<?php
// {{{ICINGA_LICENSE_HEADER}}}
// {{{ICINGA_LICENSE_HEADER}}}

use Icinga\Chart\Unit\StaticAxis;
use Icinga\Data\Filter\Filter;
use Icinga\Data\Filter\FilterExpression;
use Icinga\Data\Filter\FilterOr;
use Icinga\Module\Monitoring\Chart\HistogramGridChart;
use Icinga\Module\Monitoring\Controller;
use Icinga\Module\Monitoring\Web\Widget\SelectBox;
use Icinga\Web\Url;
use Icinga\Module\Monitoring\Object\Host;
use Icinga\Module\Monitoring\Object\Service;

class Monitoring_AlerthistogramController extends Controller
{
    protected $colors = array(
        'host'    => array(1207, 3926, 2639),
        'service' => array(1207, 4004, 3926, 2639)
    );

    protected $dateTimeFormats = array(
        'hour'  => 'Y-m-d\\TH',
        '6h'    => 'Y-m-d\\TH',
        'day'   => 'Y-m-d',
        '3d'    => 'Y-m-d',
        'week'  => 'Y\\WW',
        'month' => 'Y-m',
        'year'  => 'Y'
    );

    protected $dateTimeRegex = array(
        'hour'  => '(?P<year>[0-9]{4})-(?P<month>[0-9]{2})-(?P<day>[0-9]{2})T(?P<hour>[0-9]{2})',
        '6h'    => '(?P<year>[0-9]{4})-(?P<month>[0-9]{2})-(?P<day>[0-9]{2})T(?P<hour>[0-9]{2})',
        'day'   => '(?P<year>[0-9]{4})-(?P<month>[0-9]{2})-(?P<day>[0-9]{2})',
        '3d'    => '(?P<year>[0-9]{4})-(?P<month>[0-9]{2})-(?P<day>[0-9]{2})',
        'week'  => '(?P<year>[0-9]{4})W(?P<week>[0-9]{2})',
        'month' => '(?P<year>[0-9]{4})-(?P<month>[0-9]{2})',
        'year'  => '(?P<year>[0-9]{4})'
    );

    protected $periods = array(
        'd' => 'P1D',
        'w' => 'P1W',
        'm' => 'P1M',
        'q' => 'P3M',
        'y' => 'P1Y',

        'hour'  => 'PT1H',
        '6h'    => 'PT6H',
        'day'   => 'P1D',
        '3d'    => 'P3D',
        'week'  => 'P1W',
        'month' => 'P1M'
    );

    protected $units = array(
        'd' => 'hour',
        'w' => '6h',
        'm' => 'day',
        'q' => '3d',
        'y' => 'week'
    );

    protected $url;

    public function init()
    {
        $this->url = Url::fromRequest();
    }

