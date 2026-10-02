<?php
require_once "contact_helpers.php";
requireMethod("GET");
$user = requireLogin();

// Who a contact is shared with (owner only): ?id=12
$contact = requireContactAccess(requireId("id"), (int)$user["ID"], "owner");

$stmt = $pdo->prepare("SELECT s.SharedWithUserID AS UserID, u.FirstName, u.LastName, u.Login, s.Permission, s.DateCreated
    FROM ContactShares s JOIN Users u ON u.ID = s.SharedWithUserID
    WHERE s.ContactID = ? ORDER BY u.FirstName, u.LastName");
$stmt->execute([$contact["ID"]]);

http_response_code(200);
echo json_encode($stmt->fetchAll());
?>
