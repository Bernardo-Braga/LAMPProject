<?php
require_once "common.php";
requireMethod("POST");

$data = getRequestData();

if (empty($data["login"]) || empty($data["password"]) || !is_string($data["login"]) || !is_string($data["password"])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing login or password"]);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM Users WHERE Login = ?");
    $stmt->execute([$data["login"]]);
    $user = $stmt->fetch();

    if (!$user) {
        // Check a dummy hash anyway so the response time doesn't reveal which usernames exist.
        password_verify($data["password"], '$2y$12$IMhDzRegG38LEZvv6wD0Kufi/O8DReF4uUNbjkpzeikKI94qP7Dha');
        logActivity("user.login_failed", "failed sign-in for unknown username \"" . textLimit($data["login"], 50) . "\"", "user", null, null, null);
        http_response_code(401);
        echo json_encode(["error" => "Invalid Credentials"]);
        exit();
    }

    // Too many wrong passwords recently: temporarily locked.
    if ($user["LockedUntil"] !== null && strtotime($user["LockedUntil"] . " UTC") > time()) {
        $minutes = (int) ceil((strtotime($user["LockedUntil"] . " UTC") - time()) / 60);
        http_response_code(423);
        echo json_encode(["error" => "Too many failed attempts. Try again in $minutes minute(s) or ask an admin to unlock your account."]);
        exit();
    }

    $passwordValid = password_verify($data["password"], $user["Password"]);

    if (!$passwordValid) {
        $failed = (int) $user["FailedLoginCount"] + 1;
        if ($failed >= MAX_FAILED_LOGINS) {
            $pdo->prepare("UPDATE Users SET FailedLoginCount = 0, LockedUntil = NOW() + INTERVAL " . LOCKOUT_MINUTES . " MINUTE WHERE ID = ?")
                ->execute([$user["ID"]]);
            logActivity("user.locked", "was locked out after " . MAX_FAILED_LOGINS . " failed sign-in attempts", "user", (int) $user["ID"], (int) $user["ID"], null);
        } else {
            $pdo->prepare("UPDATE Users SET FailedLoginCount = ? WHERE ID = ?")->execute([$failed, $user["ID"]]);
            logActivity("user.login_failed", "failed sign-in (wrong password)", "user", (int) $user["ID"], (int) $user["ID"], null);
        }
        http_response_code(401);
        echo json_encode(["error" => "Invalid Credentials"]);
        exit();
    }

    if (!empty($user["IsDisabled"])) {
        http_response_code(403);
        echo json_encode(["error" => "Account suspended"]);
        exit();
    }

    // Upgrade the stored hash if PHP's recommended algorithm or cost has changed.
    if (password_needs_rehash($user["Password"], PASSWORD_DEFAULT)) {
        $pdo->prepare("UPDATE Users SET Password = ? WHERE ID = ?")
            ->execute([password_hash($data["password"], PASSWORD_DEFAULT), $user["ID"]]);
    }
    $pdo->prepare("UPDATE Users SET FailedLoginCount = 0, LockedUntil = NULL, LastLoginAt = NOW() WHERE ID = ?")
        ->execute([$user["ID"]]);
    $user["LastLoginAt"] = gmdate("Y-m-d H:i:s");

    // Remember who is signed in on the server; the browser only gets a session cookie.
    signIn($user);
    logActivity("user.login", "signed in", "user", (int) $user["ID"]);

    http_response_code(200);
    echo json_encode(userJson($user)); // id, firstName, lastName, role, csrfToken, ... "error": ""
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database query error"]);
}
?>
