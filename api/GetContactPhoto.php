<?php
require_once "contact_helpers.php";
requireMethod("GET");
$user = requireLogin();

// Sends the image itself: <img src="api/GetContactPhoto.php?id=12">
$contact = requireContactAccess(requireId("id"), (int)$user["ID"], "view");

$stmt = $pdo->prepare("SELECT MimeType, Data FROM ContactPhotos WHERE ContactID = ?");
$stmt->execute([$contact["ID"]]);
$photo = $stmt->fetch();
if (!$photo) {
    fail(404, "This contact has no photo");
}

header("Content-Type: " . $photo["MimeType"]);
header("Content-Length: " . strlen($photo["Data"]));
header("Cache-Control: private, max-age=86400"); // the URL changes (?v=...) whenever the photo does
echo $photo["Data"];
?>
