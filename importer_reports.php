<?php
session_start(); // Start the PHP session to track user data across pages
include 'Connection.php'; // Include the database connection 

// Check if the user is logged in and has the role of 'importer'
// If not, redirect them to the login page to prevent unauthorized access
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "importer") {
    header("Location: login.html"); 
    exit();
}

// Get the logged-in user's ID from session
$user_id = $_SESSION["user_id"];

// Fetch importer details (like ID and company name) from the database using user_id
$sql_importer = "SELECT imp_id, name FROM importer WHERE user_id = ?";
$stmt_importer = $conn->prepare($sql_importer); // Prepare the SQL query to prevent SQL injection
$stmt_importer->bind_param("i", $user_id); // Bind the user_id as an integer parameter
$stmt_importer->execute(); // Execute the prepared query
$result_importer = $stmt_importer->get_result(); // Get the result set from the executed statement
$importer = $result_importer->fetch_assoc(); // Fetch the single row result as an associative array
$stmt_importer->close(); // Close the statement

// Get the selected filter for date range from the GET request, default to 'all'
$date_filter = $_GET['date_filter'] ?? 'all';

// Initialize WHERE clause strings for shipments and documents queries
$where_clause_shipment = "";
$where_clause_docs = "";

// Dynamically build the WHERE clauses based on the selected date filter
switch ($date_filter) {
    case 'this_year':
        // Matches all records from the current calendar year
        $where_clause_shipment = "AND YEAR(shipment.doa) = YEAR(CURDATE())";
        $where_clause_docs = "AND YEAR(documents.dou) = YEAR(CURDATE())";
        break;
    case 'this_month':
        // Filter for current month and year
        $where_clause_shipment = "AND MONTH(shipment.doa) = MONTH(CURDATE()) AND YEAR(shipment.doa) = YEAR(CURDATE())";
        $where_clause_docs = "AND MONTH(documents.dou) = MONTH(CURDATE()) AND YEAR(documents.dou) = YEAR(CURDATE())";
        break;
    case 'last_month':
        // Matches records from exactly one month before the current date uses INTERVAL 1 MONTH to calculate previous month 
        $where_clause_shipment = "AND MONTH(shipment.doa) = MONTH(CURDATE() - INTERVAL 1 MONTH) AND YEAR(shipment.doa) = YEAR(CURDATE() - INTERVAL 1 MONTH)";
        $where_clause_docs = "AND MONTH(documents.dou) = MONTH(CURDATE() - INTERVAL 1 MONTH) AND YEAR(documents.dou) = YEAR(CURDATE() - INTERVAL 1 MONTH)";
        break;
    case 'this_quarter':
        // Filter for the current quarter of the year
        $where_clause_shipment = "AND QUARTER(shipment.doa) = QUARTER(CURDATE()) AND YEAR(shipment.doa) = YEAR(CURDATE())";
        $where_clause_docs = "AND QUARTER(documents.dou) = QUARTER(CURDATE()) AND YEAR(documents.dou) = YEAR(CURDATE())";
        break;
    case 'quarter_1':
        // Filter for Q1 (Jan-Mar)
        $where_clause_shipment = "AND QUARTER(shipment.doa) = 1 AND YEAR(shipment.doa) = YEAR(CURDATE())";
        $where_clause_docs = "AND QUARTER(documents.dou) = 1 AND YEAR(documents.dou) = YEAR(CURDATE())";
        break;
    case 'quarter_2':
        // Filter for Q2 (Apr-Jun)
        $where_clause_shipment = "AND QUARTER(shipment.doa) = 2 AND YEAR(shipment.doa) = YEAR(CURDATE())";
        $where_clause_docs = "AND QUARTER(documents.dou) = 2 AND YEAR(documents.dou) = YEAR(CURDATE())";
        break;
    case 'quarter_3':
        // Filter for Q3 (Jul-Sep)
        $where_clause_shipment = "AND QUARTER(shipment.doa) = 3 AND YEAR(shipment.doa) = YEAR(CURDATE())";
        $where_clause_docs = "AND QUARTER(documents.dou) = 3 AND YEAR(documents.dou) = YEAR(CURDATE())";
        break;
    case 'quarter_4':
        // Filter for Q4 (Oct-Dec)
        $where_clause_shipment = "AND QUARTER(shipment.doa) = 4 AND YEAR(shipment.doa) = YEAR(CURDATE())";
        $where_clause_docs = "AND QUARTER(documents.dou) = 4 AND YEAR(documents.dou) = YEAR(CURDATE())";
        break;
    default:
        // If 'all' is selected or an unknown value, do not filter by date
        $where_clause_shipment = "";
        $where_clause_docs = "";
}

// Query to fetch historical import data for the importer
$sql_imports = "SELECT shipment.ship_id, drugs.name AS drug_name, drugs.quantity, shipment.doa, shipment.method, shipment.company, shipment.port_of_entry 
                FROM shipment 
                INNER JOIN drugs ON shipment.drug_id = drugs.drug_id 
                WHERE shipment.imp_id = ? $where_clause_shipment
                ORDER BY shipment.doa DESC";
$stmt_imports = $conn->prepare($sql_imports); // Prepare the query
$stmt_imports->bind_param("i", $importer['imp_id']); // Bind importer ID
$stmt_imports->execute(); // Execute
$result_imports = $stmt_imports->get_result(); // Get results
$stmt_imports->close(); // Close statement

