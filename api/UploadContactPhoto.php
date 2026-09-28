<?php
require_once "contact_helpers.php";
requireMethod("POST");
$user = requireLogin();

// multipart/form-data with fields "id" and "photo". Photos are stored in the database
// (ContactPhotos), so uploaded files never end up in a folder the web server serves.
$contact = requireContactAccess(requireId("id"), (int)$user["ID"], "edit");
$file = $_FILES["photo"] ?? null;

if (!$file || ($file["error"] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    fail(422, "Please fix the highlighted fields", ["photo" => "Choose an image to upload"]);
}
if (in_array($file["error"], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $file["size"] > MAX_PHOTO_BYTES) {
    fail(422, "Please fix the highlighted fields", ["photo" => "Photo must be smaller than 2 MB"]);
}
if ($file["error"] !== UPLOAD_ERR_OK || !is_uploaded_file($file["tmp_name"])) {
    fail(422, "Please fix the highlighted fields", ["photo" => "Upload failed, please try again"]);
}

// Never trust the file name or the type the browser claims: check the actual bytes.
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file["tmp_name"]);
if (!in_array($mime, ["image/jpeg", "image/png", "image/webp", "image/gif"], true) || @getimagesize($file["tmp_name"]) === false) {
    fail(422, "Please fix the highlighted fields", ["photo" => "Photo must be a JPEG, PNG, WebP or GIF image"]);
}

try {
    $pdo->prepare("INSERT INTO ContactPhotos (ContactID, MimeType, Data) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE MimeType = VALUES(MimeType), Data = VALUES(Data)")
        ->execute([$contact["ID"], $mime, file_get_contents($file["tmp_name"])]);
    $pdo->prepare("UPDATE Contacts SET PhotoUpdatedAt = NOW(), DateUpdated = NOW() WHERE ID = ?")->execute([$contact["ID"]]);
    logActivity("contact.photo_updated", "changed the photo for " . contactName($contact), "contact", (int)$contact["ID"],
        $contact["Access"] === "owner" ? null : (int)$contact["UserID"]);

    http_response_code(200);
    echo json_encode(["photoUrl" => loadContactJson((int)$contact["ID"], (int)$user["ID"])["PhotoUrl"], "error" => ""]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Photo upload failed"]);
}
?>
