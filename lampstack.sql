/*  run this on a NEW server to create the database (replace CHANGE_ME below first)
mysql < /root/lampstack.sql;
mysql -u LampStackUser -p -D LampStackDB

    The droplet's existing database is upgraded with  php database/upgrade.php  instead,
    which keeps all current users and contacts.
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
    `Password` VARCHAR(255) NOT NULL,
    `Role` ENUM('User','Admin') NOT NULL DEFAULT 'User',
    `IsDisabled` TINYINT(1) NOT NULL DEFAULT 0,
    `MustChangePassword` TINYINT(1) NOT NULL DEFAULT 0,
    `SessionVersion` INT NOT NULL DEFAULT 0,
    `FailedLoginCount` INT NOT NULL DEFAULT 0,
    `LockedUntil` DATETIME NULL,
    `LastLoginAt` DATETIME NULL,
    `DateCreated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `DateUpdated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`ID`),
    UNIQUE INDEX `uq_users_login` (`Login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Contacts` (
    `ID` INT NOT NULL AUTO_INCREMENT,
    `FirstName` VARCHAR(50) NOT NULL,
    `LastName` VARCHAR(50) NOT NULL,
    `Cell` VARCHAR(50) NULL,
    `Email` VARCHAR(254) NULL,
    `Company` VARCHAR(100) NULL,
    `JobTitle` VARCHAR(100) NULL,
    `Address` VARCHAR(255) NULL,
    `Birthday` DATE NULL,
    `Notes` TEXT NULL,
    `PhotoUpdatedAt` DATETIME NULL,
    `UserID` INT NOT NULL,
    `DateCreated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `DateUpdated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`ID`),
    FOREIGN KEY (`UserID`) REFERENCES Users(`ID`) ON DELETE CASCADE,
    INDEX `idx_contacts_userid` (`UserID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One photo per contact, stored in the database so uploads never sit in a web-reachable folder.
CREATE TABLE IF NOT EXISTS `ContactPhotos` (
    `ContactID` INT NOT NULL,
    `MimeType` VARCHAR(30) NOT NULL,
    `Data` MEDIUMBLOB NOT NULL,
    PRIMARY KEY (`ContactID`),
    FOREIGN KEY (`ContactID`) REFERENCES Contacts(`ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Each user's own labels ("Family", "Work"...) and which contacts carry them.
CREATE TABLE IF NOT EXISTS `Tags` (
    `ID` INT NOT NULL AUTO_INCREMENT,
    `UserID` INT NOT NULL,
    `Name` VARCHAR(40) NOT NULL,
    `Color` CHAR(7) NOT NULL DEFAULT '#ec4899',
    `DateCreated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`ID`),
    UNIQUE INDEX `uq_tags_user_name` (`UserID`, `Name`),
    FOREIGN KEY (`UserID`) REFERENCES Users(`ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ContactTags` (
    `ContactID` INT NOT NULL,
    `TagID` INT NOT NULL,
    PRIMARY KEY (`ContactID`, `TagID`),
    FOREIGN KEY (`ContactID`) REFERENCES Contacts(`ID`) ON DELETE CASCADE,
    FOREIGN KEY (`TagID`) REFERENCES Tags(`ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Favorites are per user, so they follow you between devices and work on shared contacts too.
CREATE TABLE IF NOT EXISTS `ContactFavorites` (
    `UserID` INT NOT NULL,
    `ContactID` INT NOT NULL,
    `DateCreated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`UserID`, `ContactID`),
    FOREIGN KEY (`UserID`) REFERENCES Users(`ID`) ON DELETE CASCADE,
    FOREIGN KEY (`ContactID`) REFERENCES Contacts(`ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A contact shared with another user, either 'view' (read only) or 'edit'.
CREATE TABLE IF NOT EXISTS `ContactShares` (
    `ContactID` INT NOT NULL,
    `SharedWithUserID` INT NOT NULL,
    `Permission` ENUM('view','edit') NOT NULL DEFAULT 'view',
    `SharedByUserID` INT NULL,
    `DateCreated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`ContactID`, `SharedWithUserID`),
    FOREIGN KEY (`ContactID`) REFERENCES Contacts(`ID`) ON DELETE CASCADE,
    FOREIGN KEY (`SharedWithUserID`) REFERENCES Users(`ID`) ON DELETE CASCADE,
    FOREIGN KEY (`SharedByUserID`) REFERENCES Users(`ID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Who did what, when: the dashboard feed and the admin audit log. Names are copied in
-- so entries still read correctly after a user is deleted.
CREATE TABLE IF NOT EXISTS `ActivityLog` (
    `ID` BIGINT NOT NULL AUTO_INCREMENT,
    `ActorUserID` INT NULL,
    `TargetUserID` INT NULL,
    `ActorName` VARCHAR(101) NULL,
    `TargetName` VARCHAR(101) NULL,
    `Action` VARCHAR(50) NOT NULL,
    `EntityType` VARCHAR(30) NULL,
    `EntityID` INT NULL,
    `Summary` VARCHAR(255) NOT NULL,
    `IPAddress` VARCHAR(45) NULL,
    `DateCreated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`ID`),
    INDEX `idx_activity_actor` (`ActorUserID`, `DateCreated`),
    INDEX `idx_activity_target` (`TargetUserID`, `DateCreated`),
    INDEX `idx_activity_date` (`DateCreated`),
    FOREIGN KEY (`ActorUserID`) REFERENCES Users(`ID`) ON DELETE SET NULL,
    FOREIGN KEY (`TargetUserID`) REFERENCES Users(`ID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Signed-in sessions (PHP stores them here instead of in temporary files).
CREATE TABLE IF NOT EXISTS `Sessions` (
    `ID` VARCHAR(128) NOT NULL,
    `UserID` INT NULL,
    `Data` BLOB NOT NULL,
    `LastActivity` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`ID`),
    INDEX `idx_sessions_activity` (`LastActivity`),
    FOREIGN KEY (`UserID`) REFERENCES Users(`ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Demo accounts. Passwords are stored as bcrypt hashes (what password_hash() produces):
--   admin / admin123 (Admin; must choose a new password at first sign-in)
--   HarryP / Hogwarts123    Spiderman / Spiderman123
INSERT INTO `Users` (`FirstName`, `LastName`, `Login`, `Password`, `Role`, `MustChangePassword`) VALUES
('Firstname','Lastname','admin','$2y$12$sH60B0iAGqYQ4xM/SVzfgu4csl/m4ogQ8704LHrvFI3Z/r6wYePWW','Admin', 1),
('Harry', 'Potter', 'HarryP', '$2y$12$hplXVCAua46f8bfe1qMhNuGjHxMbUX4vgyU1G8ETWOrQZQMmzuYum','User', 0),
('Peter', 'Parker', 'Spiderman', '$2y$12$OYzgkU4Eoo9Bh4gIqyITG.JqRcbTLSbA30JHCaQMMRomlZ131SsJS','User', 0);

INSERT INTO `Contacts` (`FirstName`, `LastName`, `Cell`, `Email`, `UserID`) VALUES
('Abby', 'Anderson', '(345) 234-4574', 'abbyAnderson@example.com', 1),
('Ben', 'Brown', '(976) 395-9768', 'bennieB@example.com', 1),
('Chloe', 'Carter', '(467) 864-7732', 'ccgirlie@example.com', 1),
('Danny', 'Evans', '(348) 284-9733', 'dantheman@example.com', 1);

INSERT INTO `Contacts` (`FirstName`, `LastName`, `Cell`, `Email`, `UserID`) VALUES
('Ethan', 'Edward', '(907) 887-8645', 'ethanEdwards@example.com', 2),
('Felix', 'Fanny', '(654) 676-6767', 'feFanny@example.com', 2),
('Grace', 'Gibs', '(665) 554-4554', 'ggisme@example.com', 2);

-- The MySQL account the website uses. After running this file, set a real password in the
-- mysql shell (ALTER USER 'LampStackUser'@'localhost' IDENTIFIED BY '...';) and put it in api/db.php.
-- Don't write the real password into this file: it lives in the website folder.
-- It only accepts connections from this server (the old '%' copy allowed logins from anywhere).
CREATE USER IF NOT EXISTS 'LampStackUser'@'localhost' IDENTIFIED BY 'CHANGE_ME';
GRANT ALL PRIVILEGES ON `LampStackDB`.* TO 'LampStackUser'@'localhost';
FLUSH PRIVILEGES;
