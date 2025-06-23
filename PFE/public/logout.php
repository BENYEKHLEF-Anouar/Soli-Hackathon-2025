<?php
// Start the session to access it
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to the home page after logout
header("Location: index.php");
exit;
?>