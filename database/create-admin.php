<?php
/**
 * Create an admin account, or promote an existing user to admin and reset their password.
 * Useful for the first admin, or if everyone gets locked out:
 *
 *     php database/create-admin.php <login> <password> [FirstName] [LastName]
 */

if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit();
}

$_SERVER["REQUEST_METHOD"] = "CLI";
require __DIR__ . "/../api/db.php";

[, $login, $password, $first, $last] = array_pad($argv, 5, null);

if (!$login || !$password) {
    fwrite(STDERR, "Usage: php database/create-admin.php <login> <password> [FirstName] [LastName]\n");
    exit(1);
}
if (strlen($password) < 8) {
    fwrite(STDERR, "The password must be at least 8 characters.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_BCRYPT);
$find = $pdo->prepare("SELECT ID FROM Users WHERE Login = ?");
$find->execute([$login]);
$id = $find->fetchColumn();

if ($id) {
    $pdo->prepare("UPDATE Users SET Role = 'Admin', Password = ?, IsDisabled = 0, FailedLoginCount = 0, LockedUntil = NULL,
        MustChangePassword = 0, SessionVersion = SessionVersion + 1 WHERE ID = ?")->execute([$hash, $id]);
    echo "Promoted '$login' to Admin and set the new password.\n";
} else {
    $pdo->prepare("INSERT INTO Users (FirstName, LastName, Login, Password, Role) VALUES (?, ?, ?, ?, 'Admin')")
        ->execute([$first ?: "Site", $last ?: "Admin", $login, $hash]);
    echo "Created admin '$login'.\n";
}
