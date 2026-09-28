<?php
/**
 * Brings an existing database up to date with lampstack.sql, keeping all data.
 * Run it on the droplet after pulling new code:
 *
 *     php database/upgrade.php
 *
 * Safe to run any number of times: every step first checks whether it's already done.
 * It uses the connection settings in api/db.php.
 *
 * What it does:
 *   Users     make Password long enough for bcrypt, make sure Role/IsDisabled exist (they
 *             were added by hand on the droplet), add the new account-security columns,
 *             make usernames unique
 *   Contacts  make phone/email optional, add company, job title, address, birthday, notes, photo
 *   New tables (ContactPhotos, Tags, ContactTags, ContactFavorites, ContactShares,
 *             ActivityLog, Sessions) are created from their definitions in lampstack.sql
 */

if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit();
}

$_SERVER["REQUEST_METHOD"] = "CLI"; // db.php checks this for web requests
require __DIR__ . "/../api/db.php";

function step(string $message): void
{
    echo "  - $message\n";
}

function columnInfo(PDO $pdo, string $table, string $column)
{
    $stmt = $pdo->prepare("SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return $stmt->fetch();
}

function indexExists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?");
    $stmt->execute([$table, $index]);
    return (int)$stmt->fetchColumn() > 0;
}

function addColumn(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!columnInfo($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        step("$table: added $column");
    }
}

echo "Upgrading database " . $pdo->query("SELECT DATABASE()")->fetchColumn() . "\n";

// ---------------------------------------------------------------------------
// Users
// ---------------------------------------------------------------------------

$password = columnInfo($pdo, "Users", "Password");
if ((int)$password["CHARACTER_MAXIMUM_LENGTH"] < 255) {
    $pdo->exec("ALTER TABLE `Users` MODIFY `Password` VARCHAR(255) NOT NULL");
    step("Users: Password widened to 255 characters (bcrypt hashes are 60)");
}

// Role and IsDisabled were added by hand on the droplet; their type may differ
// (text, a number, NULLs...). Normalise them to what lampstack.sql defines.
addColumn($pdo, "Users", "Role", "VARCHAR(20) NULL AFTER `Password`");
if (columnInfo($pdo, "Users", "Role")["DATA_TYPE"] !== "enum") {
    $pdo->exec("ALTER TABLE `Users` MODIFY `Role` VARCHAR(20) NULL");
    $pdo->exec("UPDATE `Users` SET `Role` = CASE WHEN LOWER(TRIM(`Role`)) IN ('admin', '1', 'true') THEN 'Admin' ELSE 'User' END");
    $pdo->exec("ALTER TABLE `Users` MODIFY `Role` ENUM('User','Admin') NOT NULL DEFAULT 'User'");
    step("Users: Role is now 'User' or 'Admin'");
}

addColumn($pdo, "Users", "IsDisabled", "TINYINT(1) NULL AFTER `Role`");
$disabled = columnInfo($pdo, "Users", "IsDisabled");
if ($disabled["DATA_TYPE"] !== "tinyint" || $disabled["IS_NULLABLE"] === "YES") {
    // Could be a number, text such as 'true'/'false', or NULL: go through text to handle all of them.
    $pdo->exec("ALTER TABLE `Users` MODIFY `IsDisabled` VARCHAR(20) NULL");
    $pdo->exec("UPDATE `Users` SET `IsDisabled` = CASE WHEN LOWER(TRIM(`IsDisabled`)) IN ('1', 'true', 'yes') THEN '1' ELSE '0' END");
    $pdo->exec("ALTER TABLE `Users` MODIFY `IsDisabled` TINYINT(1) NOT NULL DEFAULT 0");
    step("Users: IsDisabled is now 0 or 1");
}

addColumn($pdo, "Users", "MustChangePassword", "TINYINT(1) NOT NULL DEFAULT 0 AFTER `IsDisabled`");
addColumn($pdo, "Users", "SessionVersion", "INT NOT NULL DEFAULT 0 AFTER `MustChangePassword`");
addColumn($pdo, "Users", "FailedLoginCount", "INT NOT NULL DEFAULT 0 AFTER `SessionVersion`");
addColumn($pdo, "Users", "LockedUntil", "DATETIME NULL AFTER `FailedLoginCount`");
addColumn($pdo, "Users", "LastLoginAt", "DATETIME NULL AFTER `LockedUntil`");

if (!indexExists($pdo, "Users", "uq_users_login")) {
    $duplicates = $pdo->query("SELECT Login FROM Users GROUP BY Login HAVING COUNT(*) > 1")->fetchAll(PDO::FETCH_COLUMN);
    if ($duplicates) {
        fwrite(STDERR, "\nStopped: these usernames exist more than once (upper/lower case counts as the same): "
            . implode(", ", $duplicates) . ".\nRename or delete the extra accounts, then run this again.\n");
        exit(1);
    }
    $pdo->exec("ALTER TABLE `Users` ADD UNIQUE INDEX `uq_users_login` (`Login`)");
    step("Users: usernames are now unique");
    if (indexExists($pdo, "Users", "idx_users_login")) {
        $pdo->exec("ALTER TABLE `Users` DROP INDEX `idx_users_login`"); // the unique index replaces it
    }
}

// ---------------------------------------------------------------------------
// Contacts
// ---------------------------------------------------------------------------

if (columnInfo($pdo, "Contacts", "Cell")["IS_NULLABLE"] === "NO") {
    $pdo->exec("ALTER TABLE `Contacts` MODIFY `Cell` VARCHAR(50) NULL");
    step("Contacts: phone is now optional");
}
$email = columnInfo($pdo, "Contacts", "Email");
if ($email["IS_NULLABLE"] === "NO" || (int)$email["CHARACTER_MAXIMUM_LENGTH"] < 254) {
    $pdo->exec("ALTER TABLE `Contacts` MODIFY `Email` VARCHAR(254) NULL");
    step("Contacts: email is now optional and up to 254 characters");
}
addColumn($pdo, "Contacts", "Company", "VARCHAR(100) NULL AFTER `Email`");
addColumn($pdo, "Contacts", "JobTitle", "VARCHAR(100) NULL AFTER `Company`");
addColumn($pdo, "Contacts", "Address", "VARCHAR(255) NULL AFTER `JobTitle`");
addColumn($pdo, "Contacts", "Birthday", "DATE NULL AFTER `Address`");
addColumn($pdo, "Contacts", "Notes", "TEXT NULL AFTER `Birthday`");
addColumn($pdo, "Contacts", "PhotoUpdatedAt", "DATETIME NULL AFTER `Notes`");

// ---------------------------------------------------------------------------
// New tables: taken straight from lampstack.sql so there's one definition of each.
// ---------------------------------------------------------------------------

// Each table in lampstack.sql must keep the "CREATE TABLE IF NOT EXISTS `Name` (...) ENGINE=...;" form.
$sql = file_get_contents(__DIR__ . "/../lampstack.sql");
preg_match_all("/CREATE TABLE IF NOT EXISTS `(\\w+)`.*?\\)\\s*ENGINE=[^;]+;/s", $sql, $tables, PREG_SET_ORDER);
foreach ($tables as $table) {
    $exists = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $exists->execute([$table[1]]);
    if ((int)$exists->fetchColumn() === 0) {
        $pdo->exec($table[0]);
        step("created table {$table[1]}");
    }
}

echo "Done. The database is up to date.\n";
