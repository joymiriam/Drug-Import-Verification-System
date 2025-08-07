<?php
include 'Connection.php';

// Get the ship_id from the URL
$ship_id = isset($_GET['ship_id']) ? intval($_GET['ship_id']) : 0;

// Get imp_id and drug_id from shipment
$shipment_query = "SELECT imp_id, drug_id FROM shipment WHERE ship_id = $ship_id";
$shipment_result = mysqli_query($conn, $shipment_query);

// If shipment exists
if ($shipment_row = mysqli_fetch_assoc($shipment_result)) {
    $imp_id = $shipment_row['imp_id'];
    $drug_id = $shipment_row['drug_id'];

    // Now fetch the customs document based on imp_id, drug_id, and category
    $doc_query = "SELECT path FROM documents WHERE imp_id = $imp_id AND category = 'customs'";
    $doc_result = mysqli_query($conn, $doc_query);

    if ($doc_row = mysqli_fetch_assoc($doc_result)) {
        $file_path = $doc_row['path'];

        // Force download or open the file in browser
        header("Content-Type: application/pdf");
        header("Content-Disposition: inline; filename=" . basename($file_path));
        readfile($file_path);
        exit();
    } else {
        echo "Customs document not found.";
    }
} else {
    echo "Shipment not found.";
}
?>
