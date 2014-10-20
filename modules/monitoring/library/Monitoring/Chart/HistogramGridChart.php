<?php
// {{{ICINGA_LICENSE_HEADER}}}
// {{{ICINGA_LICENSE_HEADER}}}

namespace Icinga\Module\Monitoring\Chart;

use Icinga\Chart\Axis;
use Icinga\Chart\GridChart;
use Icinga\Chart\SVGRenderer;

class HistogramGridChart extends GridChart
{
    /**
     * @see parent::init()
     */
    protected function init()
    {
        $this->renderer = new SVGRenderer(200, 100);
        $this->setAxis(Axis::createLinearAxis());
    }
}
