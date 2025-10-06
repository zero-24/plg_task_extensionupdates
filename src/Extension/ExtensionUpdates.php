<?php

/**
 * ExtensionUpdates Task Plugin
 *
 * @copyright  Copyright (C) 2024 Tobias Zulauf All rights reserved.
 * @license    http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License Version 2 or later
 */

namespace Joomla\Plugin\Task\ExtensionUpdates\Extension;

use Joomla\CMS\Access\Access;
use Joomla\CMS\Mail\Exception\MailDisabledException;
use Joomla\CMS\Mail\MailTemplate;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Table\Asset;
use Joomla\CMS\Updater\Updater;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Version;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\SubscriberInterface;
use PHPMailer\PHPMailer\Exception as phpMailerException;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * A task plugin. Checks for extension Updates and sends an eMail once one has been found
 *
 * @since 1.0.0
 */
final class ExtensionUpdates extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;
    use TaskPluginTrait;

    /**
     * @var string[]
     * @since 1.0.0
     */
    private const TASKS_MAP = [
        'update.extensions' => [
            'langConstPrefix' => 'PLG_TASK_EXTENSIONUPDATES_SEND',
            'method'          => 'checkExtensionUpdates',
            'form'            => 'sendForm',
        ],
    ];

    /**
     * @var boolean
     * @since 1.0.0
     */
    protected $autoloadLanguage = true;

    /**
     * @inheritDoc
     *
     * @return string[]
     *
     * @since 1.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList'    => 'advertiseRoutines',
            'onExecuteTask'        => 'standardRoutineHandler',
            'onContentPrepareForm' => 'enhanceTaskItemForm',
        ];
    }

    /**
     * Method to send the update notification.
     *
     * @param   ExecuteTaskEvent  $event  The `onExecuteTask` event.
     *
     * @return integer  The routine exit code.
     *
     * @since  1.0.0
     * @throws \Exception
     */
    private function checkExtensionUpdates(ExecuteTaskEvent $event): int
    {
        // Load the parameters.
        $specificEmail  = $event->getArgument('params')->email ?? '';
        $forcedLanguage = $event->getArgument('params')->language_override ?? '';

        $eids = $this->getNoneCoreExtensionIdsWithUpdateServer();

        // When there are no extensions our job is done.
        if (empty($eids))
        {
            return Status::OK;
        }

        // Get any available updates
        $updater = Updater::getInstance();

        $results = $updater->findUpdates($eids, 0);

        // If there are no updates our job is done. We need BOTH this check AND the one below.
        if (!$results) {
            return Status::OK;
        }

        // Get the update model and retrieve the Joomla! core updates
        $installerModel = $this->getApplication()->bootComponent('com_installer')
            ->getMVCFactory()->createModel('Update', 'Administrator', ['ignore_request' => true]);

        $updates = $installerModel->getItems();

        // If there are no updates we don't have to notify anyone about anything. This is NOT a duplicate check.
        if (empty($updates)) {
            return Status::OK;
        }

        // If we're here, we have updates. First, get a link to the Joomla! Installer component.
        $baseURL  = Uri::base();
        $baseURL  = rtrim($baseURL, '/');
        $baseURL .= (substr($baseURL, -13) !== 'administrator') ? '/administrator/' : '/';
        $baseURL .= 'index.php?option=com_installer&view=update';
        $uri      = new Uri($baseURL);

        /**
         * Some third party security solutions require a secret query parameter to allow log in to the administrator
         * backend of the site. The link generated above will be invalid and could probably block the user out of their
         * site, confusing them (they can't understand the third party security solution is not part of Joomla! proper).
         * So, we're calling the onBuildAdministratorLoginURL system plugin event to let these third party solutions
         * add any necessary secret query parameters to the URL. The plugins are supposed to have a method with the
         * signature:
         *
         * public function onBuildAdministratorLoginURL(Uri &$uri);
         *
         * The plugins should modify the $uri object directly and return null.
         */
        $this->getApplication()->triggerEvent('onBuildAdministratorLoginURL', [&$uri]);

        // Let's find out the email addresses to notify
        $superUsers = [];

        if (!empty($specificEmail)) {
            $superUsers = $this->getSuperUsers($specificEmail);
        }

        if (empty($superUsers)) {
            $superUsers = $this->getSuperUsers();
        }

        if (empty($superUsers)) {
            return Status::KNOCKOUT;
        }

        /*
         * Load the appropriate language. We try to load English (UK), the current user's language and the forced
         * language preference, in this order. This ensures that we'll never end up with untranslated strings in the
         * update email which would make Joomla! seem bad. So, please, if you don't fully understand what the
         * following code does DO NOT TOUCH IT. It makes the difference between a hobbyist CMS and a professional
         * solution!
         */
        $jLanguage = $this->getApplication()->getLanguage();
        $jLanguage->load('plg_task_extensionupdates', JPATH_ADMINISTRATOR, 'en-GB', true, true);
        $jLanguage->load('plg_task_extensionupdates', JPATH_ADMINISTRATOR, null, true, false);

        // Then try loading the preferred (forced) language
        if (!empty($forcedLanguage)) {
            $jLanguage->load('plg_task_extensionupdates', JPATH_ADMINISTRATOR, $forcedLanguage, true, false);
        }

        foreach ($updates as $updateId => $updateValue) {

            // Replace merge codes with their values
            $substitutions = [
                'newversion'    => $updateValue->version,
                'curversion'    => $updateValue->current_version,
                'sitename'      => $this->getApplication()->get('sitename'),
                'url'           => Uri::base(),
                'updatelink'    => $uri->toString(),
                'extensiontype' => $updateValue->type,
                'extensionname' => $updateValue->name,
            ];

            // Send the emails to the Super Users
            foreach ($superUsers as $superUser) {
                try {
                    $mailer = new MailTemplate('plg_task_extensionupdates.extension_update', $jLanguage->getTag());
                    $mailer->addRecipient($superUser->email);
                    $mailer->addTemplateData($substitutions);
                    $mailer->send();
                } catch (MailDisabledException | phpMailerException $exception) {
                    try {
                        $this->logTask($jLanguage->_($exception->getMessage()));
                    } catch (\RuntimeException $exception) {
                        return Status::KNOCKOUT;
                    }
                }
            }
        }

        $this->logTask('ExtensionUpdates end');

        return Status::OK;
    }

    /**
     * Returns the Super Users email information. If you provide a comma separated $email list
     * we will check that these emails do belong to Super Users and that they have not blocked
     * system emails.
     *
     * @param   null|string  $email  A list of Super Users to email
     *
     * @return  array  The list of Super User emails
     *
     * @since   3.5
     */
    private function getSuperUsers($email = null)
    {
        $db     = $this->getDatabase();
        $emails = [];

        // Convert the email list to an array
        if (!empty($email)) {
            $temp   = explode(',', $email);

            foreach ($temp as $entry) {
                $emails[] = trim($entry);
            }

            $emails = array_unique($emails);
        }

        // Get a list of groups which have Super User privileges
        $ret = [];

        try {
            $table     = new Asset($db);
            $rootId    = $table->getRootId();
            $rules     = Access::getAssetRules($rootId)->getData();
            $rawGroups = $rules['core.admin']->getData();
            $groups    = [];

            if (empty($rawGroups)) {
                return $ret;
            }

            foreach ($rawGroups as $g => $enabled) {
                if ($enabled) {
                    $groups[] = $g;
                }
            }

            if (empty($groups)) {
                return $ret;
            }
        } catch (\Exception $exc) {
            return $ret;
        }

        // Get the user IDs of users belonging to the SA groups
        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName('user_id'))
                ->from($db->quoteName('#__user_usergroup_map'))
                ->whereIn($db->quoteName('group_id'), $groups);

            $db->setQuery($query);
            $userIDs = $db->loadColumn(0);

            if (empty($userIDs)) {
                return $ret;
            }
        } catch (\Exception $exc) {
            return $ret;
        }

        // Get the user information for the Super Administrator users
        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'username', 'email']))
                ->from($db->quoteName('#__users'))
                ->whereIn($db->quoteName('id'), $userIDs)
                ->where($db->quoteName('block') . ' = 0')
                ->where($db->quoteName('sendEmail') . ' = 1');

            if (!empty($emails)) {
                $lowerCaseEmails = array_map('strtolower', $emails);
                $query->whereIn('LOWER(' . $db->quoteName('email') . ')', $lowerCaseEmails, ParameterType::STRING);
            }

            $db->setQuery($query);
            $ret = $db->loadObjectList();
        } catch (\Exception $exc) {
            return $ret;
        }

        return $ret;
    }

    /**
     * Method to return only the extension IDs which do have an update server
     *
     * @return array  An array of eids which
     *
     * @since  1.0.1
     */
    private function getNoneCoreExtensionIdsWithUpdateServer()
    {
        // Get the updater models as there is already a method to get non core extensions.
        $joomlaUpdateModel = $this->getApplication()->bootComponent('com_joomlaupdate')
            ->getMVCFactory()->createModel('Update', 'Administrator', ['ignore_request' => true]);

        $noneCoreExtensionIds = $joomlaUpdateModel->getNonCoreExtensions();

        // Create an array of the ids we need
        foreach ($noneCoreExtensionIds as $key => $value) {
            $eids[] = $value->extension_id;
        }

        // When no extensions are installed we have nothing to check for.
        if (count($eids) === 0)
        {
            return [];
        }

        // Check the #__update_sites_extensions table with the eids
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['extension_id']))
            ->from($db->quoteName('#__update_sites_extensions'))
            ->whereIn($db->quoteName('extension_id'), $eids);

        $db->setQuery($query);

        // Retrun the result
        return $db->loadColumn();
    }
}
