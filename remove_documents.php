<?php
session_start();
include 'Connection.php';
include 'activity.php'; // Include the activity logging function
// Ensure the user is logged in and is an importer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'importer') {
    // Redirect unauthorized users to login
    header('Location: login.html');
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if a document ID is provided via GET request
if (!isset($_GET['doc_id'])) {
    die('Document ID not provided.');
}
// Get the document ID from the URL and convert it to an integer.
$doc_id = intval($_GET['doc_id']); 

// Get the importer ID for the currently logged-in user
$sql_importer = "SELECT imp_id FROM importer WHERE user_id = ?";
$stmt = $conn->prepare($sql_importer);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$importer = $result->fetch_assoc();
$stmt->close();

// If importer details are not found, halt the process
if (!$importer) {
    die("Importer not found.");
}

$imp_id = $importer['imp_id'];

// Confirm that the document exists and belongs to the importer
$sql_doc = "SELECT path FROM documents WHERE doc_id = ? AND imp_id = ?";
$stmt = $conn->prepare($sql_doc);
$stmt->bind_param("ii", $doc_id, $imp_id);
$stmt->execute();
$result = $stmt->get_result();
$doc = $result->fetch_assoc();
$stmt->close();

// If the document doesn't exist or isn't owned by this importer, stop
if (!$doc) {
    die("Document not found or access denied.");
}

// Get the path to the uploaded document file
$file_path = $doc['path'];

// If the file exists on the server, delete it
if (!empty($file_path) && file_exists($file_path)) {
    unlink($file_path); // Physically remove the file from the server
}

// If the document was already verified or under review, remove its status entry
$conn->query("DELETE FROM verification WHERE doc_id = $doc_id");

// Delete the document record from the documents table
$sql_delete = "DELETE FROM documents WHERE doc_id = ? AND imp_id = ?";
$stmt = $conn->prepare($sql_delete);
$stmt->bind_param("ii", $doc_id, $imp_id);
$stmt->execute();
$stmt->close();

log_activity($conn, $user_id, "Deleted document ID: $doc_id", "Document Management");

// Close the database connection
$conn->close();

// Redirect back to the importer's dashboard after successful deletion
header("Location: importer_dashboard.php");
exit();
?>
