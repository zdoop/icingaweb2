<?php
// {{{ICINGA_LICENSE_HEADER}}}
// {{{ICINGA_LICENSE_HEADER}}}

namespace Icinga\Module\Monitoring\Backend\Ido\Query;

use Icinga\Data\Filter\Filter;
use Zend_Db_Select;

class EventHistoryQuery extends IdoQuery
{
    protected $subQueries = array();

    protected $columnMap = array(
        'eventhistory' => array(
            'cnt_notification'    => "SUM(CASE eh.type WHEN 'notify' THEN 1 ELSE 0 END)",
            'cnt_hard_state'      => "SUM(CASE eh.type WHEN 'hard_state' THEN 1 ELSE 0 END)",
            'cnt_soft_state'      => "SUM(CASE eh.type WHEN 'hard_state' THEN 1 ELSE 0 END)",
            'cnt_downtime_start'  => "SUM(CASE eh.type WHEN 'dt_start' THEN 1 ELSE 0 END)",
            'cnt_downtime_end'    => "SUM(CASE eh.type WHEN 'dt_end' THEN 1 ELSE 0 END)",
            'host'                => 'eho.name1 COLLATE latin1_general_ci',
            'service'             => 'eho.name2 COLLATE latin1_general_ci',
            'host_name'           => 'eho.name1 COLLATE latin1_general_ci',
            'service_description' => 'eho.name2 COLLATE latin1_general_ci',
            'object_type'         => 'eh.object_type',
            'timestamp'           => 'eh.timestamp',
            'state'               => 'eh.state',
            'attempt'             => 'eh.attempt',
            'max_attempts'        => 'eh.max_attempts',
            'output'              => 'eh.output', // we do not want long_output
            'type'                => 'eh.type',
            'hostgroup'           => '(NULL)', // We do not allow to fetch hostgroups, this is for filters only
            'servicegroup'        => '(NULL)', // We do not allow to fetch servicegroups, this is for filters only
            'service_host_name'   => 'eho.name1 COLLATE latin1_general_ci',
            'service_description' => 'eho.name2 COLLATE latin1_general_ci'
        )
    );

    protected $useSubqueryCount = true;

    protected function joinBaseTables()
    {
        $columns = array(
            'timestamp',
            'object_id',
            'type',
            'output',
            'state',
            'state_type',
            'object_type',
            'attempt',
            'max_attempts',
        );
        $this->subQueries = array(
            $this->createSubQuery('Statehistory', $columns),
            $this->createSubQuery('Downtimestarthistory', $columns),
            $this->createSubQuery('Downtimeendhistory', $columns),
            $this->createSubQuery('Commenthistory', $columns),
            $this->createSubQuery('Commentdeletionhistory', $columns),
            $this->createSubQuery('Notificationhistory', $columns)
        );
        $sub = $this->db->select()->union($this->subQueries, Zend_Db_Select::SQL_UNION_ALL);

        $this->select->from(
            array('eho' => $this->prefix . 'objects'),
            '*'
        )->join(
            array('eh' => $sub),
            'eho.' . $this->object_id . ' = eh.' . $this->object_id . ' AND eho.is_active = 1',
            array()
        );
        $this->joinedVirtualTables = array('eventhistory' => true);
    }

    public function order($columnOrAlias, $dir = null)
    {
        foreach ($this->subQueries as $sub) {
            $sub->requireColumn($columnOrAlias);
        }

        return parent::order($columnOrAlias, $dir);
    }

    public function addFilter(Filter $filter)
    {
  	    foreach ($this->subQueries as $sub) {
		        $sub->applyFilter(clone $filter);
		    }
		    return $this;
    }

    public function where($condition, $value = null)
    {
        if ($condition !== 'hostgroup' && $condition !== 'servicegroup') {
            $this->requireColumn($condition);
        }
        foreach ($this->subQueries as $sub) {
            $sub->where($condition, $value);
        }
        return $this;
    }
}
