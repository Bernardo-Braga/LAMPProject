<?php
require_once "common.php";
requireMethod("GET");
requireAdmin();

// Every recorded action, newest first, including failed sign-ins and IP addresses.
//   ?term=text  &action=contact (a group) or contact.created (exact)  &userId=5  &page=2
$term = inputText("term");
$action = inputText("action");
$userId = inputInt("userId", 0, 0, PHP_INT_MAX);
$page = inputInt("page", 1, 1, 100000);
$pageSize = 30;

$where = ["1 = 1"];
$params = [];
if ($action !== "") {
    $where[] = strpos($action, ".") !== false ? "a.Action = ?" : "a.Action LIKE ?";
    $params[] = strpos($action, ".") !== false ? $action : addcslashes($action, "%_\\") . ".%";
}
if ($userId > 0) {
    $where[] = "(a.ActorUserID = ? OR a.TargetUserID = ?)";
    array_push($params, $userId, $userId);
}
if ($term !== "") {
    $like = likePattern($term);
    $where[] = "(a.Summary LIKE ? OR au.Login LIKE ? OR a.ActorName LIKE ? OR a.TargetName LIKE ? OR a.IPAddress LIKE ?)";
    array_push($params, $like, $like, $like, $like, $like);
}
$whereSql = implode(" AND ", $where);

$count = $pdo->prepare("SELECT COUNT(*) FROM ActivityLog a LEFT JOIN Users au ON au.ID = a.ActorUserID WHERE $whereSql");
$count->execute($params);
$total = (int)$count->fetchColumn();
$pages = max(1, (int)ceil($total / $pageSize));
$page = min($page, $pages);
$offset = ($page - 1) * $pageSize;

$stmt = $pdo->prepare(ACTIVITY_SELECT . " WHERE $whereSql ORDER BY a.DateCreated DESC, a.ID DESC LIMIT $pageSize OFFSET $offset");
$stmt->execute($params);

http_response_code(200);
echo json_encode([
    "items" => activityJson($stmt->fetchAll(), true),
    "total" => $total,
    "page" => $page,
    "pages" => $pages,
    "actions" => $pdo->query("SELECT DISTINCT Action FROM ActivityLog ORDER BY Action")->fetchAll(PDO::FETCH_COLUMN),
]);
?>
