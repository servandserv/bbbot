DROP TABLE IF EXISTS `ncommands`;
CREATE TABLE IF NOT EXISTS `ncommands` (
    `autoid` INT(11) NOT NULL AUTO_INCREMENT,
    `entityId` varchar(32) COMMENT 'Уникальный идентификатор пользователя - ключ по идентификатору чата и типу мессенджера',
    `command` VARCHAR(100) DEFAULT NULL  COMMENT 'Наименование команды',
    `alias` VARCHAR(100) DEFAULT NULL COMMENT 'То же',
    `arguments` VARCHAR(230) DEFAULT NULL COMMENT 'Параметры вызова',
    `status` VARCHAR(1) DEFAULT '0',
    `updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`autoid`),
    INDEX (`entityId`)
)
DEFAULT CHARSET 'utf8mb4' 
COLLATE 'utf8mb4_unicode_ci'
ENGINE=InnoDB
;