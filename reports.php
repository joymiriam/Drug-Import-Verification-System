<?php
session_start(); // Start the session to access session variables
include 'Connection.php'; // Include the database connection

// Ensure the user is logged in and is a PPB officer
// If not, redirect them to the login page for security
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "ppb") {
    header("Location:login.html");
    exit(); // Stop further script execution after redirect
}
// Retrieve selected date filter from the GET parameter; default is 'all'
$date_filter = $_GET['date_filter'] ?? 'all';

// Initialize dynamic WHERE clauses for use in different queries
$where_clause = "";    // For shipment-related filters
$where_verif = "";     // For verification-related filters

// Dynamically build the appropriate SQL WHERE clauses based on selected date filter
switch ($date_filter) {
    case 'this_year':
        // Filter by current year
        $where_clause = "WHERE YEAR(shipment.doa) = YEAR(CURDATE())";
        $where_verif = "WHERE YEAR(verification.dov) = YEAR(CURDATE())";
        break;

    case 'this_month':
        // Filter by current month and year
        $where_clause = "WHERE MONTH(shipment.doa) = MONTH(CURDATE()) AND YEAR(shipment.doa) = YEAR(CURDATE())";
        $where_verif = "WHERE MONTH(verification.dov) = MONTH(CURDATE()) AND YEAR(verification.dov) = YEAR(CURDATE())";
        break;

    case 'last_month':
        // Filter by last month, adjusting year 
        $where_clause = "WHERE MONTH(shipment.doa) = MONTH(CURDATE() - INTERVAL 1 MONTH) 
                         AND YEAR(shipment.doa) = YEAR(CURDATE() - INTERVAL 1 MONTH)";
        $where_verif = "WHERE MONTH(verification.dov) = MONTH(CURDATE() - INTERVAL 1 MONTH) 
                        AND YEAR(verification.dov) = YEAR(CURDATE() - INTERVAL 1 MONTH)";
        break;

    case 'this_quarter':
        // Filter by current quarter of the year
        $where_clause = "WHERE QUARTER(shipment.doa) = QUARTER(CURDATE()) AND YEAR(shipment.doa) = YEAR(CURDATE())";
        $where_verif = "WHERE QUARTER(verification.dov) = QUARTER(CURDATE()) AND YEAR(verification.dov) = YEAR(CURDATE())";
        break;

    // Filter by specific quarters (Q1, Q2, Q3, Q4) of the current year
    case 'quarter_1':
    case 'quarter_2':
    case 'quarter_3':
    case 'quarter_4':
        $quarter_num = intval(substr($date_filter, -1)); // Extract the number from the string
        $where_clause = "WHERE QUARTER(shipment.doa) = $quarter_num AND YEAR(shipment.doa) = YEAR(CURDATE())";
        $where_verif = "WHERE QUARTER(verification.dov) = $quarter_num AND YEAR(verification.dov) = YEAR(CURDATE())";
        break;

    default:
        // No filtering; show all records
        $where_clause = "";
        $where_verif = "";
}

// This query counts how often each drug appears in shipment records
$drug_sql = "SELECT drugs.name, COUNT(*) AS frequency 
             FROM shipment 
             INNER JOIN drugs ON shipment.drug_id = drugs.drug_id 
             " . ($where_clause ? $where_clause : "") . " 
             GROUP BY shipment.drug_id 
             ORDER BY frequency DESC";
$drug_result = $conn->query($drug_sql);

// This query fetches all approved verifications including drug name and date
$verified_sql = "SELECT drugs.name, verification.status, verification.dov
                 FROM verification 
                 INNER JOIN documents ON verification.doc_id = documents.doc_id 
                 INNER JOIN drugs ON documents.drug_id = drugs.drug_id 
                 " . ($where_verif ? $where_verif . " AND verification.status = 'Approved'" : "WHERE verification.status = 'Approved'");
$verified_result = $conn->query($verified_sql);

// This query fetches all rejected verifications
$failed_sql = "SELECT drugs.name, verification.status, verification.dov
               FROM verification 
               INNER JOIN documents ON verification.doc_id = documents.doc_id 
               INNER JOIN drugs ON documents.drug_id = drugs.drug_id 
               " . ($where_verif ? $where_verif . " AND verification.status = 'Rejected'" : "WHERE verification.status = 'Rejected'");
$failed_result = $conn->query($failed_sql);

// This query calculates how many approved/rejected verifications each importer has
$performance_sql = "SELECT importer.name, 
        SUM(CASE WHEN verification.status = 'Approved' THEN 1 ELSE 0 END) AS approved_count, 
        SUM(CASE WHEN verification.status = 'Rejected' THEN 1 ELSE 0 END) AS rejected_count 
    FROM verification 
    INNER JOIN documents ON verification.doc_id = documents.doc_id 
    INNER JOIN importer ON documents.imp_id = importer.imp_id 
    " . ($where_verif ? $where_verif : "") . " 
    GROUP BY importer.name";
$performance_result = $conn->query($performance_sql);

