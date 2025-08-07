<?php
// Start a session to allow use of session variables
session_start();

// Include the database connection file
include 'Connection.php';

// Include the activity logger function
include 'activity.php'; 

// Check if the database connection failed
if ($conn->connect_error) {
    // Terminate the script and show the connection error
    die("Connection failed: " . $conn->connect_error);
}

// Check if the form has been submitted using POST method
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve and sanitize user inputs from the form
    $name = trim($_POST["name"]);
    $address = trim($_POST["address"]);
    $email = trim($_POST["email"]);
    $gmp_certNo = trim($_POST["gmp_certNo"]);
    $doi = trim($_POST["doi"]); // Date of Issue
    $gmp_cert_verif = isset($_POST["gmp_cert_verif"]) ? $_POST["gmp_cert_verif"] : "";

    // Convert the date from dd-mm-yyyy to yyyy-mm-dd (MySQL format)
    $date_parts = explode("-", $doi);
    $doi_mysql = "{$date_parts[2]}-{$date_parts[1]}-{$date_parts[0]}";

    // Convert "yes"/"no" string to integer (1 for yes, 0 for no)
    $gmp_cert_verif = ($gmp_cert_verif === "yes") ? 1 : 0;

    // Prepare SQL statement to insert the manufacturer data into the database
    $stmt = $conn->prepare("INSERT INTO manufacturer (name, address, email, gmp_certNo, doi, gmp_cert_verif) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $name, $address, $email, $gmp_certNo, $doi_mysql, $gmp_cert_verif);

    // Check if the insert was successful
    if ($stmt->execute()) {
        // Get the ID of the newly inserted manufacturer (used for linking documents)
        $manuf_id = $stmt->insert_id;

        // Handle file upload if a GMP certificate file was uploaded
        if (isset($_FILES["gmp_cert"]) && $_FILES["gmp_cert"]["error"] == 0) {
            // Extract file name and temporary file path
            $file_name = $_FILES["gmp_cert"]["name"];
            $file_tmp = $_FILES["gmp_cert"]["tmp_name"];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION)); // Get file extension

            // Only allow PDF files
            if ($file_ext !== "pdf") {
                die("Error: Only PDF files are allowed for GMP certificate.");
            }

            // Set upload directory and create a unique file name
            $upload_dir = "uploads/";
            $new_file_name = uniqid() . "_" . $file_name;
            $file_path = $upload_dir . $new_file_name;

            // Move the uploaded file to the uploads directory
            if (move_uploaded_file($file_tmp, $file_path)) {
                // Prepare document details for inserting into documents table
                $category = "Manufacturer";
                $type = "GMP Certificate";
                $identification_number = $gmp_certNo;

                // Prepare SQL to insert document record
                $sql_doc = "INSERT INTO documents (category, manuf_id, type, identification_number, path, dou)
                            VALUES (?, ?, ?, ?, ?, NOW())";
                $stmt_doc = $conn->prepare($sql_doc);
                $stmt_doc->bind_param("sisss", $category, $manuf_id, $type, $identification_number, $file_path);

                // Check if the document record was saved
                if (!$stmt_doc->execute()) {
                    echo "<script>alert('Error saving document: " . $stmt_doc->error . "');</script>";
                }

                // Close document statement
                $stmt_doc->close();

                // Log the activity if a user is logged in
                if (isset($_SESSION['user_id'])) {
                    log_activity(
                        $conn,
                        $_SESSION['user_id'],
                        "Registered manufacturer '$name' and uploaded GMP certificate",
                        "Manufacturer Registration"
                    );
                }
            } else {
                // Show error if the file could not be moved
                echo "<script>alert('Failed to upload the GMP certificate.');</script>";
            }
        }

        // Show success message and redirect to PPB dashboard
        echo "<script>alert('Manufacturer registered successfully.'); window.location.href='ppb_dashboard.php';</script>";
    } else {
        // Show an error if the SQL insert failed
        echo "<script>alert('Error: " . $stmt->error . "');</script>";
    }

    // Close the prepared statement
    $stmt->close();
}

// Close the database connection
$conn->close();
?>
