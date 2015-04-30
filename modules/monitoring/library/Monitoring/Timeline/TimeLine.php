<?php
/* Icinga Web 2 | (c) 2013-2015 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\Monitoring\Timeline;

use DateTime;
use Exception;
use ArrayIterator;
use AppendIterator;
use IteratorIterator;
use Traversable;
use Icinga\Exception\IcingaException;
use IteratorAggregate;
use Icinga\Data\Filter\Filter;
use Icinga\Web\Hook;
use Icinga\Web\Session\SessionNamespace;
use Icinga\Module\Monitoring\DataView\DataView;

/**
 * Represents a set of events in a specific range of time
 */
class TimeLine implements IteratorAggregate
{
    /**
     * The groups this timeline uses for display purposes
     *
     * @var array
     */
    private $displayGroups = null;

    /**
     * The session to use
     *
     * @var SessionNamespace
     */
    protected $session;

    /**
     * The base that is used to calculate each circle's diameter
     *
     * @var float
     */
    protected $calculationBase;

    /**
     * The dataview to fetch entries from
     *
     * @var DataView
     */
    protected $dataview;

    /**
     * The names by which to group entries
     *
     * @var array
     */
    protected $identifiers;

    /**
     * The range of time for which to display entries
     *
     * @var TimeRange
     */
    protected $displayRange;

    /**
     * The end of the range of time based on which to calculate forecasts
     *
     * @var DateTime
     */
    protected $forecastEnd = null;

    /**
     * Cache for self::getCalculationBase()
     *
     * @var float
     */
    protected $generatedCalculationBase = null;

    /**
     * Cache for self::countEntries()
     *
     * @var array
     */
    protected $countedEntries = null;

    /**
     * Cache for self::groupEntries()
     *
     * @var array
     */
    protected $groupedEntries = null;

    /**
     * The maximum diameter each circle can have
     *
     * @var float
     */
    protected $circleDiameter = 100.0;

    /**
     * The minimum diameter each circle can have
     *
     * @var float
     */
    protected $minCircleDiameter = 1.0;

    /**
     * The unit of a circle's diameter
     *
     * @var string
     */
    protected $diameterUnit = 'px';

    /**
     * Return a iterator for this timeline
     *
     * @return  ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->toArray());
    }

    /**
     * Create a new timeline
     *
     * The given dataview must provide the following columns:
     * - name   A string identifying an entry (Corresponds to the keys of "$identifiers")
     * - time   A unix timestamp that defines where to place an entry on the timeline
     *
     * @param   DataView    $dataview       The dataview to fetch entries from
     * @param   array       $identifiers    The names by which to group entries
     */
    public function __construct(DataView $dataview, array $identifiers)
    {
        $this->dataview = $dataview;
        $this->identifiers = $identifiers;
    }

    /**
     * Set the session to use
     *
     * @param   SessionNamespace    $session    The session to use
     */
    public function setSession(SessionNamespace $session)
    {
        $this->session = $session;
    }

    /**
     * Set the range of time for which to display elements
     *
     * @param   TimeRange   $range      The range of time for which to display elements
     */
    public function setDisplayRange(TimeRange $range)
    {
        $this->displayRange = $range;
    }

    /**
     * Set the end of the range of time based on which to calculate forecasts
     *
     * @param   DateTime    $end        The end of the range of time
     *                                  based on which to calculate forecasts
     *
     * @return  $this
     */
    public function setForecastEnd(DateTime $end)
    {
        $this->forecastEnd = $end;
        return $this;
    }

    /**
     * Set the maximum diameter each circle can have
     *
     * @param   string      $width      The diameter to set, suffixed with its unit
     *
     * @throws  Exception               If the given diameter is invalid
     */
    public function setMaximumCircleWidth($width)
    {
        $matches = array();
        if (preg_match('#([\d|\.]+)([a-z]+|%)#', $width, $matches)) {
            $this->circleDiameter = floatval($matches[1]);
            $this->diameterUnit = $matches[2];
        } else {
            throw new IcingaException(
                'Width "%s" is not a valid width',
                $width
            );
        }
    }

