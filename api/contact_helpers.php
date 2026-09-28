<?php
/**
 * Helpers shared by the contact endpoints: who may see or change a contact,
 * the contact fields, tags, and the JSON shape the pages receive.
 *
 * Access levels:
 *   owner  created it: full control, can share, delete and tag it
 *   edit   shared with "can edit": may change the details and photo
 *   view   shared read-only
 */

require_once __DIR__ . "/common.php";

/** Validation rules for the contact form (only the name is required). */
const CONTACT_RULES = [
    "firstName" => ["required", "max:50"],
    "lastName" => ["required", "max:50"],
    "cell" => ["phone", "max:30"],
    "email" => ["email", "max:254"],
    "company" => ["max:100"],
    "jobTitle" => ["max:100"],
    "address" => ["max:255"],
    "birthday" => ["date", "past"],
    "notes" => ["max:2000"],
    "tagIds" => ["list"],
];

/**
 * SELECT for contacts as seen by one user. Bind the viewer's id twice (for the two joins):
 *   $pdo->prepare(CONTACT_SELECT . " WHERE ...")->execute([$me, $me, ...])
 */
const CONTACT_SELECT = "
    SELECT c.*, u.FirstName AS OwnerFirstName, u.LastName AS OwnerLastName, u.Login AS OwnerLogin,
           s.Permission AS SharePermission, (f.ContactID IS NOT NULL) AS IsFavorite
    FROM Contacts c
    JOIN Users u ON u.ID = c.UserID
    LEFT JOIN ContactShares s ON s.ContactID = c.ID AND s.SharedWithUserID = ?
    LEFT JOIN ContactFavorites f ON f.ContactID = c.ID AND f.UserID = ?";

/**
 * Load a contact and check the user has at least $needed access ("view", "edit" or "owner").
 * Answers 404 when the user has no access at all, so ids of other people's contacts
 * can't be discovered by guessing.
 */
function requireContactAccess(int $contactId, int $userId, string $needed): array
{
    global $pdo;
    $stmt = $pdo->prepare(CONTACT_SELECT . " WHERE c.ID = ?");
    $stmt->execute([$userId, $userId, $contactId]);
    $row = $stmt->fetch();

    $access = null;
    if ($row) {
        $access = (int) $row["UserID"] === $userId ? "owner" : ($row["SharePermission"] ?: null);
    }
    if ($access === null) {
        fail(404, "Contact not found");
    }

    $rank = ["view" => 1, "edit" => 2, "owner" => 3];
    if ($rank[$access] < $rank[$needed]) {
        fail(403, $needed === "edit" ? "This contact was shared with you as view-only" : "Only the owner can do that");
    }
    $row["Access"] = $access;
    return $row;
}

/** Map validated form fields to Contacts columns. */
function contactColumns(array $clean): array
{
    return [
        "FirstName" => $clean["firstName"],
        "LastName" => $clean["lastName"],
        "Cell" => $clean["cell"],
        "Email" => $clean["email"],
        "Company" => $clean["company"],
        "JobTitle" => $clean["jobTitle"],
        "Address" => $clean["address"],
        "Birthday" => $clean["birthday"],
        "Notes" => $clean["notes"],
    ];
}

/** Replace a contact's tags. Only tags that belong to the contact's owner are accepted. */
function saveContactTags(int $contactId, int $ownerId, array $tagIds): void
{
    global $pdo;
    $tagIds = array_values(array_unique(array_map("intval", $tagIds)));
    if ($tagIds) {
        $placeholders = implode(",", array_fill(0, count($tagIds), "?"));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Tags WHERE UserID = ? AND ID IN ($placeholders)");
        $stmt->execute(array_merge([$ownerId], $tagIds));
        if ((int) $stmt->fetchColumn() !== count($tagIds)) {
            fail(422, "Please fix the highlighted fields", ["tagIds" => "One or more tags do not exist"], "validation_failed");
        }
    }
    $pdo->prepare("DELETE FROM ContactTags WHERE ContactID = ?")->execute([$contactId]);
    $insert = $pdo->prepare("INSERT INTO ContactTags (ContactID, TagID) VALUES (?, ?)");
    foreach ($tagIds as $tagId) {
        $insert->execute([$contactId, $tagId]);
    }
}

