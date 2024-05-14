-- Add mail templates
INSERT INTO `#__mail_templates` (`template_id`, `extension`, `language`, `subject`, `body`, `htmlbody`, `attachments`, `params`) VALUES
('plg_task_extensionupdates.extension_update', 'plg_task_extensionupdates', '', 'PLG_TASK_EXTENSIONUPDATES_UPDATE_MAIL_SUBJECT', 'PLG_TASK_EXTENSIONUPDATES_UPDATE_MAIL_BODY', '', '', '{"tags": ["newversion", "curversion", "sitename", "url", "updatelink", "extensiontype", "extensionname"]}');
