<?php
// Start the session to access session variables
session_start();

// Include the database connection file to use $conn for queries
include 'Connection.php'; 

// Include the file that contains the log_activity() function to track user actions
include 'activity.php'; 

// Check if the database connection was successful
if ($conn->connect_error) {
    // If connection failed, stop execution and show the error
    die("Connection failed: " . $conn->connect_error);
}

// Make sure an importer is logged in by checking if 'imp_id' is stored in the session
if (!isset($_SESSION['imp_id'])) {
    // If not logged in, terminate the script and show an error
    die("Error: You must be logged in as an importer to upload documents.");
}

// Retrieve the importer ID from the session for use in the shipment entry
$imp_id = $_SESSION['imp_id'];

// Helper function to convert a date from DD-MM-YYYY format to ISO standard YYYY-MM-DD

function convertDateToISO($dateStr) {
    $parts = explode('-', $dateStr); // Split the string by "-"
    if (count($parts) !== 3) {
        return false; // If not exactly 3 parts, return false to indicate invalid input
    }
    // Reassemble the parts into YYYY-MM-DD format
    return "{$parts[2]}-{$parts[1]}-{$parts[0]}";
}

// Check if the form has been submitted using POST method
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Safely retrieve and sanitize the form inputs
    $drug_id = isset($_POST["drug_id"]) ? trim($_POST["drug_id"]) : null;
    $method = trim($_POST["method"]); 
    $company = trim($_POST["company"]); 
    $port_of_entry = trim($_POST["port_of_entry"]); 
    $doa_input = trim($_POST["doa"]); 

    // Validate that the drug_id is not empty 
    if (empty($drug_id)) {
        die("Error: Drug ID is required.");
    }

    // Convert the user input date to proper format for database (YYYY-MM-DD)
    $doa = convertDateToISO($doa_input);
    if (!$doa) {
        // If conversion fails, show error and stop
        die("Error: Date of Arrival (DOA) must be in DD-MM-YYYY format.");
    }

    // Prepare an SQL INSERT statement to add the shipment data securely
    $sql = "INSERT INTO shipment (imp_id, method, company, port_of_entry, doa, drug_id) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql); // Prepare the query
    $stmt->bind_param("issssi", $imp_id, $method, $company, $port_of_entry, $doa, $drug_id); // Bind variables to prevent SQL injection

    // Execute the statement and check if the insert was successful
    if ($stmt->execute()) {
        // Retrieve the ID of the newly inserted shipment record
        $shipment_id = $stmt->insert_id;

        // Alert the user and redirect to the importer dashboard
        echo "<script>alert('Shipment recorded successfully!'); window.location.href='importer_dashboard.php';</script>";
        
        // Log the shipment registration activity for auditing
        log_activity($conn, $_SESSION['user_id'], "Registered a new shipment (ID: $shipment_id)", "Shipment Registration");

    } else {
        // If the query fails, show the error using JavaScript alert
        echo "<script>alert('Error: " . $stmt->error . "');</script>";
    }

    // Close the prepared statement to free up resources
    $stmt->close();
}

// Close the database connection when done
$conn->close();
?>
