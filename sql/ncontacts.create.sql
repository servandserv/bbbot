DROP TABLE IF EXISTS `ncontacts`;
CREATE TABLE IF NOT EXISTS `ncontacts` (
    `autoid` INT(11) NOT NULL AUTO_INCREMENT,
    `entityId` varchar(32),
    `phoneNumber` VARCHAR(15) NOT NULL,
    `updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`autoid`),
    UNIQUE (`entityId`, `phoneNumber`)
)
DEFAULT CHARSET 'utf8mb4' 
COLLATE 'utf8mb4_unicode_ci'
ENGINE=InnoDB
;