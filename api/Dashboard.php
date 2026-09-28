<?php
require_once "common.php";
requireMethod("GET");
$user = requireLogin();
$me = (int)$user["ID"];

// Everything the dashboard page shows, in one request.
$counts = $pdo->prepare("SELECT
    (SELECT COUNT(*) FROM Contacts WHERE UserID = ?) AS Contacts,
    (SELECT COUNT(*) FROM ContactFavorites WHERE UserID = ?) AS Favorites,
    (SELECT COUNT(*) FROM ContactShares WHERE SharedWithUserID = ?) AS SharedWithMe,
    (SELECT COUNT(DISTINCT s.ContactID) FROM ContactShares s JOIN Contacts c ON c.ID = s.ContactID WHERE c.UserID = ?) AS SharedByMe,
    (SELECT COUNT(*) FROM Tags WHERE UserID = ?) AS Tags");
$counts->execute([$me, $me, $me, $me, $me]);

$recent = $pdo->prepare("SELECT ID, FirstName, LastName, Company, DateCreated FROM Contacts
    WHERE UserID = ? ORDER BY DateCreated DESC, ID DESC LIMIT 5");
$recent->execute([$me]);

// Birthdays in the next 30 days, for contacts you own or that are shared with you.
$withBirthdays = $pdo->prepare("SELECT c.ID, c.FirstName, c.LastName, c.Birthday FROM Contacts c
    LEFT JOIN ContactShares s ON s.ContactID = c.ID AND s.SharedWithUserID = ?
    WHERE c.Birthday IS NOT NULL AND (c.UserID = ? OR s.SharedWithUserID IS NOT NULL)");
$withBirthdays->execute([$me, $me]);

$today = new DateTime("today");
$birthdays = [];
foreach ($withBirthdays->fetchAll() as $c) {
    [$year, $month, $day] = array_map("intval", explode("-", $c["Birthday"]));
    $nextYear = (int)$today->format("Y");
    for ($attempt = 0; $attempt < 2; $attempt++, $nextYear++) {
        $celebrate = checkdate($month, $day, $nextYear) ? $day : 28; // Feb 29 -> Feb 28 in other years
        $next = new DateTime(sprintf("%04d-%02d-%02d", $nextYear, $month, $celebrate));
        if ($next >= $today) {
            break;
        }
    }
    $daysUntil = (int)$today->diff($next)->days;
    if ($daysUntil <= 30) {
        $birthdays[] = ["ID" => (int)$c["ID"], "Name" => $c["FirstName"] . " " . $c["LastName"],
            "Date" => $next->format("Y-m-d"), "DaysUntil" => $daysUntil, "Turning" => $nextYear - $year];
    }
}
usort($birthdays, function ($a, $b) { return $a["DaysUntil"] <=> $b["DaysUntil"]; });

$activity = $pdo->prepare(ACTIVITY_SELECT . " WHERE (a.ActorUserID = ? OR a.TargetUserID = ?)
    AND a.Action NOT IN ('user.login', 'user.login_failed', 'user.locked', 'contacts.exported')
    ORDER BY a.DateCreated DESC, a.ID DESC LIMIT 8");
$activity->execute([$me, $me]);

http_response_code(200);
echo json_encode([
    "counts" => array_map("intval", $counts->fetch()),
    "upcomingBirthdays" => $birthdays,
    "recentContacts" => $recent->fetchAll(),
    "activity" => activityJson($activity->fetchAll()),
]);
?>
