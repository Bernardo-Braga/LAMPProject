<?php
require_once "common.php";
requireMethod("GET");
requireAdmin();

// Numbers and chart data for the Statistics tab of the admin page.
$totals = $pdo->query("SELECT
    (SELECT COUNT(*) FROM Users) AS Users,
    (SELECT COUNT(*) FROM Users WHERE Role = 'Admin') AS Admins,
    (SELECT COUNT(*) FROM Users WHERE IsDisabled = 1) AS Disabled,
    (SELECT COUNT(*) FROM Users WHERE LockedUntil > NOW()) AS Locked,
    (SELECT COUNT(*) FROM Users WHERE LastLoginAt > NOW() - INTERVAL 7 DAY) AS ActiveLast7Days,
    (SELECT COUNT(*) FROM Contacts) AS Contacts,
    (SELECT COUNT(*) FROM ContactShares) AS Shares,
    (SELECT COUNT(*) FROM Tags) AS Tags,
    (SELECT COUNT(*) FROM ActivityLog WHERE Action = 'user.login_failed' AND DateCreated > NOW() - INTERVAL 1 DAY) AS FailedLogins24h")
    ->fetch();

/** Counts per day for the last $days days, with zeros for quiet days: [{Date, Count}] */
function dailyCounts(PDO $pdo, string $sql, int $days): array
{
    $start = new DateTime("today");
    $start->modify("-" . ($days - 1) . " days");
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$start->format("Y-m-d 00:00:00")]);
    $byDay = [];
    foreach ($stmt->fetchAll() as $r) {
        $byDay[$r["Day"]] = (int)$r["N"];
    }
    $series = [];
    for ($i = 0; $i < $days; $i++) {
        $day = $start->format("Y-m-d");
        $series[] = ["Date" => $day, "Count" => $byDay[$day] ?? 0];
        $start->modify("+1 day");
    }
    return $series;
}

$topUsers = $pdo->query("SELECT u.ID, u.FirstName, u.LastName, u.Login, COUNT(c.ID) AS Contacts
    FROM Users u JOIN Contacts c ON c.UserID = u.ID
    GROUP BY u.ID, u.FirstName, u.LastName, u.Login ORDER BY Contacts DESC LIMIT 5")->fetchAll();
$actions = $pdo->query("SELECT Action, COUNT(*) AS Count FROM ActivityLog
    WHERE DateCreated > NOW() - INTERVAL 7 DAY GROUP BY Action ORDER BY Count DESC LIMIT 10")->fetchAll();

http_response_code(200);
echo json_encode([
    "totals" => array_map("intval", $totals),
    "loginsByDay" => dailyCounts($pdo, "SELECT DATE(DateCreated) AS Day, COUNT(*) AS N FROM ActivityLog WHERE Action = 'user.login' AND DateCreated >= ? GROUP BY Day", 14),
    "signupsByDay" => dailyCounts($pdo, "SELECT DATE(DateCreated) AS Day, COUNT(*) AS N FROM Users WHERE DateCreated >= ? GROUP BY Day", 30),
    "contactsByDay" => dailyCounts($pdo, "SELECT DATE(DateCreated) AS Day, COUNT(*) AS N FROM Contacts WHERE DateCreated >= ? GROUP BY Day", 30),
    "topUsers" => $topUsers,
    "actionsLast7Days" => $actions,
]);
?>
