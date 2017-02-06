<?php
/* Icinga Web 2 | (c) 2017 Icinga Development Team | GPLv2+ */

namespace Icinga\User\AlternationRules;

use Icinga\User;
use Icinga\User\AlternationRulesInterface;

/**
 * If a user has no domain, set it to a default one
 */
class SetDefaultDomainIfNeeded implements AlternationRulesInterface
{
    /**
     * The domain to give to a user if they don't have any
     *
     * @var string
     */
    protected $defaultDomain;

    /**
     * SetDefaultDomainIfNeeded constructor
     *
     * @param   string  $defaultDomain  The domain to give to a user if they don't have any
     */
    public function __construct($defaultDomain)
    {
        $this->defaultDomain = $defaultDomain;
    }

    /**
     * {@inheritdoc}
     */
    public function getAltered(User $user)
    {
        if ($user->getDomain() === null) {
            $alteredUser = clone $user;
            $alteredUser->setDomain($this->defaultDomain);
            return $alteredUser;
        }
    }
}
