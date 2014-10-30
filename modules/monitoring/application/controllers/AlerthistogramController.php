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

        $start = null;
        $unit = 'month';
        $dateTimeFormat = $this->dateTimeFormats[$unit];
        if ($params['period'] !== null) {
            $unit = $this->units[$params['period']];
            $dateTimeFormat = $this->dateTimeFormats[$unit];
            $end = new DateTime($params['end']);
            $start = $end->sub(new DateInterval(
                $this->periods[$params['period']]
            ))->format($dateTimeFormat);
        }

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
            $query->addFilter(new FilterExpression($unit, '>=', $start));
        }

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

        $interval = new DateInterval($this->periods[$unit]);
        for ($current = $first; $current <= $last;) {
            foreach ($data as $type => $stats) {
                foreach ($stats as $stat => $timestamps) {
                    if (false === array_key_exists($current, $timestamps)) {
                        $data[$type][$stat][$current] = array($current, 0);
                    }
                }
            }
            $current = DateTime::createFromFormat($dateTimeFormat, $current);
            $current->add($interval);
            $current = $current->format($dateTimeFormat);
        }

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
}
