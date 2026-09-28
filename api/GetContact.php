<?php
require_once "contact_helpers.php";
requireMethod("GET");
$user = requireLogin();

// One contact you own or that is shared with you: ?id=12
http_response_code(200);
echo json_encode(loadContactJson(requireId("id"), (int)$user["ID"]));
?>
