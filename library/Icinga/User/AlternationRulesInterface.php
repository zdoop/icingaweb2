<?php
/* Icinga Web 2 | (c) 2017 Icinga Development Team | GPLv2+ */

namespace Icinga\User;

use Icinga\User;

/**
 * To be implemented by classes which decide whether/how users shall be altered
 */
interface AlternationRulesInterface
{
    /**
     * Return an altered copy of the given user or null if no alternation needed
     *
     * @param   User        $user
     *
     * @return  User|null
     */
    public function getAltered(User $user);
}
