<?php
// {{{ICINGA_LICENSE_HEADER}}}
// {{{ICINGA_LICENSE_HEADER}}}

namespace Icinga\Module\Monitoring\DataView;

class StateHistoryGroupedSummary extends DataView
{
    /**
     * Retrieve columns provided by this view
     *
     * @return array
     */
    public function getColumns()
    {
        return array(
            'cnt_host_up',
            'cnt_host_down',
            'cnt_host_unreachable',
            'cnt_service_ok',
            'cnt_service_warning',
            'cnt_service_critical',
            'cnt_service_unknown',

            'hour',
            '6h',
            'day',
            '3d',
            'week',
            'month',
            'year'
        );
    }

    public function getSortRules()
    {
        return array();
    }
}
