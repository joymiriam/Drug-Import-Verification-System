<?php
session_start(); // Start the session to access session variables
include 'Connection.php'; // Include the file that connects to the database
include 'activity.php'; // Include the file that contains the log_activity function

// Check if the form was submitted via POST method
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Check if the user is logged in by verifying session variable
    if (!isset($_SESSION['user_id'])) {
        // If not logged in, terminate script and display error message
        die("Error: User not logged in. Please log in first.");
    }

    // Retrieve and trim form input values to remove unnecessary whitespace
    $name = trim($_POST["name"]);
    $batch_no = trim($_POST["batch_no"]);
    $dom_input = trim($_POST["dom"]); 
    $doe_input = trim($_POST["doe"]); 
    $type = trim($_POST["type"]); 
    $strength = trim($_POST["strength"]);
    $quantity = trim($_POST["quantity"]); 

    // Convert manufacture date from dd-mm-yyyy to yyyy-mm-dd format
    $dom_parts = explode('-', $dom_input); // Split string by hyphen
    if (count($dom_parts) == 3) {
        $dom = $dom_parts[2] . '-' . $dom_parts[1] . '-' . $dom_parts[0]; // Rearrange into yyyy-mm-dd
    } else {
        // If format is incorrect, stop execution
        die("Invalid manufacture date format");
    }

    // Convert expiry date from dd-mm-yyyy to yyyy-mm-dd format
    $doe_parts = explode('-', $doe_input); // Split string by hyphen
    if (count($doe_parts) == 3) {
        $doe = $doe_parts[2] . '-' . $doe_parts[1] . '-' . $doe_parts[0]; // Rearrange into yyyy-mm-dd
    } else {
        // If format is incorrect, stop execution
        die("Invalid expiry date format");
    }

    // Prepare SQL query to insert drug record into database
    $sql = "INSERT INTO drugs (name, batch_no, dom, doe, type, strength, quantity) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql); // Prepare the SQL statement
    // Bind the parameters to the SQL query
    $stmt->bind_param("ssssssi", $name, $batch_no, $dom, $doe, $type, $strength, $quantity);

    // Execute the query and check if it was successful
    if ($stmt->execute()) {
        $drug_id = $stmt->insert_id; // Get the ID of the newly inserted drug

        // Show a JavaScript alert and redirect to the importer dashboard
        echo "<script> alert('Drug registered successfully!');
       window.location.href = 'importer_dashboard.php';
       </script>";

        // Log the activity using the custom function
        log_activity($conn, $_SESSION['user_id'], "Registered drug '$name' (ID: $drug_id)", "Drug Registration");
        exit(); // End script after redirect
    } else {
        // If insertion failed, display the error
        echo "Error: " . $stmt->error;
    }

    // Close the prepared statement and database connection
    $stmt->close();
    $conn->close();
}
?>
