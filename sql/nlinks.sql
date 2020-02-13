DROP TABLE IF EXISTS `nlinks`;
CREATE TABLE IF NOT EXISTS `nlinks` (
    `autoid` INT(11) NOT NULL AUTO_INCREMENT,
    `entityId` VARCHAR(32) NOT NULL,
    `created` VARCHAR(10) NOT NULL,
    `expired` VARCHAR(10) NOT NULL,
    `href` VARCHAR(65000) NOT NULL,
    `size` INT,
    `name` VARCHAR(1000),
    `updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`autoid`),
    INDEX (`entityId`),
    INDEX (`expired`)
)
DEFAULT CHARSET 'utf8mb4' 
COLLATE 'utf8mb4_unicode_ci'
ENGINE=InnoDB
;