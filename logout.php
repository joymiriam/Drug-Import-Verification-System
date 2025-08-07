<?php
// Include the database connection from the Connection.php file
include 'Connection.php';

// Start the session
session_start();

// Include the file that contains the log_activity function
include 'activity.php'; 

// Record this logout action in the database activity log
// - $conn: the active database connection
// - $_SESSION['user_id']: ID of the user logging out
// - "User logged out": the action description
// - "Logout": the action type/category

log_activity($conn, $_SESSION['user_id'], "User logged out", "Logout");

// End the session by removing all session variables and destroying the session data
session_destroy();

// Redirect the user to the dashboard
header("Location: dashboard.html");

// Stop executing the script after the redirection
exit();
