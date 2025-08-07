<?php

// This gives us access to the variable $conn which represents the connection to the database.
include 'Connection.php';

// Define a function named log_activity that will log a user's activity to the database.
// The function accepts 4 parameters:
// - $conn: the database connection object 
// - $user_id: the ID of the user performing the activity 
// - $activity: a text description of the activity 
// - $activity_type: a category or type of activity 
function log_activity($conn, $user_id, $activity, $activity_type) {

    // Use PHP's built-in date() function to get the current date and time.
    // This timestamp will show when the activity happened.
    $activity_time = date("Y-m-d H:i:s");

    // Prepare an SQL statement using the prepare() method.
    // This creates a secure SQL statement with placeholders (?) to avoid SQL injection.
    // The SQL statement will insert data into the 'activities' table 
    $stmt = $conn->prepare("INSERT INTO activities (user_id, activity, activity_type, activity_time) VALUES (?, ?, ?, ?)");

     // Bind actual values to the placeholders in the SQL query using bind_param().
    // The first argument "isss" tells MySQL the data types
    $stmt->bind_param("isss", $user_id, $activity, $activity_type, $activity_time);

    // Execute the prepared SQL statement. This sends the data to the database and inserts it.
    $stmt->execute();


    // Close the statement
    $stmt->close();
}

?>
