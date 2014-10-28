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
    protected $colors = array(
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

    protected $labels = array(
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

    protected $states = array(
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

    protected $periodFormats = array(
        '1d' => '%H:00:00',
        '1w' => '%Y-%m-%d',
        '1m' => '%Y-%m-%d',
        '1y' => '%Y-%m'
    );

    protected $datePeriods = array(
        '1d' => array('PT1H', 24),
        '1w' => array('P1D',   7),
        '1m' => array('P1D',  30),
        '1y' => array('P1M',  12)
    );

    protected $beginDates = array(
        '1d' => 'P1D',
        '1w' => 'P1W',
        '1m' => 'P1M',
        '1y' => 'P1Y'
    );

    protected $url;

    public function init()
    {
        $this->url = Url::fromRequest();
    }

    public function indexAction()
    {
        $data = array();
        foreach ($this->labels as $type => $labels) {
            $data[$type] = array();
            foreach ($labels as $key => $value) {
                $data[$type][$key] = array();
            }
        }

        $interval = $this->getParam('interval', '1d');
        if (false === array_key_exists($interval, $this->periodFormats)) {
            throw new Zend_Controller_Action_Exception(sprintf(
                $this->translate('Value \'%s\' for interval is not valid'),
                $interval
            ));
        }
        $this->params->remove('interval');

        foreach ($this->createPeriod($interval) as $entry) {
            $index = $this->getPeriodFormat($interval, $entry->getTimestamp());
            foreach ($data as $type => $stats) {
                foreach ($stats as $stat => $value) {
                    $data[$type][$stat][$index] = array($index, 0);
                }
            }
        }

        $query = $this->backend->select()->from('eventHistory', array(
            'object_type',
            'host',
            'service',
            'timestamp',
            'state',
            'type',
            'hostgroup',
            'servicegroup'
        ))->addFilter(new FilterOr(array(
            new FilterExpression('object_type', '=', 'host'),
            new FilterExpression('object_type', '=', 'service')
        )));

        $params = (string) $this->params;
        if ($params !== '') {
            $query->addFilter(Filter::fromQueryString($params));
        }

        foreach (
            $query->addFilter(new FilterExpression(
                'timestamp',
                '>=',
                $this->getBeginDate($interval)->getTimestamp()
            ))
            ->order('timestamp', 'ASC')
            ->getQuery()
            ->fetchAll() as $record
        ) {
            $type = $record->object_type;
            $state = $this->states[$type][$record->state];
            $dateTime = $this->getPeriodFormat($interval, $record->timestamp);
            ++$data[$type][$state][$dateTime][1];
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
                    'label'         => $this->labels[$type][$stat],
                    'color'         => $this->colors[$type][$stat],
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
        return strftime($this->periodFormats[$interval], $timestamp);
    }

    private function createPeriod($interval)
    {
        $datePeriod = $this->datePeriods[$interval];
        return new DatePeriod(
            $this->getBeginDate($interval),
            new DateInterval($datePeriod[0]),
            $datePeriod[1]
        );
    }

    private function getBeginDate($interval)
    {
        $new = new DateTime();
        return $new->sub(new DateInterval($this->beginDates[$interval]));
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
