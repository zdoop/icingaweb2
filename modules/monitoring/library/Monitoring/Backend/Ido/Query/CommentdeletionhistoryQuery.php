<?php
// {{{ICINGA_LICENSE_HEADER}}}
// {{{ICINGA_LICENSE_HEADER}}}

namespace Icinga\Module\Monitoring\Backend\Ido\Query;

class CommentdeletionhistoryQuery extends IdoQuery
{
    protected $columnMap = array(
        'commenthistory' => array(
            'state_time'    => 'h.deletion_time',
            'timestamp'     => 'UNIX_TIMESTAMP(h.deletion_time)',
            'raw_timestamp' => 'h.deletion_time',
            'object_id'     => 'h.object_id',
            'type'          => "(CASE h.entry_type WHEN 1 THEN 'comment_deleted' WHEN 2 THEN 'dt_comment_deleted' WHEN 3 THEN 'flapping_deleted' WHEN 4 THEN 'ack_deleted' END)",
            'state'         => '(NULL)',
            'state_type'    => '(NULL)',
            'output'        => "('[' || h.author_name || '] ' || h.comment_data)",
            'attempt'       => '(NULL)',
            'max_attempts'  => '(NULL)',

            'host'                => 'o.name1 COLLATE latin1_general_ci',
            'service'             => 'o.name2 COLLATE latin1_general_ci',
            'host_name'           => 'o.name1 COLLATE latin1_general_ci',
            'service_description' => 'o.name2 COLLATE latin1_general_ci',
            'service_host_name'   => 'o.name1 COLLATE latin1_general_ci',
            'service_description' => 'o.name2 COLLATE latin1_general_ci',
            'object_type'         => "CASE WHEN o.objecttype_id = 1 THEN 'host' ELSE 'service' END"
        )
    );

    public function whereToSql($col, $sign, $expression)
    {
        if ($col === 'UNIX_TIMESTAMP(h.deletion_time)') {
            return 'h.deletion_time ' . $sign . ' ' . $this->timestampForSql($this->valueToTimestamp($expression));
        } else {
            return parent::whereToSql($col, $sign, $expression);
        }
    }

    protected function joinBaseTables()
    {
        $this->select->from(
            array('o' => $this->prefix . 'objects'),
            array()
        )->join(
            array('h' => $this->prefix . 'commenthistory'),
            'o.' . $this->object_id . ' = h.' . $this->object_id . " AND o.is_active = 1 AND h.deletion_time > '1970-01-02 00:00:00' AND h.entry_type <> 2",
            array()
        );
        $this->joinedVirtualTables = array('commenthistory' => true);
    }

    protected function joinHostgroups()
    {
        $this->select->join(
            array('hgm' => $this->prefix . 'hostgroup_members'),
            'hgm.host_object_id = eho.object_id',
            array()
        )->join(
            array('hg' => $this->prefix . 'hostgroups'),
            'hgm.hostgroup_id = hg.' . $this->hostgroup_id,
            array()
        )->join(
            array('hgo' => $this->prefix . 'objects'),
            'hgo.' . $this->object_id. ' = hg.hostgroup_object_id' . ' AND hgo.is_active = 1',
            array()
        );
        return $this;
    }
}
