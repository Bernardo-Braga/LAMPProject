<?php
require_once "contact_helpers.php";
requireMethod("POST");
$user = requireLogin(); // the owner is the signed-in user, never a userId sent by the browser

$data = getRequestData();

if (empty($data["firstName"]) || empty($data["lastName"])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields"]);
    exit();
}

// Phone, email, company, birthday... are optional but must be valid when given (422 otherwise).
$clean = validateFields($data, CONTACT_RULES);
$columns = contactColumns($clean);

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO Contacts (FirstName, LastName, Cell, Email, Company, JobTitle, Address, Birthday, Notes, UserID)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute(array_merge(array_values($columns), [$user["ID"]]));
    $newId = (int)$pdo->lastInsertId();
    saveContactTags($newId, (int)$user["ID"], $clean["tagIds"]);
    $pdo->commit();

    logActivity("contact.created", "added contact " . $columns["FirstName"] . " " . $columns["LastName"], "contact", $newId);

    http_response_code(201);
    echo json_encode(["id" => $newId, "contact" => loadContactJson($newId, (int)$user["ID"]), "error" => ""]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(["error" => "Failed to add contact"]);
}
?>
