<?php
session_start(); // Starts a new session or resumes an existing one

include 'Connection.php'; // Connects to the database
include 'activity.php';   // Includes the file that contains the log_activity function

// Check if the database connection was successful
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error); // Stop script if connection fails
}

// Check if the importer is logged in by verifying the imp_id in session
if (!isset($_SESSION['imp_id'])) {
    die("Error: You must be logged in as an importer to upload documents."); // Block access if not logged in
}

// Get the importer ID from session
$imp_id = $_SESSION['imp_id'];

// Check if the form has been submitted via POST method
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Retrieve form fields from POST request
    $category = $_POST["category"];                        
    $type = $_POST["type"];                               
    $identification_number = $_POST["identification_number"];  

    // Handle the uploaded file
    if (isset($_FILES["path"]) && $_FILES["path"]["error"] == 0) {

        // Extract file details
        $file_name = $_FILES["path"]["name"];          // Original file name
        $file_tmp = $_FILES["path"]["tmp_name"];       // Temporary location
        $file_size = $_FILES["path"]["size"];          // File size in bytes
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION)); // Get file extension

        // Only allow PDF files
        if ($file_ext !== "pdf") {
            die("Error: Only PDF files are allowed.");
        }

        // Set the directory to save the uploaded files
        $upload_dir = "uploads/";

        // Generate a new unique file name to avoid overwriting
        $new_file_name = uniqid() . "_" . $file_name;
        $file_path = $upload_dir . $new_file_name;

        // Move the file from temporary to permanent directory
        if (move_uploaded_file($file_tmp, $file_path)) {

            // Prepare SQL statement to insert document details into database
            $sql = "INSERT INTO documents (category, imp_id, type, identification_number, path, dou) 
                    VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sisss", $category, $imp_id, $type, $identification_number, $file_path);

            // Execute the query
            if ($stmt->execute()) {
                // Show success alert and redirect back to dashboard
                echo "<script>alert('Document submitted successfully!'); window.location.href='importer_dashboard.php';</script>";

                // Log the activity 
                log_activity($conn, $_SESSION['user_id'], "Uploaded document '$file_name'", "Document Upload");

                exit(); // Stop further execution
            } else {
                // Show database error if query failed
                echo "Error: " . $stmt->error;
            }

            // Close the prepared statement
            $stmt->close();
        } else {
            // Handle failure in file move
            die("Error uploading the file.");
        }
    } else {
        // Handle case where no file was uploaded
        die("File upload is required.");
    }
}

// Close the database connection
$conn->close();
?>