// Query to fetch document uploads and their verification status
$sql_docs = "SELECT documents.doc_id, documents.identification_number, documents.category, documents.dou, COALESCE(verification.status, 'Pending') AS status 
             FROM documents 
             LEFT JOIN verification ON documents.doc_id = verification.doc_id 
             WHERE documents.imp_id = ? $where_clause_docs
             ORDER BY documents.dou DESC";
$stmt_docs = $conn->prepare($sql_docs); // Prepare query
$stmt_docs->bind_param("i", $importer['imp_id']); // Bind importer ID
$stmt_docs->execute(); // Execute
$result_docs = $stmt_docs->get_result(); // Get result set
$stmt_docs->close(); // Close statement

$conn->close(); // Close the database connection
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Importer Reports</title>
    <link rel="stylesheet" href="styles.css">
<style>
    /* Style the entire page */
    body {
        font-family: Arial, sans-serif; /* Use a clean sans-serif font */
        background-color: #f4f4f4;      /* Light grey background for the page */
        text-align: center;             /* Center-align all text on the page */
    }

    /* Style for the main content container */
    .container {
        opacity: 0.9; /* Slight transparency effect to give a soft overlay look */
        width: 100%;  /* Take full width of the page */
        max-width: 1100px; /* But limit the maximum width to 1100px for better readability */
        margin: 50px auto; /* Add space above and below, center horizontally */
        padding: 20px; /* Inner spacing between content and container edges */
        background: white; /* White background for the container to stand out */
        border-radius: 10px; /* Rounded corners for a modern look */
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); /* Subtle shadow around the box */
    }

    /* Style for the table inside the container */
    table {
        width: 80%; /* Make the table take 80% of the container's width */
        border-collapse: collapse; /* Merge borders for a clean look */
        margin: auto; /* Center the table horizontally */
    }

    /* Style for all table header and data cells */
    th, td {
        padding: 12px; /* Space inside each table cell */
        border: 1px solid #ddd; /* Light grey border around each cell */
        text-align: center; /* Center-align content in both headers and cells */
    }

    /* Style specifically for header cells */
    th {
        background-color: #9474af; /* Purple background for headers */
        color: white; /* White text color for contrast */
    }

    /* Alternate row shading for better readability */
    tr:nth-child(even) {
        background-color: #f9f9f9; /* Light grey background on even rows */
    }

    /* Style for the back button */
    .btn-back {
        background-color: #555; /* Dark grey button background */
        color: white; /* White text color */
        display: inline-block; /* Allows setting width and margin */
        padding: 10px 20px; /* Padding inside the button */
        border-radius: 5px; /* Slightly rounded corners */
        text-decoration: none; /* Remove underline from links */
    }

    /* Container to right-align the back button */
    .back-container {
        text-align: right; /* Align child elements to the right */
        width: 100%; /* Full width to push the button to the right */
    }

    /* Style for the filter form area (if any filter dropdowns or inputs are present) */
    .filter-form {
        margin: 20px 0; /* Add spacing above and below the filter form */
    }
</style>

</head>
<body>
    <header>
        <h1>Importer Reports</h1>
        <a href="importer_dashboard.php" class="btn-back">Back to Dashboard</a>
    </header>

    <div class="container">
        <h2>Import History Report</h2>
        <form method="GET" class="filter-form">
            <label for="date_filter">Filter by Date:</label>
            <select name="date_filter" id="date_filter">
                <option value="all" <?= $date_filter == 'all' ? 'selected' : '' ?>>All Time</option>
                <option value="this_year" <?= $date_filter == 'this_year' ? 'selected' : '' ?>>This Year</option>
                <option value="this_month" <?= $date_filter == 'this_month' ? 'selected' : '' ?>>This Month</option>
                <option value="last_month" <?= $date_filter == 'last_month' ? 'selected' : '' ?>>Last Month</option>
                <option value="quarter_1" <?= $date_filter == 'quarter_1' ? 'selected' : '' ?>>1st Quarter</option>
                <option value="quarter_2" <?= $date_filter == 'quarter_2' ? 'selected' : '' ?>>2nd Quarter</option>
                <option value="quarter_3" <?= $date_filter == 'quarter_3' ? 'selected' : '' ?>>3rd Quarter</option>
                <option value="quarter_4" <?= $date_filter == 'quarter_4' ? 'selected' : '' ?>>4th Quarter</option>

            </select>
            <button type="submit">Apply</button>
        </form>

        <table>
            <thead>
                <tr>
                    <th>Drug Name</th>
                    <th>Quantity</th>
                    <th>Date Imported</th>
                    <th>Method</th>
                    <th>Company</th>
                    <th>Port of Entry</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result_imports->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['drug_name']) ?></td>
                        <td><?= htmlspecialchars($row['quantity']) ?></td>
                        <td><?= htmlspecialchars($row['doa']) ?></td>
                        <td><?= htmlspecialchars($row['method']) ?></td>
                        <td><?= htmlspecialchars($row['company']) ?></td>
                        <td><?= htmlspecialchars($row['port_of_entry']) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div class="container">
        <h2>Document Submission Report</h2>
        <table>
            <thead>
                <tr>
                    <th>Identification Number</th>
                    <th>Category</th>
                    <th>Date Uploaded</th>
                    <th>Verification Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($doc = $result_docs->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($doc['identification_number']) ?></td>
                        <td><?= htmlspecialchars($doc['category']) ?></td>
                        <td><?= htmlspecialchars($doc['dou']) ?></td>
                        <td><?= htmlspecialchars($doc['status']) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
