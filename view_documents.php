<?php
session_start(); // Start the session to access session variables
include 'Connection.php'; // Include the database connection

// Check if user is logged in and has the "importer" role
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "importer") {
    header("Location: login.html"); // Redirect to login if not authorized
    exit();
}

$user_id = $_SESSION["user_id"]; // Store current user's ID

// Check if a document ID is provided in the URL and ensure it's a number
if (!isset($_GET['doc_id']) || !is_numeric($_GET['doc_id'])) {
    echo "Invalid document ID.";
    exit();
}

// Get the document ID from the URL
$doc_id = $_GET['doc_id']; 

// Fetch the current importer's ID using the logged-in user's ID
$sql_imp = "SELECT imp_id FROM importer WHERE user_id = ?";
$stmt_imp = $conn->prepare($sql_imp);
$stmt_imp->bind_param("i", $user_id);
$stmt_imp->execute();
$result_imp = $stmt_imp->get_result();
$importer = $result_imp->fetch_assoc();
$stmt_imp->close();

// If the importer doesn't exist, stop further processing
if (!$importer) {
    echo "Importer not found.";
    exit();
}

// Retrieve document details, but only if it belongs to this importer
$sql_doc = "SELECT path, category, type, identification_number, dou 
            FROM documents 
            WHERE doc_id = ? AND imp_id = ?";
$stmt_doc = $conn->prepare($sql_doc);
$stmt_doc->bind_param("ii", $doc_id, $importer['imp_id']);
$stmt_doc->execute();
$result_doc = $stmt_doc->get_result();
$doc = $result_doc->fetch_assoc();
$stmt_doc->close();


$conn->close(); // Close database connection

// If no document is found or it doesn’t belong to this importer
if (!$doc) {
    echo "Document not found or access denied.";
    exit();
}

// Get the document file path
$doc_path = $doc['path'];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>View Document</title>
<style>
    /* Set general font, background color, padding and center align text */
    body {
        font-family: Arial, sans-serif;            
        background-color: #f2f2f2;                 /* Light gray background for the entire page */
        padding: 30px;                             /* Add space inside the page */
        text-align: center;                        /* Center-align all text by default */
    }

    /* Style for the document container */
    .doc-container {
        background: #fff;                          /* White background for contrast */
        padding: 20px;                             /* Inner spacing inside the container */
        max-width: 800px;                          /* Limit the container width to 800px */
        margin: auto;                              /* Center the container horizontally */
        border-radius: 10px;                       /* Smooth rounded corners */
        box-shadow: 0 0 10px rgba(0,0,0,0.1);      /* Soft shadow for a card-like look */
    }

    /* Container for document metadata (e.g. name, type) */
    .doc-meta {
        text-align: left;                          /* Left-align text for better readability */
        margin-bottom: 20px;                       /* Add spacing below metadata section */
    }

    /* Style metadata labels like “Document Name” */
    .doc-meta strong {
        display: inline-block;                     /* Allow width to be controlled but remain inline */
        width: 180px;                              /* Fixed width for alignment of all labels */
    }

    /* Style for the back button */
    .back-btn {
        margin-top: 20px;                          /* Add spacing above the button */
        display: inline-block;                     /* Allows applying width/padding without full width */
        background-color: #9474af;                 /* Purple background */
        color: white;                              /* White text */
        text-decoration: none;                     /* Remove underline from link */
        padding: 10px 20px;                        /* Vertical and horizontal padding */
        border-radius: 6px;                        /* Rounded corners */
    }

    /* Hover effect for the back button */
    .back-btn:hover {
        background-color: #7a5d98;                 /* Slightly darker purple on hover */
    }

    /* Style for the embedded document viewer (PDF etc.) */
    embed {
        border: 1px solid #ccc;                    /* Light border around the embed */
        border-radius: 6px;                        /* Rounded corners for smoothness */
    }
</style>

</head>
<body>
    <div class="doc-container">
        <h2>Document Preview</h2>

        <!-- Display document details-->
        <div class="doc-meta">
            <p><strong>Category:</strong> <?php echo htmlspecialchars($doc['category']); ?></p>
            <p><strong>Type:</strong> <?php echo htmlspecialchars($doc['type']); ?></p>
            <p><strong>Identification Number:</strong> <?php echo htmlspecialchars($doc['identification_number']); ?></p>
            <p><strong>Date Uploaded:</strong> <?php echo htmlspecialchars($doc['dou']); ?></p>
        </div>
       

        <!-- Preview the document as a PDF file using <embed> -->
        <embed src="<?php echo htmlspecialchars($doc_path); ?>" type="application/pdf" width="100%" height="600px">

        
        <br>
        <a href="importer_dashboard.php" class="back-btn">Back to Dashboard</a>
    </div>
</body>
</html>
