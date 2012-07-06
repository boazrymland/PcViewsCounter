CREATE TABLE `page_views_bookeeping` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `result_id` int(11) unsigned NOT NULL,
  `ip_address` varbinary(16) DEFAULT NULL,
  `user_id` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `single_user_and_stat` (`result_id`,`user_id`),
  UNIQUE KEY `single_ipaddress_and_stat` (`result_id`,`ip_address`),
  KEY `result_id` (`result_id`),
  CONSTRAINT `fk_stats_id` FOREIGN KEY (`result_id`) REFERENCES `page_views_stats` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `page_views_stats` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `model_name` varchar(128) NOT NULL,
  `model_id` int(11) unsigned NOT NULL,
  `count_uniq` int(11) unsigned DEFAULT '0',
  `count_non_uniq` int(11) unsigned DEFAULT '0',
  `created_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_on` timestamp NULL DEFAULT NULL,
  `lock_version` int(11) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `single_model_object_record` (`model_name`,`model_id`),
  KEY `model_id` (`model_id`),
  KEY `model_name` (`model_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
