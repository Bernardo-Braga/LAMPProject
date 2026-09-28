<?php
require_once "common.php";
requireMethod("POST");
$user = requireLogin();

$data = validateFields(getRequestData() ?? [], [
    "firstName" => ["required", "max:50"],
    "lastName" => ["required", "max:50"],
]);

try {
    $stmt = $pdo->prepare("UPDATE Users SET FirstName = ?, LastName = ? WHERE ID = ?");
    $stmt->execute([$data["firstName"], $data["lastName"], $user["ID"]]);
    logActivity("account.updated", "updated their profile", "user", (int)$user["ID"]);

    http_response_code(200);
    echo json_encode(userJson(findUserOr404((int)$user["ID"])));
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Profile update failed"]);
}
?>
