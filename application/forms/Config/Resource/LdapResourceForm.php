<?php
/* Icinga Web 2 | (c) 2014 Icinga Development Team | GPLv2+ */

namespace Icinga\Forms\Config\Resource;

use Icinga\Application\Config;
use Icinga\Data\ConfigObject;
use Icinga\Data\ResourceFactory;
use Icinga\Protocol\Ldap\LdapCapabilities;
use Icinga\Protocol\Ldap\LdapException;
use Icinga\Web\Form;
use Icinga\Protocol\Ldap\LdapConnection;
use Icinga\Web\Url;

/**
 * Form class for adding/modifying ldap resources
 */
class LdapResourceForm extends Form
{
    /**
     * Initialize this form
     */
    public function init()
    {
        $this->setName('form_config_resource_ldap');
    }

    /**
     * Create and add elements to this form
     *
     * @param   array   $formData   The data sent by the user
     */
    public function createElements(array $formData)
    {
        $defaultPort = ! array_key_exists('encryption', $formData) || $formData['encryption'] !== LdapConnection::LDAPS
            ? 389
            : 636;

        $this->addElement(
            'text',
            'name',
            array(
                'required'      => true,
                'label'         => $this->translate('Resource Name'),
                'description'   => $this->translate('The unique name of this resource')
            )
        );
        $this->addElement(
            'text',
            'hostname',
            array(
                'required'      => true,
                'label'         => $this->translate('Host'),
                'description'   => $this->translate(
                    'The hostname or address of the LDAP server to use for authentication.'
                    . ' You can also provide multiple hosts separated by a space'
                ),
                'value'         => 'localhost'
            )
        );
        $this->addElement(
            'number',
            'port',
            array(
                'required'          => true,
                'preserveDefault'   => true,
                'label'             => $this->translate('Port'),
                'description'       => $this->translate('The port of the LDAP server to use for authentication'),
                'value'             => $defaultPort
            )
        );
        $this->addElement(
            'select',
            'encryption',
            array(
                'required'      => true,
                'autosubmit'    => true,
                'label'         => $this->translate('Encryption'),
                'description'   => $this->translate(
                    'Whether to encrypt communication. Choose STARTTLS or LDAPS for encrypted communication or'
                    . ' none for unencrypted communication'
                ),
                'multiOptions'  => array(
                    'none'                      => $this->translate('None', 'resource.ldap.encryption'),
                    LdapConnection::STARTTLS    => 'STARTTLS',
                    LdapConnection::LDAPS       => 'LDAPS'
                )
            )
        );

        $this->addElement(
            'text',
            'root_dn',
            array(
                'required'      => true,
                'label'         => $this->translate('Root DN'),
                'description'   => $this->translate(
                    'Only the root and its child nodes will be accessible on this resource.'
                )
            )
        );
        $this->addElement(
            'text',
            'bind_dn',
            array(
                'label'         => $this->translate('Bind DN'),
                'description'   => $this->translate(
                    'The user dn to use for querying the ldap server. Leave the dn and password empty for attempting'
                    . ' an anonymous bind'
                )
            )
        );
        $this->addElement(
            'password',
            'bind_pw',
            array(
                'renderPassword'    => true,
                'label'             => $this->translate('Bind Password'),
                'description'       => $this->translate('The password to use for querying the ldap server')
            )
        );

        $this->addElement(
            'text',
            'domains',
            array(
                'label'         => $this->translate('Domains'),
                'description'   => $this->translate(
                    'The comma-separated domains the LDAP server is responsible for.'
                ),
                'decorators'    => array(
                    array('Label', array('tag'=>'span', 'separator' => '', 'class' => 'control-label')),
                    array('Help', array('placement' => 'APPEND')),
                    array(array('labelWrap' => 'HtmlTag'), array('tag' => 'div', 'class' => 'control-label-group')),
                    array('ViewHelper', array('separator' => '')),
                    array('Errors', array('separator' => '')),
                    array('HtmlTag', array('tag' => 'div', 'class' => 'control-group', 'openOnly' => true))
                )
            )
        );

        $this->addElement(
            'button',
            'btn_discover_domains',
            array(
                'escape'        => false,
                'ignore'        => true,
                'label'         => $this->getView()->icon('binoculars'),
                'type'          => 'submit',
                'title'         => $this->translate('Discover the domains'),
                'value'         => $this->translate('Discover'),
                'decorators'    => array(
                    array('Help', array('placement' => 'APPEND')),
                    array('ViewHelper', array('separator' => '')),
                    array('Errors', array('separator' => '')),
                    array('HtmlTag', array('tag' => 'div', 'class' => 'control-group', 'closeOnly' => true))
                )
            )
        );

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isValidPartial(array $formData)
    {
        if (! parent::isValidPartial($formData)) {
            return false;
        }

        if (isset($formData['btn_discover_domains'])) {
            $config = new ConfigObject(array_merge($formData, array('type' => 'ldap')));
            try {
                $domains = $this->discoverDomains(ResourceFactory::createResource($config));
                $e = null;
            } catch (LdapException $e) {
                // May be an authentication error because of the dummy password

                $resource = Url::fromRequest()->getParam('resource');
                if ($resource !== null) {
                    $bindPw = Config::app('resources')->get($resource, 'bind_pw');
                    if ($bindPw !== null) {
                        $config->bind_pw = $bindPw;
                        try {
                            $domains = $this->discoverDomains(ResourceFactory::createResource($config));
                            $e = null;
                        } catch (LdapException $e) {
                        }
                    }
                }
            }

            if ($e === null) {
                $this->_elements['domains']->setValue(implode(',', $domains));
            } else {
                $this->_elements['btn_discover_domains']->addError($e->getMessage());
                return false;
            }
        }

        return true;
    }

    /**
     * Discover the domains the LDAP server is responsible for
     *
     * @param   LdapConnection  $connection
     *
     * @return  string[]
     */
    protected function discoverDomains(LdapConnection $connection)
    {
        $domains = array();
        $cap = LdapCapabilities::discoverCapabilities($connection);

        if ($cap->isActiveDirectory()) {
            $netBiosName = $this->discoverADConfigOption($connection, 'nETBIOSName', $cap);
            if ($netBiosName !== null) {
                $domains[] = $netBiosName;
            }
        }

        $fqdn = $this->defaultNamingContextToFQDN($cap);
        if ($fqdn !== null) {
            $domains[] = $fqdn;
        }

        return $domains;
    }

    /**
     * Get the default naming context as FQDN
     *
     * @param   LdapCapabilities    $cap
     *
     * @return  string|null
     */
    protected function defaultNamingContextToFQDN(LdapCapabilities $cap)
    {
        $defaultNamingContext = $cap->getDefaultNamingContext();
        if ($defaultNamingContext !== null) {
            $validationMatches = array();
            if (preg_match('/\bdc=[^,]+(?:,dc=[^,]+)*$/', strtolower($defaultNamingContext), $validationMatches)) {
                $splitMatches = array();
                preg_match_all('/dc=([^,]+)/', $validationMatches[0], $splitMatches);
                return implode('.', $splitMatches[1]);
            }
        }
    }

    /**
     * Discover an AD-specific configuration option (e.g. nETBIOSName)
     *
     * @param   LdapConnection          $connection     A connection to the AD
     * @param   string                  $option         The option to discover
     * @param   LdapCapabilities|null   $cap            The AD's capabilities if already discovered
     *
     * @return  string|null                             The value of the option
     */
    protected function discoverADConfigOption(LdapConnection $connection, $option, LdapCapabilities $cap = null)
    {
        if ($cap === null) {
            $cap = LdapCapabilities::discoverCapabilities($connection);
        }

        $configurationNamingContext = $cap->getConfigurationNamingContext();
        $defaultNamingContext = $cap->getDefaultNamingContext();
        if (!($configurationNamingContext === null || $defaultNamingContext === null)) {
            $value = $connection->select()
                ->setBase('CN=Partitions,' . $configurationNamingContext)
                ->from('*', array($option))
                ->where('nCName', $defaultNamingContext)
                ->fetchOne();
            if ($value !== false) {
                return $value;
            }
        }
    }
}