    /**
     * Set the minimum diameter each circle can have
     *
     * @param   string      $width      The diameter to set, suffixed with its unit
     *
     * @throws  Exception               If the given diameter is invalid or its unit differs from the maximum
     */
    public function setMinimumCircleWidth($width)
    {
        $matches = array();
        if (preg_match('#([\d|\.]+)([a-z]+|%)#', $width, $matches)) {
            if ($matches[2] === $this->diameterUnit) {
                $this->minCircleDiameter = floatval($matches[1]);
            } else {
                throw new IcingaException(
                    'Unit needs to be in "%s"',
                    $this->diameterUnit
                );
            }
        } else {
            throw new IcingaException(
                'Width "%s" is not a valid width',
                $width
            );
        }
    }

    /**
     * Return all known group types (identifiers) with their respective labels and colors as array
     *
     * @return  array
     */
    public function getGroupInfo()
    {
        $groupInfo = array();
        foreach ($this->identifiers as $name => $attributes) {
            $groupInfo[$name]['label'] = $attributes['label'];
            $groupInfo[$name]['color'] = $attributes['color'];
        }

        return $groupInfo;
    }

    /**
     * Return the circle's diameter for the given event group
     *
     * @param   TimeEntry   $group          The group for which to return a circle width
     * @param   int         $precision      Amount of decimal places to preserve
     *
     * @return  string
     */
    public function calculateCircleWidth(TimeEntry $group, $precision = 0)
    {
        $base = $this->getCalculationBase(true);
        $factor = log($group->getValue() * $group->getWeight(), $base) / 100;
        $width = $this->circleDiameter * $factor;
        return sprintf(
            '%.' . $precision . 'F%s',
            $width > $this->minCircleDiameter ? $width : $this->minCircleDiameter,
            $this->diameterUnit
        );
    }

    /**
     * Return an extrapolated circle width for the given event group
     *
     * @param   TimeEntry   $group          The event group for which to return an extrapolated circle width
     * @param   int         $precision      Amount of decimal places to preserve
     *
     * @return  string
     */
    public function getExtrapolatedCircleWidth(TimeEntry $group, $precision = 0)
    {
        $totalCount = $eventCount = 0;
        foreach ($this as $timeInfo) {
            ++$totalCount;
            if (array_key_exists($group->getName(), $timeInfo[1])) {
                $eventCount += $timeInfo[1][$group->getName()]->getValue();
            }
        }

        $extrapolatedCount = (int) $eventCount / $totalCount;
        if ($extrapolatedCount < $group->getValue()) {
            return $this->calculateCircleWidth($group, $precision);
        }

        return $this->calculateCircleWidth(
            TimeEntry::fromArray(
                array(
                    'value'     => $extrapolatedCount,
                    'weight'    => $group->getWeight()
                )
            ),
            $precision
        );
    }

    /**
     * Return the base that should be used to calculate circle widths
     *
     * @param   bool    $create     Whether to generate a new base if none is known yet
     *
     * @return  float|null
     */
    public function getCalculationBase($create)
    {
        if ($this->calculationBase === null) {
            $calculationBase = $this->session !== null ? $this->session->get('calculationBase') : null;

            if ($create) {
                if ($this->generatedCalculationBase === null) {
                    $this->generateGroupedEntries();
                }
                $new = $this->generatedCalculationBase;
                if ($new > $calculationBase) {
                    $this->calculationBase = $new;

                    if ($this->session !== null) {
                        $this->session->calculationBase = $new;
                    }
                } else {
                    $this->calculationBase = $calculationBase;
                }
            } else {
                return $calculationBase;
            }
        }

        return $this->calculationBase;
    }

