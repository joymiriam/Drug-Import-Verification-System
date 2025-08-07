<?php
// Start the session to access session variables like user ID and role
session_start();

// Include database connection so we can run SQL queries using $conn
include 'Connection.php';

// Access control: Only users with role 'admin' are allowed on this page
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    // If not logged in or not an admin, redirect them to the login page
    header("Location: login.html");
    exit(); // Stop script execution after redirection
}

// Retrieve the selected report type from the GET request, default to 'importers' if not set
$reportType = $_GET['reportType'] ?? 'importers';

// Retrieve the selected date filter, default to 'all' if not set
$dateFilter = $_GET['dateFilter'] ?? 'all';

// Get the current year and month as integers for comparison in date filtering
$year = date('Y');
$month = date('m');

// Initialize the SQL WHERE clause and the column to be used for filtering dates
$whereClause = '';    // This will store date filter conditions like WHERE YEAR(...) = 2025
$dateColumn = '';     // This will store which column to filter on (e.g., 'doe', 'doa')

// Determine which column to filter by depending on the report type
switch ($reportType) {
    case 'drugs':
        $dateColumn = 'doe'; // Date of entry
        break;
    case 'shipments':
        $dateColumn = 'doa'; // Date of arrival
        break;
    case 'verification':
        $dateColumn = 'dov'; // Date of verification
        break;
    default:
        $dateColumn = ''; // No date filtering for importers
}

// If a valid date column is defined, build the appropriate WHERE clause based on the selected filter
if ($dateColumn) {
    switch ($dateFilter) {
        case 'this_month':
            // Filter entries from the current month and year
            $whereClause = "WHERE MONTH($dateColumn) = $month AND YEAR($dateColumn) = $year";
            break;
        case 'last_month':
            // Calculate last month's values dynamically
            $lastMonth = date('m', strtotime('first day of last month'));
            $lastYear = date('Y', strtotime('first day of last month'));
            $whereClause = "WHERE MONTH($dateColumn) = $lastMonth AND YEAR($dateColumn) = $lastYear";
            break;
        case 'q1': // First quarter: Jan-Mar
            $whereClause = "WHERE MONTH($dateColumn) BETWEEN 1 AND 3 AND YEAR($dateColumn) = $year";
            break;
        case 'q2': // Second quarter: Apr-Jun
            $whereClause = "WHERE MONTH($dateColumn) BETWEEN 4 AND 6 AND YEAR($dateColumn) = $year";
            break;
        case 'q3': // Third quarter: Jul-Sep
            $whereClause = "WHERE MONTH($dateColumn) BETWEEN 7 AND 9 AND YEAR($dateColumn) = $year";
            break;
        case 'q4': // Fourth quarter: Oct-Dec
            $whereClause = "WHERE MONTH($dateColumn) BETWEEN 10 AND 12 AND YEAR($dateColumn) = $year";
            break;
        case 'all': // No filtering
        default:
            $whereClause = ''; // Leave it empty if all data is to be shown
            break;
    }
}

// Define SQL query based on selected report type and apply the WHERE clause if applicable
switch ($reportType) {
    case 'importers':
        // No date filtering for importers
        $sql = "SELECT imp_id, name, address, phone, email FROM importer";
        break;
    case 'drugs':
        $sql = "SELECT drug_id, name, type, manuf_id, doe FROM drugs $whereClause";
        break;
    case 'shipments':
        $sql = "SELECT ship_id, imp_id, drug_id, method, doa FROM shipment $whereClause";
        break;
    case 'verification':
        $sql = "SELECT verif_id, ship_id, status, inspector_name, dov FROM verification $whereClause";
        break;
    default:
        // Fallback to importers table if an unknown type is selected
        $sql = "SELECT imp_id, name, address, phone, email FROM importer";
}

// Execute the final SQL query using the $conn object (from Connection.php)
$result = $conn->query($sql); // $result will hold a mysqli_result object or false on failure
?>

<!DOCTYPE html>
<!-- Declares the document type as HTML5 -->
<html lang="en">
<!-- Begins the HTML document and sets the language to English -->

