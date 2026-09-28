<?php
require_once "contact_helpers.php";
requireMethod("POST");
$user = requireLogin();
$userId = (int)$user["ID"];

$data = getRequestData();

if (empty($data["id"]) || empty($data["firstName"]) || empty($data["lastName"])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields"]);
    exit();
}

$clean = validateFields($data, CONTACT_RULES);
$columns = contactColumns($clean);

try {
    // Allowed for the owner, or someone it was shared with as "can edit" (404 / 403 otherwise).
    $contact = requireContactAccess(requireId("id"), $userId, "edit");
    $isOwner = $contact["Access"] === "owner";

    $pdo->beginTransaction();
    $stmt = $pdo->prepare("UPDATE Contacts SET FirstName=?, LastName=?, Cell=?, Email=?, Company=?, JobTitle=?, Address=?, Birthday=?, Notes=?, DateUpdated=NOW() WHERE ID=?");
    $stmt->execute(array_merge(array_values($columns), [$contact["ID"]]));
    if ($isOwner && array_key_exists("tagIds", $data)) {
        saveContactTags((int)$contact["ID"], $userId, $clean["tagIds"]); // tags are the owner's own labels
    }
    $pdo->commit();

    // When a collaborator edits it, the owner sees that in their activity feed.
    logActivity("contact.updated", "updated contact " . $columns["FirstName"] . " " . $columns["LastName"], "contact", (int)$contact["ID"], $isOwner ? null : (int)$contact["UserID"]);

    http_response_code(200);
    echo json_encode(["contact" => loadContactJson((int)$contact["ID"], $userId), "error" => ""]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(["error" => "Update failed"]);
}
?>
