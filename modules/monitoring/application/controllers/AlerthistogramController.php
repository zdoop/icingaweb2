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
        $this->addTitleTab('alerthistogram', $this->translate('Alert Histogram'));

        $this->view->intervalBox = $this->createIntervalBox();

        $interval = $this->getInterval();
        $host = $this->getHost();
        $service = $this->getService();

        $period = $this->createPeriod($interval);

        $type = 'service';

        if ($type === 'service') {

            $records = $this->getServiceRecords($interval, $service);

            $data = array(
                'ok' => array(),
                'warning' => array(),
                'critical' => array(),
                'unknown' => array(),
            );

            foreach ($period as $entry) {
                $index = $this->getPeriodFormat($interval, $entry->getTimestamp());

                $data['ok'][$index] = array($index, 0);
                $data['warning'][$index] = array($index, 0);
                $data['critical'][$index] = array($index, 0);
                $data['unknown'][$index] = array($index, 0);
            }

            foreach ($records as $record) {
                $index = $this->getPeriodFormat($interval, $record->timestamp);

                switch ($record->state) {
                    case '0':
                        $data['ok'][$index][1]++;
                        break;
                    case '1':
                        $data['warning'][$index][1]++;
                        break;
                    case '2':
                        $data['critical'][$index][1]++;
                        break;
                    case '3':
                        $data['unknown'][$index][1]++;
                        break;
                }
            }

        } elseif ($type === 'host') {

            $records = $this->getHostRecords($interval, $host);

            $data = array(
                'up' => array(),
                'down' => array(),
                'unreachable' => array()
            );

            foreach ($period as $entry) {
                $index = $this->getPeriodFormat($interval, $entry->getTimestamp());

                $data['up'][$index] = array($index, 0);
                $data['down'][$index] = array($index, 0);
                $data['unreachable'][$index] = array($index, 0);
            }

            foreach ($records as $record) {
                $index = $this->getPeriodFormat($interval, $record->timestamp);

                switch ($record->state) {
                    case '0':
                        $data['up'][$index][1]++;
                        break;
                    case '1':
                        $data['down'][$index][1]++;
                        break;
                    case '2':
                        $data['unreachable'][$index][1]++;
                        break;
                }
            }
        }

        $this->view->chart = $this->createHistogram($type, $data);

        return $this;
    }

    private function getServiceRecords($interval, $service)
    {
        /** @var \Icinga\Module\Monitoring\DataView\DataView $query */
        $query = $this->backend->select()->from('eventHistory', array(
            'object_type',
            'service',
            'timestamp',
            'state',
            'type'
        ))->order('timestamp', 'ASC')
            ->where('service', $service)
            ->where('object_type', 'service');

        $query->addFilter(
            new Icinga\Data\Filter\FilterExpression(
                'timestamp',
                '>=',
                $this->getBeginDate($interval)->getTimestamp()
            )
        );

        return $query->getQuery()->fetchAll();
    }

    private function getHostRecords($interval, $host)
    {
        /** @var \Icinga\Module\Monitoring\DataView\DataView $query */
        $query = $this->backend->select()->from('eventHistory', array(
            'object_type',
            'host_name',
            'timestamp',
            'state',
            'type'
        ))->order('timestamp', 'ASC')
          ->where('host_name', $host)
          ->where('object_type', 'host');

        $query->addFilter(
            new Icinga\Data\Filter\FilterExpression(
                'timestamp',
                '>=',
                $this->getBeginDate($interval)->getTimestamp()
            )
        );

        return $query->getQuery()->fetchAll();
    }

    private function createHistogram($type, $data)
    {
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
