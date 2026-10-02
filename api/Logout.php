<?php
require_once "common.php";
requireMethod("POST");

// Ends the server-side session. Safe to call even if already signed out.
if (currentUser() !== null) {
    requireLogin(true); // a signed-in user must send the CSRF token, like any other POST
}
signOut();
http_response_code(200);
echo json_encode(["error" => ""]);
?>
