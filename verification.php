<?php
// Include database connection file
include 'Connection.php';

// Check if the database connection was successful
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error); // Stop the script if connection failed
}

// Check if the form was submitted using POST method
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Retrieve and sanitize input data from the form
    $status = trim($_POST["status"]); 
    $dov = trim($_POST["dov"]); 
    $inspector_name = trim($_POST["inspector_name"]); 
    $rejection_reason = isset($_POST["rejection_reason"]) ? trim($_POST["rejection_reason"]) : null; 

    // Convert DOV from DD-MM-YYYY to YYYY-MM-DD format for MySQL storage
    $date_parts = explode("-", $dov); // Split date string into array using "-" as separator
    $dov_mysql = "{$date_parts[2]}-{$date_parts[1]}-{$date_parts[0]}"; // Rearrange parts to match MySQL date format

    // Prepare SQL statement to insert verification record
    $sql = "INSERT INTO verification (status, dov, inspector_name, rejection_reason) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql); // Prepare the SQL statement

    // Bind the input values to the prepared statement (all as strings)
    $stmt->bind_param("ssss", $status, $dov_mysql, $inspector_name, $rejection_reason);

    // Execute the SQL statement
    if ($stmt->execute()) {
        // If insertion successful, show alert and redirect back to form page
        echo "<script>alert('Verification recorded successfully!'); window.location.href='verification.html';</script>";
    } else {
        // If error occurs during insertion, show error message
        echo "<script>alert('Error: " . $stmt->error . "');</script>";
    }

    // Close the statement to free resources
    $stmt->close();
}

// Close the database connection
$conn->close();
?>
