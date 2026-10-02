<?php
require_once "contact_helpers.php";
requireMethod("POST");
$user = requireLogin();
$userId = (int)$user["ID"];

// multipart/form-data with a "file" field. The first row must be headings.
// Bad rows are skipped and reported; good rows are imported. Unknown tag names are created.
$file = $_FILES["file"] ?? null;
if (!$file || $file["error"] !== UPLOAD_ERR_OK || !is_uploaded_file($file["tmp_name"])) {
    fail(422, "Please fix the highlighted fields", ["file" => "Choose a CSV file to import"]);
}
if ($file["size"] > MAX_CSV_BYTES) {
    fail(422, "Please fix the highlighted fields", ["file" => "The CSV must be smaller than 1 MB"]);
}

// Which column is which. Accepts our own export plus common names from phones and email apps.
$aliases = [
    "firstname" => "firstName", "first" => "firstName", "givenname" => "firstName",
    "lastname" => "lastName", "last" => "lastName", "surname" => "lastName", "familyname" => "lastName",
    "phone" => "cell", "cell" => "cell", "mobile" => "cell", "phonenumber" => "cell", "telephone" => "cell",
    "email" => "email", "emailaddress" => "email", "e-mail" => "email",
    "company" => "company", "organization" => "company", "organisation" => "company",
    "jobtitle" => "jobTitle", "title" => "jobTitle", "address" => "address",
    "birthday" => "birthday", "dob" => "birthday", "notes" => "notes",
    "tags" => "tags", "labels" => "tags", "groups" => "tags",
];

$handle = fopen("php://temp", "r+");
fwrite($handle, toUtf8((string)file_get_contents($file["tmp_name"]))); // Excel's Windows-1252 -> UTF-8
rewind($handle);

$header = fgetcsv($handle, null, ",", "\"", "");
$map = [];
foreach ($header ?: [] as $index => $heading) {
    $key = strtolower(preg_replace("/[\\s_]+/", "", preg_replace("/^\xEF\xBB\xBF/", "", (string)$heading)));
    if (isset($aliases[$key])) {
        $map[$index] = $aliases[$key];
    }
}
if (!in_array("firstName", $map, true)) {
    fail(422, "Please fix the highlighted fields", ["file" => "The first row must contain headings, including \"First Name\""]);
}

$rules = CONTACT_RULES;
unset($rules["tagIds"]);
$rules["lastName"] = ["max:50"]; // a first name is enough when importing

$imported = 0;
$errors = [];
$rowNumber = 1;
$tagIds = [];
$insert = $pdo->prepare("INSERT INTO Contacts (FirstName, LastName, Cell, Email, Company, JobTitle, Address, Birthday, Notes, UserID)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

$pdo->beginTransaction();
while (($cells = fgetcsv($handle, null, ",", "\"", "")) !== false) {
    $rowNumber++;
    if ($cells === [null] || implode("", $cells) === "") {
        continue; // blank line
    }
    if ($imported + count($errors) >= MAX_CSV_ROWS) {
        $errors[] = ["row" => $rowNumber, "message" => "Stopped: only " . MAX_CSV_ROWS . " rows can be imported at once"];
        break;
    }

    $record = [];
    foreach ($map as $index => $field) {
        $value = trim((string)($cells[$index] ?? ""));
        $record[$field] = preg_match("/^'[=+\\-@]/", $value) ? substr($value, 1) : $value; // undo safeCell() from exports
    }

    // Check this row without stopping the whole import on the first mistake.
    [$clean, $problems] = checkFields($record, $rules);
    if ($problems) {
        $errors[] = ["row" => $rowNumber, "message" => implode("; ", $problems)];
        continue;
    }
    $clean["lastName"] = $clean["lastName"] ?? "";
    $insert->execute(array_merge(array_values(contactColumns($clean)), [$userId]));
    $contactId = (int)$pdo->lastInsertId();

    foreach (array_filter(array_map("trim", preg_split("/[;|]/", $record["tags"] ?? ""))) as $tagName) {
        $tagName = textLimit($tagName, 40);
        $key = strtolower($tagName);
        if (!isset($tagIds[$key])) {
            $find = $pdo->prepare("SELECT ID FROM Tags WHERE UserID = ? AND Name = ?");
            $find->execute([$userId, $tagName]);
            $existing = $find->fetchColumn();
            if (!$existing) {
                $pdo->prepare("INSERT INTO Tags (UserID, Name) VALUES (?, ?)")->execute([$userId, $tagName]);
                $existing = $pdo->lastInsertId();
            }
            $tagIds[$key] = (int)$existing;
        }
        $pdo->prepare("INSERT IGNORE INTO ContactTags (ContactID, TagID) VALUES (?, ?)")->execute([$contactId, $tagIds[$key]]);
    }
    $imported++;
}
$pdo->commit();

if ($imported > 0) {
    logActivity("contacts.imported", "imported $imported contact(s) from CSV");
}
http_response_code(200);
echo json_encode(["imported" => $imported, "skipped" => count($errors), "errors" => array_slice($errors, 0, 50), "error" => ""]);
?>
