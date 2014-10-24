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

class Monitoring_AlerthistogramController extends Controller
{
    protected static $colors = array(
        'host'      => array(
            'up'            => '#4b7',
            'down'          => '#f56',
            'unreachable'   => '#a4f'
        ),
        'service'   => array(
            'ok'        => '#4b7',
            'warning'   => '#fa4',
            'critical'  => '#f56',
            'unknown'   => '#a4f'
        )
    );

    protected static $labels = array(
        'host'      => array(
            'up'            => 'Recovery (Up)',
            'down'          => 'Down',
            'unreachable'   => 'Unreachable'
        ),
        'service'   => array(
            'ok'        => 'OK',
            'warning'   => 'WARNING',
            'critical'  => 'CRITICAL',
            'unknown'   => 'UNKNOWN'
        )
    );

    protected static $states = array(
        'host'      => array(
            '0' => 'up',
            '1' => 'down',
            '2' => 'unreachable'
        ),
        'service'   => array(
            '0' => 'ok',
            '1' => 'warning',
            '2' => 'critical',
            '3' => 'unknown'
        )
    );

    const FETCH = 1;
    const ALL = 2;

    protected $url;

    public function init()
    {
        $this->url = Url::fromRequest();
    }

    public function indexAction()
    {
        $target = array();

        foreach (static::$labels as $type => $value) {
            $target[$type] = array();
            foreach (array('', 'group') as $suffix) {
                $param = $type . $suffix;
                if ($this->params->has($param)) {
                    $target[$type][$suffix] = $this->params->get($param);
                }
            }
        }
        foreach ($target as $key => $value) {
            if (empty($value)) {
                unset($target[$key]);
            }
        }

        $whatToFetch = array();

        if (array_key_exists('service', $target)) {
            $whatToFetch['service'] = static::FETCH;
            $whatToFetch['host'] = array_key_exists('host', $target)
                ? 0 : static::ALL;
        } else if (array_key_exists('host', $target)) {
            $whatToFetch['host'] = static::FETCH;
            $whatToFetch['service'] = 0;
        } else {
            foreach (static::$labels as $key => $value) {
                $whatToFetch[$key] = static::FETCH | static::ALL;
            }
        }

        $filters = array();

        foreach (static::$labels as $type => $label) {
            $filters[$type] = array();
            if (array_key_exists($type, $target)) {
                foreach ($target[$type] as $suffix => $value) {
                    switch ($suffix) {
                        case '':
                            $filters[$type][] = $value;
                            break;
                        case 'group':
                            foreach ($this->groupMembers($type, $value) as $member) {
                                $filters[$type][] = $member->{$type};
                            }
                    }
                }
            }
        }

        $this->addTitleTab('alerthistogram', $this->translate('Alert Histogram'));

        $this->view->intervalBox = $this->createIntervalBox();

        $this->view->charts = array();

        foreach (static::$labels as $type => $label) {
            if ($whatToFetch[$type] & static::FETCH) {
                $filter = null;
                if ($type === 'service' && false === empty($filters['host'])) {
                    $hosts = array();
                    foreach ($filters['host'] as $host) {
                        $hosts[] = new FilterExpression(
                            'host_name', '=', $host
                        );
                    }
                    $filter = new FilterOr($hosts);
                }
                $this->view->charts[$type] = $this->createHistogram(
                    $type, $filters[$type], $filter
                );
            }
        }

        return $this;
    }

    private function groupMembers($type, $group) {
        return $this->backend->select()->from(
            ucfirst($type) . 'group', array($type)
        )->where($type . 'group_name', $group)->getQuery()->fetchAll();
    }

