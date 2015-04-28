{* file to handle db changes in 4.6.beta4 during upgrade *}
-- CRM-13283
CREATE TABLE IF NOT EXISTS `civicrm_status_pref` (
     `id` int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'Unique Status Preference ID',
     `domain_id` int unsigned NOT NULL   COMMENT 'Which Domain is this Status Preference for',
     `name` varchar(255) NOT NULL   COMMENT 'Name of the status check this preference references.',
     `hush_until` date   DEFAULT NULL COMMENT 'expires ignore_severity.  NULL never hushes.',
     `ignore_severity` int unsigned   DEFAULT 1 COMMENT 'Hush messages up to and including this severity.',
     `prefs` varchar(255)    COMMENT 'These settings are per-check, and can\'t be compared across checks.',
     `check_info` varchar(255)    COMMENT 'These values are per-check, and can\'t be compared across checks.'
,
    PRIMARY KEY ( `id` )

    ,     INDEX `UI_status_pref_name`(
        name
  )

,          CONSTRAINT FK_civicrm_status_pref_domain_id FOREIGN KEY (`domain_id`) REFERENCES `civicrm_domain`(`id`)
)  ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci  ;
