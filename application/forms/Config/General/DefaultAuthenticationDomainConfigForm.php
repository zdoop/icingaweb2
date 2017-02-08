<?php
/* Icinga Web 2 | (c) 2017 Icinga Development Team | GPLv2+ */

namespace Icinga\Forms\Config\General;

use Icinga\Application\Config;
use Icinga\User\Alternate;
use Icinga\User\AlternationRules\SetDefaultDomainIfNeeded;
use Icinga\Web\Form;

/**
 * Configuration form for the default domain for authentication
 *
 * This form is not used directly but as subform to the {@link GeneralConfigForm}.
 */
class DefaultAuthenticationDomainConfigForm extends Form
{
    /**
     * {@inheritdoc}
     */
    public function init()
    {
        $this->setName('form_config_general_authentication');
    }

    /**
     * {@inheritdoc}
     *
     * @return  $this
     */
    public function createElements(array $formData)
    {
        $this->addElement(
            'text',
            'authentication_default_domain',
            array(
                'label'         => $this->translate('Default Domain'),
                'description'   => $this->translate(
                    'If a user logs in without specifying any domain (e.g. "jdoe" instead of "jdoe@example.com"),'
                    . ' this default domain will be assumed.'
                )
            )
        );

        $defaultDomain = Config::app()->get('authentication', 'default_domain');
        if ($defaultDomain === null) {
            $this->addElement(
                'checkbox',
                'rename_users',
                array(
                    'label'         => $this->translate('Migrate users'),
                    'description'   => $this->translate(
                        'Check this box to rename all users in the configuration without any domain so that they are'
                        . ' in the default domain (e.g.: "jdoe" becomes "jdoe@example.com"). If you omit this,'
                        . ' your users will loose e.g. their preferences, dashboards and role memberships!'
                    ),
                    'value'         => true,
                    'ignore'        => true
                )
            );
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function onSuccess()
    {
        $renameUsers = $this->getElement('rename_users');
        if ($renameUsers !== null && $renameUsers->getValue()) {
            $defaultDomain = $this->getElement('authentication_default_domain')->getValue();
            if ($defaultDomain !== '') {
                Alternate::allUsers(new SetDefaultDomainIfNeeded($defaultDomain));
            }
        }
    }
}
