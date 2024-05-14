<?php

/**
 * ExtensionUpdates Task Plugin
 *
 * @copyright  Copyright (C) 2024 Tobias Zulauf All rights reserved.
 * @license    http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License Version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Installer\InstallerScript;

/**
 * Installation class to perform additional changes during install/uninstall/update
 *
 * @since  1.0.0
 */
class plgTaskExtensionUpdatesInstallerScript extends InstallerScript
{
	/**
	 * Extension script constructor.
	 *
	 * @since  1.0.0
	 */
	public function __construct()
	{
		// Define the minumum versions to be supported.
		$this->minimumJoomla = '5.1';
		$this->minimumPhp    = '8.1';
	}
}
