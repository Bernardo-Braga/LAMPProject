<?php
require_once "common.php";
requireMethod("GET");
$user = requireLogin();

// Your own actions plus things others did that affect you (e.g. someone edited a contact
// you shared with them). Sign-ins and exports are left out; admins see those in AuditLog.php.
$page = inputInt("page", 1, 1, 100000);
$pageSize = inputInt("pageSize", 20, 1, 50);

$where = "(a.ActorUserID = ? OR a.TargetUserID = ?)
    AND a.Action NOT IN ('user.login', 'user.login_failed', 'user.locked', 'contacts.exported')";

$count = $pdo->prepare("SELECT COUNT(*) FROM ActivityLog a WHERE $where");
$count->execute([$user["ID"], $user["ID"]]);
$total = (int)$count->fetchColumn();
$pages = max(1, (int)ceil($total / $pageSize));
$page = min($page, $pages);
$offset = ($page - 1) * $pageSize;

$stmt = $pdo->prepare(ACTIVITY_SELECT . " WHERE $where ORDER BY a.DateCreated DESC, a.ID DESC LIMIT $pageSize OFFSET $offset");
$stmt->execute([$user["ID"], $user["ID"]]);

http_response_code(200);
echo json_encode(["items" => activityJson($stmt->fetchAll()), "total" => $total, "page" => $page, "pages" => $pages]);
?>