<head>
    <title>View Reports</title>
    <!-- Sets the title of the web page, which appears in the browser tab -->

    <link rel="stylesheet" href="styles.css">
    <!-- Links to an external CSS stylesheet for additional styling -->

    <style>
        /* Inline CSS styling begins here */
        
        body {
            font-family: Arial, sans-serif; /* Sets font style for the whole page */
            background-color: #f4f4f4; /* Light gray background */
            margin: 0; /* Removes default margin around body */
            padding: 0; /* Removes default padding around body */
        }

        .header {
            background: linear-gradient(to right, #9474af, #e5d4ef); /* Gradient background */
            color: white; /* White text color */
            padding: 15px; /* Adds space inside the header */
            text-align: center; /* Centers the header text */
            font-size: 24px; /* Increases font size */
            font-weight: bold; /* Makes text bold */
        }

        .container {
            width: 90%; /* Container takes 90% of the screen width */
            margin: auto; /* Centers the container horizontally */
            padding: 20px; /* Adds space inside the container */
            background: white; /* White background */
            border-radius: 8px; /* Rounds the corners */
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1); /* Adds a subtle shadow */
            margin-top: 20px; /* Adds space above the container */
        }

        .btn {
            padding: 8px 12px; /* Space inside the button */
            background: #9474af; /* Button background color */
            color: white; /* Text color */
            text-decoration: none; /* Removes underline */
            border-radius: 5px; /* Rounded button edges */
            border: none; /* No border line */
            cursor: pointer; /* Changes cursor to pointer on hover */
        }

        .btn:hover {
            background: #815a9b; /* Darker background on hover */
        }

        table {
            width: 100%; /* Table takes full width of container */
            border-collapse: collapse; /* Removes spacing between table cells */
            margin-top: 20px; /* Adds space above the table */
        }

        th, td {
            padding: 12px; /* Space inside cells */
            text-align: left; /* Align text to the left */
            border-bottom: 1px solid #ddd; /* Light border below each row */
        }

        th {
            background: #9474af; /* Header background color */
            color: white; /* Header text color */
        }

        tr:hover {
            background: #f1e6f7; /* Light background when row is hovered */
        }

        select, .btn {
            margin-top: 5px; /* Space above selects and buttons */
            padding: 6px; /* Padding inside dropdown and buttons */
        }

        .filters {
            margin-bottom: 15px; /* Adds space below filter form */
        }
    </style>
</head>

<body>
<!-- Start of the visible content of the webpage -->

<div class="header">
    <!-- Header section with title and navigation -->
    View Reports
    <!-- Page title text -->

    <a href="admin_dashboard.php" class="btn" style="float: right; margin-top: -5px;">Back to Dashboard</a>
    <!-- Button to go back to admin dashboard, styled to float right -->
</div>

<div class="container">
    <!-- Main content container -->

    <h2>Select Report Type</h2>
    <!-- Heading for the report filter section -->

    <form method="GET" class="filters">
        <!-- Form with GET method for filtering reports -->

        <!-- Dropdown for selecting report type (e.g., importers, drugs) -->
        <select name="reportType" onchange="this.form.submit()">
            <!-- Each <option> checks if it should be selected based on the current value of $reportType -->
            <option value="importers" <?= $reportType == 'importers' ? 'selected' : '' ?>>Importers</option>
            <option value="drugs" <?= $reportType == 'drugs' ? 'selected' : '' ?>>Drugs</option>
            <option value="shipments" <?= $reportType == 'shipments' ? 'selected' : '' ?>>Shipments</option>
            <option value="verification" <?= $reportType == 'verification' ? 'selected' : '' ?>>Verification</option>
        </select>

        <!-- Conditionally shows the date filter dropdown if the report type is NOT "importers" -->
        <?php if ($reportType !== 'importers'): ?>
        <select name="dateFilter" onchange="this.form.submit()">
            <!-- Dropdown for filtering by date range (quarter, month, etc.) -->
            <option value="all" <?= $dateFilter == 'all' ? 'selected' : '' ?>>All Time</option>
            <option value="this_month" <?= $dateFilter == 'this_month' ? 'selected' : '' ?>>This Month</option>
            <option value="last_month" <?= $dateFilter == 'last_month' ? 'selected' : '' ?>>Last Month</option>
            <option value="q1" <?= $dateFilter == 'q1' ? 'selected' : '' ?>>1st Quarter</option>
            <option value="q2" <?= $dateFilter == 'q2' ? 'selected' : '' ?>>2nd Quarter</option>
            <option value="q3" <?= $dateFilter == 'q3' ? 'selected' : '' ?>>3rd Quarter</option>
            <option value="q4" <?= $dateFilter == 'q4' ? 'selected' : '' ?>>4th Quarter</option>
        </select>
        <?php endif; ?>
    </form>

    <table>
        <!-- Table to display report data -->

        <thead>
        <!-- Table header section -->
        <tr>
            <?php if ($result->num_rows > 0): ?>
                <!-- Get the keys of the first row (column names) -->
                <?php $columns = array_keys($result->fetch_assoc()); ?>

                <!-- Loop through each column name -->
                <?php foreach ($columns as $column): ?>
                    <!-- Display each column name as a table header, formatted nicely -->
                    <th><?= ucfirst(str_replace("_", " ", $column)) ?></th>
                <?php endforeach; ?>
            <?php endif; ?>
        </tr>
        </thead>

        <tbody>
        <!-- Table body section to show data rows -->

        <?php
        // Rewind result pointer to start from first row again 
        $result->data_seek(0);

        // Loop through each row of the result
        while ($row = $result->fetch_assoc()): ?>
            <tr>
                <!-- For each column in the row -->
                <?php foreach ($row as $value): ?>
                    <!-- Output the cell data using htmlspecialchars() to prevent XSS -->
                    <td><?= htmlspecialchars($value ?? '') ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

</body>
</html>

