-- Update existing mail template and add the extensionid param
UPDATE `#__mail_templates`
SET `params` = '{"tags": ["newversion", "curversion", "sitename", "url", "updatelink", "extensiontype", "extensionname", "extensionid"]}'
WHERE `template_id` = 'plg_task_extensionupdates.extension_update' AND  `extension` = 'plg_task_extensionupdates';
