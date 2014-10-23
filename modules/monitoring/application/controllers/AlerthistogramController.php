<?php
// {{{ICINGA_LICENSE_HEADER}}}
// {{{ICINGA_LICENSE_HEADER}}}

use Icinga\Web\Url;
use Icinga\Chart\GridChart;
use Icinga\Chart\Unit\StaticAxis;
use Icinga\Module\Monitoring\Controller;
use Icinga\Module\Monitoring\Web\Widget\SelectBox;
use Icinga\Module\Monitoring\Chart\HistogramGridChart;

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

    private function getHost()
    {
        return 'test-random-10';
    }

    private function getService()
    {
        return 'service-flapping-1';
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

        $this->addTitleTab('alerthistogram', $this->translate('Alert Histogram'));

        $this->view->intervalBox = $this->createIntervalBox();

        $host = $this->getHost();
        $service = $this->getService();

        $type = 'service';

        $this->view->chart = $this->createHistogram(
            $type, ($type === 'host') ? $host : $service
        );

        return $this;
    }

    private function hostsFromGroup($hostgroup) {
        return $this->backend->select()->from(
            'Hostgroup', array('host')
        )->where('hostgroup_name', $hostgroup)->getQuery()->fetchAll();
    }

    private function createHistogram($type, $which)
    {
        $interval = $this->getInterval();

        // $query
        $key = ($type === 'host') ? 'host_name' : 'service';
        $query = $this->backend->select()->from('eventHistory', array(
            'object_type',
            $key,
            'timestamp',
            'state',
            'type'
        ))->order('timestamp', 'ASC')
          ->where($key, $which)
          ->where('object_type', $type);

        $query->addFilter(
            new Icinga\Data\Filter\FilterExpression(
                'timestamp',
                '>=',
                $this->getBeginDate($interval)->getTimestamp()
            )
        );

        // $data
        $data = array();
        $gridChart = new HistogramGridChart();

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

        // $gridChart
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