    private function createHistogram($type, $which, Filter $filter = null)
    {
        $key = ($type === 'host') ? 'host_name' : $type;

        $query = $this->backend->select()->from('eventHistory', array(
            'object_type',
            $key,
            'timestamp',
            'state',
            'type'
        ))->order('timestamp', 'ASC')
          ->where('object_type', $type);

        $interval = $this->getInterval();

        $query->addFilter(
            new FilterExpression(
                'timestamp',
                '>=',
                $this->getBeginDate($interval)->getTimestamp()
            )
        );

        if ($filter !== null) {
            $query->addFilter($filter);
        }

        if (false === empty($which)) {
            $filters = array();

            foreach ($which as $subject) {
                $filters[] = new FilterExpression(
                    $key, '=', $subject
                );
            }

            $query->addFilter(new FilterOr($filters));
        }

        $data = array();

        foreach (static::$labels[$type] as $key => $value) {
            $data[$key] = array();
        }

        foreach ($this->createPeriod($interval) as $entry) {
            $index = $this->getPeriodFormat($interval, $entry->getTimestamp());
            foreach (static::$labels[$type] as $key => $value) {
                $data[$key][$index] = array($index, 0);
            }
        }

        foreach ($query->getQuery()->fetchAll() as $record) {
            ++$data[
                static::$states[$type][(int)$record->state]
            ][
                $this->getPeriodFormat($interval, $record->timestamp)
            ][1];
        }

        $gridChart = new HistogramGridChart();

        $gridChart->alignTopLeft();
        $gridChart->setAxisLabel('Date', 'Events')
            ->setXAxis(new StaticAxis())
            ->setAxisMin(null, 0);

        foreach (static::$colors[$type] as $status => $color) {
            $gridChart->drawLines(array(
                'label'         => static::$labels[$type][$status],
                'color'         => $color,
                'data'          => $data[$status],
                'showPoints'    => true
            ));
        }

        return $gridChart;
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

    private function getPeriodFormat($interval, $timestamp)
    {
        $format = '';

        switch ($interval) {
            case '1d':
                $format = '%H:00:00';
                break;
            case '1w':
                $format = '%Y-%m-%d';
                break;
            case '1m':
                $format = '%Y-%m-%d';
                break;
            case '1y':
                $format = '%Y-%m';
                break;
        }

        return strftime($format, $timestamp);
    }

    private function createPeriod($interval)
    {
        switch ($interval) {
            case '1d':
                return new DatePeriod($this->getBeginDate($interval), new DateInterval('PT1H'), 24);
                break;
            case '1w':
                return new DatePeriod($this->getBeginDate($interval), new DateInterval('P1D'), 7);
                break;
            case '1m':
                return new DatePeriod($this->getBeginDate($interval), new DateInterval('P1D'), 30);
                break;
            case '1y':
                return new DatePeriod($this->getBeginDate($interval), new DateInterval('P1M'), 12);
                break;
        }

        return new DatePeriod($this->getBeginDate($interval), new DateInterval('PT1H'), 24);
    }

    private function getBeginDate($interval)
    {
        $new = new DateTime();

        switch ($interval) {
            case '1d':
                return $new->sub(new DateInterval('P1D'));
                break;
            case '1w':
                return $new->sub(new DateInterval('P1W'));
                break;
            case '1m':
                return $new->sub(new DateInterval('P1M'));
                break;
            case '1y':
                return $new->sub(new DateInterval('P1Y'));
                break;
        }

        return null;
    }

    private function getInterval()
    {
        $interval = $this->getParam('interval', '1d');
        if (false === in_array($interval, array('1d', '1w', '1m', '1y'))) {
            throw new Zend_Controller_Action_Exception($this->translate('Value for interval is not valid'));
        }

        return $interval;
    }

    private function createIntervalBox()
    {
        $box = new SelectBox(
            'intervalBox',
            array(
                '1d' => t('One day'),
                '1w' => t('One week'),
                '1m' => t('One month'),
                '1y' => t('One year')
            ),
            t('Report interval'),
            'interval'
        );
        $box->applyRequest($this->getRequest());
        return $box;
    }
}
