<?php
require_once "common.php";
requireMethod("GET");
$admin = requireAdmin(); // checked from the session, not from an adminId sent by the browser

$term = inputText("term");

try {
    // Extra columns for the admin page: status, lockout, last sign-in, number of contacts.
    $columns = "ID, FirstName, LastName, Login, Role, IsDisabled, MustChangePassword, LockedUntil,
        (LockedUntil IS NOT NULL AND LockedUntil > NOW()) AS IsLocked, LastLoginAt, DateCreated,
        (SELECT COUNT(*) FROM Contacts c WHERE c.UserID = Users.ID) AS ContactCount";

    if ($term !== "") {
        $like = likePattern($term);
        $stmt = $pdo->prepare("SELECT $columns FROM Users
            WHERE FirstName LIKE ? OR LastName LIKE ? OR Login LIKE ?");
        $stmt->execute([$like, $like, $like]);
    } else {
        $stmt = $pdo->prepare("SELECT $columns FROM Users");
        $stmt->execute();
    }

    $users = $stmt->fetchAll();
    http_response_code(200);
    echo json_encode($users);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Search failed"]);
}
?>
