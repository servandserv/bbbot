DROP TABLE IF EXISTS `nchats`;
CREATE TABLE `nchats` (
  `entityId` varchar(32) NOT NULL,
  `id` varchar(50) NOT NULL,
  `context` varchar(50) NOT NULL,
  `UID` VARCHAR(100) DEFAULT NULL,
  `outerName` VARCHAR(1000) DEFAULT NULL,
  `securityLevel` VARCHAR(2) DEFAULT NULL,
  `firstName` varchar(100) DEFAULT '',
  `lastName` varchar(100) DEFAULT '',
  `phoneNumber` varchar(15) DEFAULT NULL,
  `latitude` varchar(10) DEFAULT NULL,
  `longitude` varchar(10) DEFAULT NULL,
  `type` varchar(10) NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата создания',
  PRIMARY KEY (`entityId`)
) 
ENGINE=InnoDB 
DEFAULT CHARSET 'utf8mb4' 
COLLATE 'utf8mb4_unicode_ci'
;