    /**
     * Generate grouped entries (self::$displayGroups) and
     * calculation base (self::$generatedCalculationBase)
     */
    protected function generateGroupedEntries()
    {
        if ($this->displayGroups === null) {
            $this->displayGroups = array();
            $highestValue = 0;
            foreach ($this->groupEntries($this->fetchResults(), new TimeRange(
                $this->displayRange->getStart(),
                $this->forecastEnd,
                $this->displayRange->getInterval()
            )) as $key => $groups) {
                if ($key > $this->displayRange->getEnd()->getTimestamp()) {
                    $this->displayGroups[$key] = $groups;
                }
                foreach ($groups as $group) {
                    if ($highestValue < (
                        $newVal = $group->getValue() * $group->getWeight()
                    )) {
                        $highestValue = $newVal;
                    }
                }
            }
            $this->generatedCalculationBase = pow($highestValue, 0.01);
        }
    }

    /**
     * Fetch all entries and forecasts by using the dataview associated with this timeline
     *
     * @return  Iterator    The dataview's result
     */
    private function fetchResults()
    {
        $hookResults = new AppendIterator;
        foreach (Hook::all('timeline') as $timelineProvider) {
            $hookResults->append($timelineProvider->fetchResults(new TimeRange(
                $this->displayRange->getStart(),
                $this->forecastEnd,
                $this->displayRange->getInterval()
            )));
            foreach ($timelineProvider->getIdentifiers() as $identifier => $attributes) {
                if (!array_key_exists($identifier, $this->identifiers)) {
                    $this->identifiers[$identifier] = $attributes;
                }
            }
        }

        $results = new AppendIterator;
        $results->append(new IteratorIterator(
            $this->dataview->applyFilter(Filter::matchAll(
                Filter::where('type', array_keys($this->identifiers)),
                Filter::expression('timestamp', '<=', $this->displayRange->getStart()->getTimestamp()),
                Filter::expression('timestamp', '>', $this->forecastEnd->getTimestamp())
            ))->getQuery()->getSelectQuery()->query()
        ));
        $results->append($hookResults);
        return $results;
    }

    /**
     * Return the given entries grouped together
     *
     * @param   Traversable     $entries        The entries to group
     * @param   TimeRange       $timeRange      The range of time to group by
     *
     * @return  array                           The grouped entries
     */
    protected function groupEntries(Traversable $entries, TimeRange $timeRange)
    {
        if ($this->groupedEntries === null) {
            $this->groupedEntries = array();
            foreach ($this->countEntries($entries, $timeRange) as $name => $data) {
                foreach ($data as $timestamp => $count) {
                    $dateTime = new DateTime();
                    $dateTime->setTimestamp($timestamp);
                    $this->groupedEntries[$timestamp][$name] = TimeEntry::fromArray(
                        array_merge(
                            $this->identifiers[$name],
                            array(
                                'name'      => $name,
                                'value'     => $count,
                                'dateTime'  => $dateTime
                            )
                        )
                    );
                }
            }
        }
        return $this->groupedEntries;
    }

    /**
     * Return the given entries counted
     *
     * @param   Traversable     $entries        The entries to count
     * @param   TimeRange       $timeRange      The range of time to group by
     *
     * @return  array                           The counted entries
     */
    protected function countEntries(Traversable $entries, TimeRange $timeRange)
    {
        if ($this->countedEntries === null) {
            $this->countedEntries = array();
            foreach ($entries as $entry) {
                $entryTime = new DateTime();
                $entryTime->setTimestamp($entry->time);
                $timestamp = $timeRange->findTimeframe($entryTime, true);

                if ($timestamp !== null) {
                    if (array_key_exists($entry->name, $this->countedEntries)
                        && array_key_exists($timestamp, $this->countedEntries[$entry->name])) {
                        $this->countedEntries[$entry->name][$timestamp] += 1;
                    } else {
                        $this->countedEntries[$entry->name][$timestamp] = 1;
                    }
                }
            }
        }
        return $this->countedEntries;
    }

    /**
     * Return the contents of this timeline as array
     *
     * @return  array
     */
    protected function toArray()
    {
        $this->generateGroupedEntries();

        $array = array();
        foreach ($this->displayRange as $timestamp => $timeframe) {
            $array[] = array(
                $timeframe,
                array_key_exists($timestamp, $this->displayGroups) ? $this->displayGroups[$timestamp] : array()
            );
        }

        return $array;
    }
}
