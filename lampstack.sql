/*  run this on droplet to create database
mysql < /root/lampstack.sql;
mysql -u LampAppUser -p'LampPassword@123!' -D LampStackDB
*/

CREATE DATABASE IF NOT EXISTS `LampStackDB`
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE `LampStackDB`;

CREATE TABLE IF NOT EXISTS `Users` (
    `ID` INT NOT NULL AUTO_INCREMENT,
    `FirstName` VARCHAR(50) NOT NULL,
    `LastName` VARCHAR(50) NOT NULL,
    `Login` VARCHAR(50) NOT NULL,
    `Password` VARCHAR(50) NOT NULL,
    `DateCreated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `DateUpdated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`ID`),
    INDEX `idx_users_login` (`Login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Contacts` (
    `ID` INT NOT NULL AUTO_INCREMENT,
    `FirstName` VARCHAR(50) NOT NULL,
    `LastName` VARCHAR(50) NOT NULL,
    `Cell` VARCHAR(50) NOT NULL,
    `Email` VARCHAR(50) NOT NULL,
    `UserID` INT NOT NULL,
    `DateCreated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `DateUpdated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`ID`),
    FOREIGN KEY (`UserID`) REFERENCES Users(`ID`) ON DELETE CASCADE,
    INDEX `idx_contacts_userid` (`UserID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `Users` (`FirstName`, `LastName`, `Login`, `Password`) VALUES
('Firstname','Lastname','admin','admin123'),
('Harry', 'Potter', 'HarryP', 'Hogwarts123'),
('Peter', 'Parker', 'Spiderman', '5832a71366768098cceb7095efb774f2');

INSERT INTO `Contacts` (`FirstName`, `LastName`, `Cell`, `Email`, `UserID`) VALUES
('Abby', 'Anderson', '(345) 234-4574', 'abbyAnderson@example.com', 1),
('Ben', 'Brown', '(976) 395-9768', 'bennieB@example.com', 1),
('Chloe', 'Carter', '(467) 864-7732', 'ccgirlie@example.com', 1),
('Danny', 'Evans', '(348) 284-9733', 'dantheman@example.com', 1);

INSERT INTO `Contacts` (`FirstName`, `LastName`, `Cell`, `Email`, `UserID`) VALUES
('Ethan', 'Edward', '(907) 887-8645', 'ethanEdwards@example.com', 2),
('Felix', 'Fanny', '(654) 676-6767', 'feFanny@example.com', 2),
('Grace', 'Gibs', '(665) 554-4554', 'ggisme@example.com', 2);

CREATE USER IF NOT EXISTS 'LampStackUser'@'localhost' IDENTIFIED BY 'LampPassword@123';
GRANT ALL PRIVILEGES ON `LampStackDB`.* TO 'LampStackUser'@'localhost';

-- Also permit remote connection if needed for Docker containerization
CREATE USER IF NOT EXISTS 'LampStackUser'@'%' IDENTIFIED BY 'LampPassword@123';
GRANT ALL PRIVILEGES ON `LampStackDB`.* TO 'LampStackUser'@'%';
FLUSH PRIVILEGES;
