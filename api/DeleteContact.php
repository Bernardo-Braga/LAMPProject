<?php
require_once "contact_helpers.php";
requireMethod("POST");
$user = requireLogin();

$data = getRequestData();

if (empty($data["id"])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields"]);
    exit();
}

try {
    $check = $pdo->prepare("SELECT ID, FirstName, LastName FROM Contacts WHERE ID = ? AND UserID = ?");
    $check->execute([requireId("id"), $user["ID"]]);
    $contact = $check->fetch();
    if (!$contact) {
        http_response_code(403);
        echo json_encode(["error" => "Not your contact"]);
        exit();
    }

    // Its photo, tags, favorites and shares are removed with it (ON DELETE CASCADE).
    $stmt = $pdo->prepare("DELETE FROM Contacts WHERE ID = ?");
    $stmt->execute([$contact["ID"]]);
    logActivity("contact.deleted", "deleted contact " . contactName($contact), "contact", (int)$contact["ID"]);

    http_response_code(200);
    echo json_encode(["error" => ""]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Delete failed"]);
}
?>
