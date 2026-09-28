<?php
require_once "contact_helpers.php";
requireMethod("GET");
$user = requireLogin();
$userId = (int)$user["ID"];

$term = inputText("term");
$scope = inputText("scope"); // all (default) = mine + shared with me, mine, shared

try {
    $where = match ($scope) {
        "mine" => "c.UserID = ?",
        "shared" => "s.SharedWithUserID IS NOT NULL",
        default => "(c.UserID = ? OR s.SharedWithUserID IS NOT NULL)",
    };
    $params = [$userId, $userId];
    if ($scope !== "shared") {
        $params[] = $userId;
    }

    if ($term !== "") {
        $like = likePattern($term);
        $where .= " AND (c.FirstName LIKE ? OR c.LastName LIKE ? OR c.Cell LIKE ? OR c.Email LIKE ?
                         OR c.Company LIKE ? OR c.JobTitle LIKE ? OR CONCAT(c.FirstName, ' ', c.LastName) LIKE ?)";
        array_push($params, $like, $like, $like, $like, $like, $like, $like);
    }

    $stmt = $pdo->prepare(CONTACT_SELECT . " WHERE $where ORDER BY c.LastName, c.FirstName");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $ids = array_map(function ($r) { return (int)$r["ID"]; }, $rows);
    $tags = tagsForContacts($ids);
    $shareCounts = shareCountsForContacts($ids);

    $contacts = [];
    foreach ($rows as $row) {
        $contacts[] = contactJson($row, $userId, $tags[(int)$row["ID"]] ?? [], $shareCounts[(int)$row["ID"]] ?? 0);
    }
    http_response_code(200);
    echo json_encode($contacts);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Search failed"]);
}
?>
