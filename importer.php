<?php
// Start the session to access session variables
session_start();

// Include the database connection file
include 'Connection.php'; 

// Check if the user is logged in by verifying the existence of 'user_id' in session
if (!isset($_SESSION['user_id'])) {
    // If not logged in, redirect the user to the login page
    header("Location: login.html");
    exit();
}

// Get the user ID from the session 
$user_id = $_SESSION['user_id']; 

// Check if the form was submitted via POST method
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect and sanitize form data using trim to remove unnecessary whitespace
    $name = trim($_POST["name"]);
    $phone = trim($_POST["phone"]);
    $address = trim($_POST["address"]);
    $license_no = trim($_POST["license_no"]);
    $email = trim($_POST["email"]);
    $registration_no = trim($_POST["registration_no"]);

    // Prepare the SQL statement to insert the importer details into the database
      $sql = "INSERT INTO importer (name, phone, address, license_no, email, registration_no, user_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";

    // Prepare the SQL statement to prevent SQL injection
    $stmt = $conn->prepare($sql);

    // Bind the values to the prepared statement 
       $stmt->bind_param("ssssssi", $name, $phone, $address, $license_no, $email, $registration_no, $user_id);

    // Execute the prepared statement
    if ($stmt->execute()) {
        // If insert is successful, store a message in the session
        $_SESSION['message'] = "Your registration was successful and is awaiting approval by the PPB.";
        
        // Redirect the user to the importer dashboard
        header("Location: importer_dashboard.php");

    } else {
        // If there's a database error, show it using JavaScript alert
        echo "<script>alert('Error: " . $stmt->error . "');</script>";
    }

    // Close the prepared statement to free up resources
    $stmt->close();
}

// Close the database connection 
$conn->close();
?>