// Close the database connection once all queries have been run
$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <title>PPB Reports</title>
    <link rel="stylesheet" href="../styles.css">
    <style>
    /* Set the font, background color, and center text for the entire page */
    body { 
        font-family: Arial;                     /* Use Arial font throughout the page */
        background-color: #f4f4f4;              /* Light gray background for the body */
        text-align: center;                     /* Center-align all text inside body */
    }

    /* Style for the main container that wraps the page content */
    .container { 
        width: 95%;                             /* Set container width to 95% of the screen */
        margin: 30px auto;                      /* Add vertical margin and center horizontally */
        background: white;                      /* White background for the container */
        padding: 20px;                          /* Inner padding around content */
        border-radius: 8px;                     /* Rounded corners */
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);   /* Light drop shadow for 3D effect */
    }

    /* Style for the top header section */
    .header {
        background-color: #7a5893;              /* Purple background */
        color: white;                           /* White text color */
        padding: 20px;                          /* Inner spacing around content */
        text-align: center;                     /* Center the header text */
        position: relative;                     /* Positioning context for child elements */
        border-radius: 0 0 8px 8px;             /* Rounded corners at the bottom only */
    }

    /* Header title inside the .header */
    .header h1 {
        margin: 0;                              /* Remove default margin */
        font-size: 28px;                        /* Set font size of header title */
    }

    /* Table styles */
    table {
        width: 90%;                             /* Set table width to 90% of the page */
        margin: auto;                           /* Center the table */
        border-collapse: collapse;              /* Collapse borders into one */
    }

    /* Table cells (header and data) */
    th, td { 
        padding: 10px;                          /* Add space inside cells */
        border: 1px solid #ddd;                 /* Light gray border around cells */
    }

    /* Table headers only */
    th { 
        background-color: #7a5893;              /* Purple background */
        color: white;                           /* White text color */
    }

    /* Alternate row coloring for better readability */
    tr:nth-child(even) { 
        background-color: #f9f9f9;              /* Light gray background for even rows */
    }

    /* Sub-headings  in the page */
    h2 {
        color: #333;                            /* Dark gray text */
        text-align: left;                       /* Align text to the left */
        margin-left: 5%;                        /* Small left margin */
    }

    /* Wrapper for filter/search forms */
    .filter-form { 
        margin-bottom: 20px;                    /* Space below the form */
    }

    /* Button for going back to a previous page */
    .btn-back { 
        background: #444;                       /* Dark gray background */
        color: #fff;                            /* White text */
        right: 20px;                            /* Position from right edge (relative to parent) */
        top: 20px;                              /* Position from top edge (relative to parent) */
        padding: 8px 16px;                      /* Inner spacing (vertical and horizontal) */
        text-decoration: none;                  /* Remove underline on link */
        border-radius: 4px;                     /* Slightly rounded corners */
        float: right;                           /* Float the button to the right */
        margin-top: -50px;                      /* Move the button upwards */
    }
</style>

</head>
<body>
    <header class="header">
        <h1>PPB Reports</h1>
        <a href="ppb_dashboard.php" class="btn-back">Back to Dashboard</a>
      

    <form method="GET" class="filter-form">
        <label for="date_filter">Filter by:</label>
        <select name="date_filter" id="date_filter">
            <option value="all" <?= $date_filter == 'all' ? 'selected' : '' ?>>All Time</option>
            <option value="this_year" <?= $date_filter == 'this_year' ? 'selected' : '' ?>>This Year</option>
            <option value="this_month" <?= $date_filter == 'this_month' ? 'selected' : '' ?>>This Month</option>
            <option value="last_month" <?= $date_filter == 'last_month' ? 'selected' : '' ?>>Last Month</option>
            <option value="this_quarter" <?= $date_filter == 'this_quarter' ? 'selected' : '' ?>>This Quarter</option>
            <option value="quarter_1" <?= $date_filter == 'quarter_1' ? 'selected' : '' ?>>1st Quarter</option>
            <option value="quarter_2" <?= $date_filter == 'quarter_2' ? 'selected' : '' ?>>2nd Quarter</option>
            <option value="quarter_3" <?= $date_filter == 'quarter_3' ? 'selected' : '' ?>>3rd Quarter</option>
            <option value="quarter_4" <?= $date_filter == 'quarter_4' ? 'selected' : '' ?>>4th Quarter</option>
        </select>
        <button type="submit">Apply</button>
    </form>

      </header>

    <div class="container">
        <h2>Drug Frequency Report</h2>
        <table>
            <tr><th>Drug Name</th><th>Import Frequency</th></tr>
            <?php while ($row = $drug_result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= htmlspecialchars($row['frequency']) ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>

    <div class="container">
        <h2>Verified Drugs</h2>
        <table>
            <tr><th>Drug Name</th><th>Status</th><th>Verification Date</th></tr>
            <?php while ($row = $verified_result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= htmlspecialchars($row['status']) ?></td>
                    <td><?= htmlspecialchars($row['dov']) ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>

    <div class="container">
        <h2>Failed Drugs</h2>
        <table>
            <tr><th>Drug Name</th><th>Status</th><th>Verification Date</th></tr>
            <?php while ($row = $failed_result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= htmlspecialchars($row['status']) ?></td>
                    <td><?= htmlspecialchars($row['dov']) ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>

    <div class="container">
        <h2>Importer Performance</h2>
        <table>
            <tr><th>Importer Name</th><th>Approved Shipments</th><th>Rejected Shipments</th></tr>
            <?php while ($row = $performance_result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= htmlspecialchars($row['approved_count']) ?></td>
                    <td><?= htmlspecialchars($row['rejected_count']) ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>
</body>
</html>