/** Tags for many contacts at once: [contactId => [{ID, Name, Color}, ...]] */
function tagsForContacts(array $contactIds): array
{
    global $pdo;
    if (!$contactIds) {
        return [];
    }
    $placeholders = implode(",", array_fill(0, count($contactIds), "?"));
    $stmt = $pdo->prepare(
        "SELECT ct.ContactID, t.ID, t.Name, t.Color FROM ContactTags ct JOIN Tags t ON t.ID = ct.TagID
         WHERE ct.ContactID IN ($placeholders) ORDER BY t.Name"
    );
    $stmt->execute(array_values($contactIds));
    $byContact = [];
    foreach ($stmt->fetchAll() as $r) {
        $byContact[(int) $r["ContactID"]][] = ["ID" => (int) $r["ID"], "Name" => $r["Name"], "Color" => $r["Color"]];
    }
    return $byContact;
}

/** How many people each contact is shared with: [contactId => count] */
function shareCountsForContacts(array $contactIds): array
{
    global $pdo;
    if (!$contactIds) {
        return [];
    }
    $placeholders = implode(",", array_fill(0, count($contactIds), "?"));
    $stmt = $pdo->prepare("SELECT ContactID, COUNT(*) AS n FROM ContactShares WHERE ContactID IN ($placeholders) GROUP BY ContactID");
    $stmt->execute(array_values($contactIds));
    $counts = [];
    foreach ($stmt->fetchAll() as $r) {
        $counts[(int) $r["ContactID"]] = (int) $r["n"];
    }
    return $counts;
}

/**
 * The JSON for one contact, as seen by $viewerId. Keeps the original column-style keys
 * (ID, FirstName, LastName, Cell, Email) that the contacts page already reads.
 * Tags are private labels, so only the owner sees them.
 */
function contactJson(array $row, int $viewerId, array $tags = [], int $shareCount = 0): array
{
    $id = (int) $row["ID"];
    $isOwner = (int) $row["UserID"] === $viewerId;
    return [
        "ID" => $id,
        "FirstName" => $row["FirstName"],
        "LastName" => $row["LastName"],
        "Cell" => $row["Cell"],
        "Email" => $row["Email"],
        "Company" => $row["Company"],
        "JobTitle" => $row["JobTitle"],
        "Address" => $row["Address"],
        "Birthday" => $row["Birthday"],
        "Notes" => $row["Notes"],
        "PhotoUrl" => $row["PhotoUpdatedAt"] ? "api/GetContactPhoto.php?id=$id&v=" . strtotime($row["PhotoUpdatedAt"]) : null,
        "IsFavorite" => (bool) $row["IsFavorite"],
        "Tags" => $isOwner ? $tags : [],
        "Access" => $isOwner ? "owner" : ($row["SharePermission"] ?: "view"),
        "OwnerName" => $isOwner ? null : trim($row["OwnerFirstName"] . " " . $row["OwnerLastName"]),
        "OwnerLogin" => $isOwner ? null : $row["OwnerLogin"],
        "SharedWithCount" => $isOwner ? $shareCount : 0,
        "DateCreated" => $row["DateCreated"],
        "DateUpdated" => $row["DateUpdated"],
    ];
}

/** Reload one contact and return its JSON (after an add or edit). */
function loadContactJson(int $contactId, int $viewerId): array
{
    $row = requireContactAccess($contactId, $viewerId, "view");
    return contactJson($row, $viewerId, tagsForContacts([$contactId])[$contactId] ?? [], shareCountsForContacts([$contactId])[$contactId] ?? 0);
}

/** A contact's full name for activity messages. */
function contactName(array $row): string
{
    return trim($row["FirstName"] . " " . $row["LastName"]);
}
