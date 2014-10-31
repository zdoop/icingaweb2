<?php
// {{{ICINGA_LICENSE_HEADER}}}
// {{{ICINGA_LICENSE_HEADER}}}

namespace Icinga\Module\Monitoring\Backend\Ido\Query;

class StateHistoryGroupedSummaryQuery extends IdoQuery
{
    protected $columnMap = array(
        'statehistory' => array(
            'cnt_host_up'           => 'SUM(CASE WHEN sh.state = 0 AND sho.objecttype_id = 1 THEN 1 ELSE 0 END)',
            'cnt_host_down'         => 'SUM(CASE WHEN sh.state = 1 AND sho.objecttype_id = 1 THEN 1 ELSE 0 END)',
            'cnt_host_unreachable'  => 'SUM(CASE WHEN sh.state = 2 AND sho.objecttype_id = 1 THEN 1 ELSE 0 END)',
            'cnt_service_ok'        => 'SUM(CASE WHEN sh.state = 0 AND sho.objecttype_id = 2 THEN 1 ELSE 0 END)',
            'cnt_service_warning'   => 'SUM(CASE WHEN sh.state = 1 AND sho.objecttype_id = 2 THEN 1 ELSE 0 END)',
            'cnt_service_critical'  => 'SUM(CASE WHEN sh.state = 2 AND sho.objecttype_id = 2 THEN 1 ELSE 0 END)',
            'cnt_service_unknown'   => 'SUM(CASE WHEN sh.state = 3 AND sho.objecttype_id = 2 THEN 1 ELSE 0 END)',

            'hour'                  => 'DATE_FORMAT(sh.state_time, \'%Y-%m-%dT%H\')',
            '6h'                    => 'DATE_FORMAT(
FROM_UNIXTIME(UNIX_TIMESTAMP(sh.state_time) DIV 21600 * 21600)
, \'%Y-%m-%dT%H\')',
            'day'                   => 'DATE_FORMAT(sh.state_time, \'%Y-%m-%d\')',
            '3d'                    => 'DATE_FORMAT(
FROM_UNIXTIME(UNIX_TIMESTAMP(sh.state_time) DIV 259200 * 259200)
, \'%Y-%m-%d\')',
            'week'                  => 'DATE_FORMAT(sh.state_time, \'%YW%u\')',
            'month'                 => 'DATE_FORMAT(sh.state_time, \'%Y-%m\')',
            'year'                  => 'DATE_FORMAT(sh.state_time, \'%Y\')',

            'unix_timestamp'        => 'UNIX_TIMESTAMP(sh.state_time)'
        )/*,
        'servicegroups' => array(
            'servicegroup' => 'sgo.name1'
        ),

        'hostgroups' => array(
            'hostgroup'  => 'hgo.name1'
        )*/
    );

    protected function joinBaseTables()
    {
        $this->select->from(
            array('sh' => $this->prefix . 'statehistory'),
            array()
        )->join(
            array('sho' => $this->prefix . 'objects'),
            'sh.object_id = sho.object_id AND sho.is_active = 1',
            array()
        )/*
        ->group('DATE(sh.state_time)')*/;
        $this->joinedVirtualTables = array('statehistory' => true);
    }
/*
    protected function joinHostgroups()
    {
        $this->select->join(
            array('hgm' => $this->prefix . 'hostgroup_members'),
            'hgm.host_object_id = sho.object_id',
            array()
        )->join(
            array('hgs' => $this->prefix . 'hostgroups'),
            'hgm.hostgroup_id = hgs.hostgroup_id',
            array()
        )->join(
            array('hgo' => $this->prefix . 'objects'),
            'hgo.object_id = hgs.hostgroup_object_id',
            array()
        );
    }

    protected function joinServicegroups()
    {
        $this->select->join(
            array('sgm' => $this->prefix . 'servicegroup_members'),
            'sgm.service_object_id = sho.object_id',
            array()
        )->join(
            array('sgs' => $this->prefix . 'servicegroups'),
            'sgm.servicegroup_id = sgs.servicegroup_id',
            array()
        )->join(
            array('sgo' => $this->prefix . 'objects'),
            'sgo.object_id = sgs.servicegroup_object_id',
            array()
        );
    }
*/
}
