<?php
require_once "common.php";
requireMethod("GET");
$user = requireLogin();

// Your tags, with how many of your contacts use each one.
$stmt = $pdo->prepare("SELECT t.ID, t.Name, t.Color, COUNT(ct.ContactID) AS ContactCount
    FROM Tags t LEFT JOIN ContactTags ct ON ct.TagID = t.ID
    WHERE t.UserID = ? GROUP BY t.ID, t.Name, t.Color ORDER BY t.Name");
$stmt->execute([$user["ID"]]);

http_response_code(200);
echo json_encode($stmt->fetchAll());
?>
