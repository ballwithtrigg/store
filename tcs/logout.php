<?php
require_once __DIR__ . '/config/database.php';

// Unset only user session variables (not admin ones if they exist, though typically they are separate)
unset($_SESSION['user_id']);
unset($_SESSION['user_email']);

// If no session data left, destroy it
if (empty($_SESSION)) {
    session_destroy();
}

header('Location: /tcs/index.php');
exit;
