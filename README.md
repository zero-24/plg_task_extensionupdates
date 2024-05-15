# ExtensionUpdates Plugin

This Joomla plugin checks for updates of extensions and sends an eMail once available, the code is based on the core plg_task_updatenotification plugin.

## Configuration

### Initial setup the plugin

- [Download the latest version of the plugin](https://github.com/zero-24/plg_task_extensionupdates/releases/latest)
- Install the plugin using `Upload & Install`
- Enable the plugin `Task - ExtensionUpdates` from the plugin manager
- Setup the new Task PLugin `System -> Scheduled Tasks -> New -> ExtensionUpdates`

Now the inital setup is completed, please make sure that the cron has been fully setup in the best cases it should use the WebCron setting.

### Update Server

Please note that my update server only supports the latest version running the latest version of Joomla and atleast PHP 8.1.
Any other plugin version I may have added to the download section don't get updates using the update server.

## Issues / Pull Requests

You have found an Issue, have a question or you would like to suggest changes regarding this extension?
[Open an issue in this repo](https://github.com/zero-24/plg_task_extensionupdates/issues/new) or submit a pull request with the proposed changes.

## Translations

You want to translate this extension to your own language? Check out my [Crowdin Page for my Extensions](https://joomla.crowdin.com/zero-24) for more details. Feel free to [open an issue here](https://github.com/zero-24/plg_task_extensionupdates/issues/new) on any question that comes up.

## Joomla! Extensions Directory (JED)

This plugin can also been found in the Joomla! Extensions Directory: [ExtensionUpdates by zero24](follows)

## Release steps

- `build/build.sh`
- `git commit -am 'prepare release ExtensionUpdates 1.0.1'`
- `git tag -s '1.0.1' -m 'ExtensionUpdates 1.0.1'`
- `git push origin --tags`
- create the release on GitHub
- `git push origin master`

## Crowdin

### Upload new strings

`crowdin upload sources`

### Download translations

`crowdin download --skip-untranslated-files --ignore-match`
