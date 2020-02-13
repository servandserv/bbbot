DROP TABLE IF EXISTS `ndialogs`;
CREATE TABLE IF NOT EXISTS `ndialogs` (
    `autoid` INT(11) NOT NULL AUTO_INCREMENT,
    `entityId` VARCHAR(32) NOT NULL,
    `created` varchar(10) NOT NULL,
    `dialog` VARCHAR(65000) NOT NULL,
    `updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`autoid`),
    UNIQUE (`entityId`,`created`)
)
DEFAULT CHARSET 'utf8mb4' 
COLLATE 'utf8mb4_unicode_ci'
ENGINE=InnoDB
;