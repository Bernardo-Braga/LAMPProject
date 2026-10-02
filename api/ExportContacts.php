<?php
require_once "contact_helpers.php";
requireMethod("GET");
$user = requireLogin();

// Downloads your own contacts as a CSV file (opens in Excel / Google Sheets).
$stmt = $pdo->prepare("SELECT * FROM Contacts WHERE UserID = ? ORDER BY LastName, FirstName");
$stmt->execute([$user["ID"]]);
$rows = $stmt->fetchAll();
$tags = tagsForContacts(array_map(function ($r) { return (int)$r["ID"]; }, $rows));

// Spreadsheet apps run cells starting with = + - @ as formulas ("CSV injection"); prefix them with '.
function safeCell($value): string
{
    $value = (string)$value;
    return preg_match("/^[=+\\-@\\t\\r]/", $value) ? "'" . $value : $value;
}

$out = fopen("php://temp", "r+");
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 marker so Excel shows accents correctly
fputcsv($out, ["First Name", "Last Name", "Phone", "Email", "Company", "Job Title", "Address", "Birthday", "Notes", "Tags"], ",", "\"", "");
foreach ($rows as $r) {
    $tagNames = array_column($tags[(int)$r["ID"]] ?? [], "Name");
    $line = [$r["FirstName"], $r["LastName"], $r["Cell"], $r["Email"], $r["Company"], $r["JobTitle"],
        $r["Address"], $r["Birthday"], $r["Notes"], implode("; ", $tagNames)];
    fputcsv($out, array_map("safeCell", $line), ",", "\"", "");
}
rewind($out);
$csv = stream_get_contents($out);

logActivity("contacts.exported", "exported " . count($rows) . " contact(s) to CSV");
header("Content-Type: text/csv; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"contacts-" . date("Y-m-d") . ".csv\"");
echo $csv;
?>
