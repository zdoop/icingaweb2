<?php
/* Icinga Web 2 | (c) 2013-2015 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\Monitoring\Web\Hook;

use Icinga\Module\Monitoring\Timeline\TimeRange;

/**
 * Base class for TimeLine providers
 */
abstract class TimelineProviderHook
{
    /**
     * Return the names by which to group entries
     *
     * @return  array   An array with the names as keys and their attribute-lists as values
     */
    abstract public function getIdentifiers();

    /**
     * Return the visible entries supposed to be shown on the timeline
     * and the entries supposed to be used to calculate forecasts
     *
     * @param   TimeRange   $range      The range of time for which to fetch entries
     *
     * @return  Iterator                The entries to display on the timeline
     */
    abstract public function fetchResults(TimeRange $range);
}
