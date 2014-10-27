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

    protected $url;

    public function init()
    {
        $this->url = Url::fromRequest();
    }

    public function indexAction()
    {
        $data = array();
        foreach (static::$labels as $type => $labels) {
            $data[$type] = array();
            foreach ($labels as $key => $value) {
                $data[$type][$key] = array();
            }
        }

        $interval = $this->getInterval();

        foreach ($this->createPeriod($interval) as $entry) {
            $index = $this->getPeriodFormat($interval, $entry->getTimestamp());
            foreach ($data as $type => $stats) {
                foreach ($stats as $stat => $value) {
                    $data[$type][$stat][$index] = array($index, 0);
                }
            }
        }

        foreach ($this->backend->select()->from('eventHistory', array(
                'object_type',
                'host',
                'service',
                'timestamp',
                'state',
                'type',
                'hostgroup',
                //'servicegroup'
            ))
                ->addFilter(new FilterOr(array(
                    new FilterExpression('object_type', '=', 'host'),
                    new FilterExpression('object_type', '=', 'service')
                )))
                ->addFilter(Filter::fromQueryString((string) $this->params))
                ->addFilter(new FilterExpression(
                    'timestamp', '>=',
                    $this->getBeginDate($interval)->getTimestamp()
                ))
                ->order('timestamp', 'ASC')
                ->getQuery()
                ->fetchAll() as $record) {
            $type = $record->object_type;
            ++$data[$type][
                static::$states[$type][$record->state]
            ][
                $this->getPeriodFormat($interval, $record->timestamp)
            ][1];
        }

        $this->view->charts = array();

        foreach ($data as $type => $stats) {
            foreach ($stats as $stat => $values) {
                $keepStat = false;
                foreach ($values as $value) {
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
            foreach ($stats as $stat => $value) {
                $gridChart->drawLines(array(
                    'label'         => static::$labels[$type][$stat],
                    'color'         => static::$colors[$type][$stat],
                    'data'          => $value,
                    'showPoints'    => true
                ));
            }
            $this->view->charts[$type] = $gridChart;
        }

        $this->addTitleTab('alerthistogram', $this->translate('Alert Histogram'));

        $this->view->intervalBox = $this->createIntervalBox();

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
