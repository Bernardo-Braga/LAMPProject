<?php
require_once "common.php";
requireMethod("GET");

// Who is signed in? Pages call this to check the session is still valid (401 if not).
$user = requireLogin(true);
http_response_code(200);
echo json_encode(userJson($user));
?>