    public function indexAction()
    {
        $params = array(
            'period' => null,
            'end'    => date(DateTime::ISO8601)
        );
        foreach ($params as $param => $default) {
            $params[$param] = $this->getParam($param, $default);
            $this->params->remove($param);
        }

        if (false === (
            $params['period'] === null ||
            array_key_exists($params['period'], $this->units)
        )) {
            throw new Zend_Controller_Action_Exception(sprintf(
                $this->translate('Value \'%s\' for period is not valid'),
                $params['period']
            ));
        }

        $endDT = new DateTime($params['end']);
        $end = $endDT->getTimestamp();
        $unit = $this->units[$params['period']] ?: 'month';
        $start = ($params['period'] === null)
            ? null
            : $endDT->sub(new DateInterval(
                $this->periods[$params['period']]
            ))->getTimestamp();
        $dateTimeFormat = $this->dateTimeFormats[$unit];

        $data = array();
        foreach (array(
            'host'    => array(0, 1, 2),
            'service' => array(0, 1, 2, 3)
        ) as $type => $stats) {
            $data[$type] = array();
            foreach ($stats as $stat) {
                $data[$type][$stat] = array();
            }
        }

        $query = $this->backend->select()->from(
            'stateHistoryGroupedSummary',
            array(
                'timestamp' => $unit,
                'cnt_host_up',
                'cnt_host_down',
                'cnt_host_unreachable',
                'cnt_service_ok',
                'cnt_service_warning',
                'cnt_service_critical',
                'cnt_service_unknown'
            )
        );

        $params = (string) $this->params;
        if ($params !== '') {
            $query->addFilter(Filter::fromQueryString($params));
        }

        if ($start !== null) {
            $query->addFilter(new FilterExpression('unix_timestamp', '>=', $start));
        }
        $query->addFilter(new FilterExpression('unix_timestamp', '<', $end));

        $first = null;
        $last = null;

        foreach (
            $query
            ->order($unit, 'ASC')
            ->getQuery()
            ->group('timestamp')
            ->fetchAll() as $record
        ) {
            if ($first === null) {
                $first = $record->timestamp;
            }
            $last = $record->timestamp;

            foreach ($data as $type => $stats) {
                foreach ($stats as $stat) {
                    $data[$type][(int) $stat][$record->timestamp] = array(
                        $record->timestamp,
                        (int) $record->{sprintf(
                            'cnt_%s_%s',
                            $type,
                            $this->getStateText($type, $stat)
                        )}
                    );
                }
            }
        }

        $this->addTitleTab('alerthistogram', $this->translate('Alert Histogram'));

        $box = $this->view->intervalBox = new SelectBox(
            'intervalBox',
            array(
                'd' => $this->translate('Last day'),
                'w' => $this->translate('Last week'),
                'm' => $this->translate('Last month'),
                'q' => $this->translate('Last Quarter'),
                'y' => $this->translate('Last year')
            ),
            $this->translate('Report period'),
            'period'
        );
        $box->applyRequest($this->getRequest());

        $this->view->charts = array();

        if ($first === null) {
            return $this;
        }

        $currentDT = $this->createFromFormat($first, $unit);
        $interval = new DateInterval($this->periods[$unit]);
        $current = $currentDT->format($dateTimeFormat);
        do {
            foreach ($data as $type => $stats) {
                foreach ($stats as $stat => $timestamps) {
                    if (false === array_key_exists($current, $timestamps)) {
                        $data[$type][$stat][$current] = array($current, 0);
                    }
                }
            }
            $currentDT->add($interval);
            $current = $currentDT->format($dateTimeFormat);
        } while ($current <= $last);

        foreach ($data as $type => $stats) {
            foreach ($stats as $stat => $timestamps) {
                $keepStat = false;
                foreach ($timestamps as $value) {
                    if ($value[1] !== 0) {
                        $keepStat = true;
                    }
                }
                if (false === $keepStat) {
                    unset($data[$type][$stat]);
                }
            }

            if (empty($data[$type])) {
                unset($data[$type]);
                continue;
            }

            $gridChart = new HistogramGridChart();
            $gridChart->alignTopLeft();
            $gridChart
                ->setAxisLabel('Date', 'Events')
                ->setXAxis(new StaticAxis())
                ->setAxisMin(null, 0);

            foreach ($stats as $stat => $timestamps) {
                ksort($timestamps);

                $gridChart->drawLines(array(
                    'label'         => $this->getStateText($type, $stat, true),
                    'color'         => sprintf('#%03x', $this->colors[$type][$stat]),
                    'data'          => $timestamps,
                    'showPoints'    => true
                ));
            }

            $this->view->charts[$type] = $gridChart;
        }

        return $this;
    }

    protected function addTitleTab($action, $title = false)
    {
        $title = $title ?: ucfirst($action);

        $this->getTabs()->add(
            $action,
            array(
                'title' => $title,
                'url'   => $this->url
            )
        )->activate($action);

        $this->view->title = $title;
    }

    private function getStateText($type, $state, $translate = false) {
        switch ($type) {
            case 'host':
                return Host::getStateText($state, $translate);
            case 'service':
                return Service::getStateText($state, $translate);
        }
    }

    private function createFromFormat($subject, $unit)
    {
        $matches = array();
        if (preg_match(
            '#^' . $this->dateTimeRegex[$unit] . '$#',
            $subject,
            $matches)
        ) {
            foreach ($matches as $key => $value) {
                if (is_int($key)) {
                    unset($matches[$key]);
                } else {
                    $matches[$key] = (int) $value;
                }
            }
            $DT = new DateTime();
            if ($unit === 'week') {
                return $DT->setISODate(
                    $matches['year'],
                    $matches['week']
                )->setTime(0, 0);
            }
            foreach (array(
                array('hour', 'minute'),
                array('month', 'day')
            ) as $default => $keys) {
                foreach ($keys as $key) {
                    if (false === array_key_exists($key, $matches)) {
                        $matches[$key] = $default;
                    }
                }
            }
            return $DT->setDate(
                $matches['year'],
                $matches['month'],
                $matches['day']
            )->setTime(
                $matches['hour'],
                $matches['minute']
            );
        }
    }
}
