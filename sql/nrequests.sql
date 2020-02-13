DROP TABLE IF EXISTS `nrequests`;
CREATE TABLE IF NOT EXISTS `nrequests` (
    `autoid` INT(11) NOT NULL AUTO_INCREMENT,
    `id` VARCHAR(50) NOT NULL,
    `entityId` varchar(32),
    `outerId` VARCHAR(50) DEFAULT NULL,
    `json` VARCHAR(10000) NOT NULL,
    `watermark` VARCHAR(13) NOT NULL,
    `delivered` VARCHAR(13) DEFAULT NULL,
    `read` VARCHAR(13) DEFAULT NULL,
    `signature` VARCHAR(64) DEFAULT "",
    `updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`autoid`),
    UNIQUE (`id`,`entityId`),
    UNIQUE (`outerId`),
    INDEX (`entityId`),
    INDEX (`signature`)
)
DEFAULT CHARSET 'utf8mb4' 
COLLATE 'utf8mb4_unicode_ci'
ENGINE=InnoDB
;