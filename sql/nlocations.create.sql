DROP TABLE IF EXISTS `nlocations`;
CREATE TABLE IF NOT EXISTS `nlocations` (
    `autoid` INT(11) NOT NULL AUTO_INCREMENT,
    `entityId` varchar(32),
    `latitude` VARCHAR(10) NOT NULL,
    `longitude` VARCHAR(10) NOT NULL,
    `updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`autoid`),
    INDEX (`entityId`)
)
DEFAULT CHARSET 'utf8mb4' 
COLLATE 'utf8mb4_unicode_ci'
ENGINE=InnoDB
;