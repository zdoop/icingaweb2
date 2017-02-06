<?php
/* Icinga Web 2 | (c) 2017 Icinga Development Team | GPLv2+ */

namespace Icinga\User;

use Icinga\Application\Config;
use Icinga\Data\ConfigObject;
use Icinga\Data\Db\DbConnection;
use Icinga\Data\Filter\Filter;
use Icinga\Data\ResourceFactory;
use Icinga\Exception\ConfigurationError;
use Icinga\User;
use Icinga\Web\Announcement\AnnouncementIniRepository;

/**
 * Alternate users stored in the configuration
 */
class Alternate
{
    /**
     * Alternate usernames everywhere in the configuration according to the given rules
     *
     * @param   AlternationRulesInterface   $rules
     */
    public static function allUsers(AlternationRulesInterface $rules)
    {
        static::announcementAuthors($rules);
        static::dashboardOwners($rules);
        static::preferencesOwners($rules);
        static::sharedNavigationOwners($rules);
        static::roleMemberships($rules);
    }

    /**
     * Alternate authors of announcements according to the given rules
     *
     * @param   AlternationRulesInterface   $rules
     */
    public static function announcementAuthors(AlternationRulesInterface $rules)
    {
        $repo = new AnnouncementIniRepository();

        $authors = array();
        foreach ($repo->select(array('author')) as $announcement) {
            if (! isset($authors[$announcement->author])) {
                $altered = $rules->getAltered(new User($announcement->author));
                $authors[$announcement->author] = $altered === null ? null : $altered->getUsername();
            }
        }

        foreach ($authors as $author => $update) {
            if ($update !== null) {
                $repo->update('announcement', array('author' => $update), Filter::where('author', $author));
            }
        }
    }

    /**
     * Alternate owners of dashboards according to the given rules
     *
     * @param   AlternationRulesInterface   $rules
     */
    public static function dashboardOwners(AlternationRulesInterface $rules)
    {
        static::directoryStructure(Config::resolvePath('dashboards'), $rules);
    }

    /**
     * Alternate owners of preferences according to the given rules
     *
     * @param   AlternationRulesInterface   $rules
     *
     * @throws  ConfigurationError
     */
    public static function preferencesOwners(AlternationRulesInterface $rules)
    {
        $config = Config::app();
        $type = $config->get('global', 'config_backend', 'ini');
        switch ($type) {
            case 'ini':
                static::directoryStructure(Config::resolvePath('preferences'), $rules);
                break;
            case 'db':
                $conn = ResourceFactory::create($config->get('global', 'config_resource'));
                /** @var DbConnection $conn */

                $users = array();
                foreach (
                    $conn->select()
                        ->from('icingaweb_user_preference', array('username'))
                        ->group('username')
                        ->fetchColumn()
                    as $username
                ) {
                    $altered = $rules->getAltered(new User($username));
                    if ($altered !== null) {
                        $users[$username] = $altered->getUsername();
                    }
                }

                if (! empty($users)) {
                    $conn->getDbAdapter()->beginTransaction();
                    foreach ($users as $user => $update) {
                        $conn->update(
                            'icingaweb_user_preference',
                            array('username' => $update),
                            Filter::where('username', $user)
                        );
                    }
                    $conn->getDbAdapter()->commit();
                }

                break;
            default:
                throw new ConfigurationError(
                    'Invalid configuration backend type: %s. Expected one of: ini, db',
                    $type
                );
        }
    }

    /**
     * Alternate owners of shared navigation according to the given rules
     *
     * @param   AlternationRulesInterface   $rules
     */
    public static function sharedNavigationOwners(AlternationRulesInterface $rules)
    {
        $directory = Config::resolvePath('navigation');
        if (file_exists($directory)) {
            $handle = opendir($directory);
            while (($basename = readdir($handle)) !== false) {
                if (! in_array($basename, array('.', '..'))) {
                    $config = Config::fromIni($directory . DIRECTORY_SEPARATOR . $basename);
                    foreach ($config as $options) { /** @var ConfigObject $options */
                        $owner = $options->get('owner', '');
                        if ($owner !== '') {
                            $altered = $rules->getAltered(new User($owner));
                            if ($altered !== null) {
                                $options->owner = $altered->getUsername();
                            }
                        }

                        $users = $options->get('users', '');
                        if ($users !== '') {
                            $updated = array();
                            foreach (explode(',', $users) as $user) {
                                $altered = $rules->getAltered(new User($user));
                                $updated[] = $altered === null ? $user : $altered->getUsername();
                            }
                            $options->users = implode(',', $updated);
                        }
                    }
                    $config->saveIni();
                }
            }
            closedir($handle);
        }
    }

    /**
     * Alternate members of roles according to the given rules
     *
     * @param   AlternationRulesInterface   $rules
     */
    public static function roleMemberships(AlternationRulesInterface $rules)
    {
        $config = Config::app('roles');
        foreach ($config as $options) {
            $users = $options->get('users', '');
            if ($users !== '') {
                $updated = array();
                foreach (explode(',', $users) as $user) {
                    $altered = $rules->getAltered(new User($user));
                    $updated[] = $altered === null ? $user : $altered->getUsername();
                }
                $options->users = implode(',', $updated);
            }
        }
        $config->saveIni();
    }

    /**
     * Alternate entries of a directory according to the given rules
     *
     * @param   string                      $directory
     * @param   AlternationRulesInterface   $rules
     */
    protected static function directoryStructure($directory, AlternationRulesInterface $rules)
    {
        if (file_exists($directory)) {
            $users = array();
            $handle = opendir($directory);
            while (($basename = readdir($handle)) !== false) {
                if (! in_array($basename, array('.', '..'))) {
                    $altered = $rules->getAltered(new User($basename));
                    if ($altered !== null) {
                        $users[$basename] = $altered->getUsername();
                    }
                }
            }
            closedir($handle);

            foreach ($users as $user => $update) {
                rename($directory . DIRECTORY_SEPARATOR . $user, $directory . DIRECTORY_SEPARATOR . $update);
            }
        }
    }
}
