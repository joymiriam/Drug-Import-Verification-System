<?php
// Start a session
session_start();

// Include the database connection file 
include 'Connection.php';

// ensure that only users logged in and assigned the role customs can access this page
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "Customs") {
    // If the user is not logged in or not a customs officer, redirect them to the login page
    header("Location: login.html");
    exit(); // Stop script execution after redirect
}

// Get the selected filter from the URL (GET request); if not set, default to all
$filter = $_GET['filter'] ?? 'all';

// Initialize the WHERE clause 
$where_clause = '';

// Determine which date filter to apply based on the value of $filter
switch ($filter) {
    case 'this_month':
        // Filter for shipments with date of arrival (doa) in the current month and year
        $where_clause = "WHERE MONTH(doa) = MONTH(CURRENT_DATE()) AND YEAR(doa) = YEAR(CURRENT_DATE())";
        break;

    case 'last_month':
        // Filter for shipments that arrived last month
        $where_clause = "WHERE MONTH(doa) = MONTH(CURRENT_DATE - INTERVAL 1 MONTH) 
                         AND YEAR(doa) = YEAR(CURRENT_DATE - INTERVAL 1 MONTH)";
        break;

    case 'q1':
        // Filter for Quarter 1 (January to March)
        $where_clause = "WHERE MONTH(doa) BETWEEN 1 AND 3";
        break;

    case 'q2':
        // Filter for Quarter 2 (April to June)
        $where_clause = "WHERE MONTH(doa) BETWEEN 4 AND 6";
        break;

    case 'q3':
        // Filter for Quarter 3 (July to September)
        $where_clause = "WHERE MONTH(doa) BETWEEN 7 AND 9";
        break;

    case 'q4':
        // Filter for Quarter 4 (October to December)
        $where_clause = "WHERE MONTH(doa) BETWEEN 10 AND 12";
        break;

    case 'all':
    default:
        // No filter is applied, meaning all shipments will be included
        $where_clause = '';
        $filter = 'all';
        break;
}

// Query to count the number of shipments per month, optionally filtered by the selected condition
$sql_monthly = "SELECT DATE_FORMAT(doa, '%Y-%m') AS month, COUNT(*) AS total 
    FROM shipment " . 
      ($where_clause ? "$where_clause AND doa IS NOT NULL" : "WHERE doa IS NOT NULL") . "
    GROUP BY month ORDER BY month DESC";

// Run the query on the database and store the result
$result_monthly = $conn->query($sql_monthly);

// Fetch all results into an associative array for easy access 
$monthly_data = $result_monthly->fetch_all(MYSQLI_ASSOC);

// Query to count how many shipments were approved and how many were rejected from the verification table
$sql_trends = "SELECT 
    SUM(CASE WHEN v.status = 'Approved' THEN 1 ELSE 0 END) AS approved,
    SUM(CASE WHEN v.status = 'Rejected' THEN 1 ELSE 0 END) AS rejected
    FROM verification v";

// Execute the trends query
$result_trends = $conn->query($sql_trends);

// Get a single associative array with keys approved and rejected
$trends = $result_trends->fetch_assoc();

// Query to find the top 5 most common rejection reasons in the verification table
$sql_reasons = "SELECT rejection_reason, COUNT(*) AS count 
    FROM verification 
    WHERE status = 'Rejected' 
    GROUP BY rejection_reason 
    ORDER BY count DESC 
    LIMIT 5";

// Run the query
$result_reasons = $conn->query($sql_reasons);

// Fetch all results as associative array for use in charts or tables
$reasons = $result_reasons->fetch_all(MYSQLI_ASSOC);

// Query to find the top 5 importers based on the number of shipments
$sql_importers = "SELECT i.name AS importer_name, COUNT(s.ship_id) AS total_shipments
    FROM shipment s 
    JOIN importer i ON s.imp_id = i.imp_id 
    GROUP BY i.imp_id 
    ORDER BY total_shipments DESC 
    LIMIT 5";

// Run the query
$result_importers = $conn->query($sql_importers);

// Fetch all rows as associative array for display in dashboard
$importers = $result_importers->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Customs Reports</title>
    <link rel="stylesheet" href="styles.css">
<style>
    /* Set the default font, background color, and remove margin/padding for the body */
    body {
        font-family: Arial, sans-serif; /* Use Arial or sans-serif fonts */
        background-color: #f4f4f9;      /* Light greyish background */
        margin: 0;                      /* Remove default margin */
        padding: 0;                     /* Remove default padding */
        color: #333;                    /* Dark text color for readability */
    }

    /* Style for the 'Back' button inside the header */
    .header .back_btn {
        background-color: gray;         /* Set button background to gray */
        color: white;                   /* Set text color to white */
        text-decoration: none;          /* Remove underline from link */
        font-size: 16px;                /* Set font size */
        padding: 8px 15px;              /* Add padding around the text */
        border-radius: 5px;             /* Slightly round the button corners */
        position: absolute;             /* Position it absolutely in the header */
        top: 15px;                      /* Distance from top of the header */
        left: 20px;                     /* Distance from left of the header */
    }

    /* Change back button background color on hover */
    .header .back_btn:hover {
        background-color: darkgray;     /* Darker shade when hovered */
    }

    /* Container that wraps the content */
    .container {
        width: 90%;                     /* Take 90% of the page width */
        max-width: 1000px;              /* But not more than 1000px wide */
        margin: 20px auto;              /* Center it with vertical margin */
        background: #fff;               /* White background for the container */
        padding: 20px;                  /* Add space inside the box */
        border-radius: 8px;             /* Slightly rounded corners */
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); /* Soft shadow effect */
    }

    /* Header section at the top of the page */
    .header {
        background: linear-gradient(to right, #9474af, #e5d4ef); /* Purple gradient background */
        color: white;                  /* White text color */
        padding: 15px;                 /* Padding inside the header */
        text-align: center;            /* Center-align header text */
        font-size: 20px;               /* Large text size */
        font-weight: bold;            /* Bold header text */
    }

    /* Anchor link inside header (same as back_btn but fallback) */
    .header a {
        color: white;                  /* White link text */
        text-decoration: none;        /* Remove underline */
        font-size: 16px;              /* Font size for the link */
        padding: 8px 15px;            /* Padding for button look */
        border-radius: 5px;           /* Rounded edges */
        background-color: #9474af;    /* Purple background */
        position: absolute;           /* Positioned relative to the header */
        top: 15px;                    /* Distance from top of header */
        left: 20px;                   /* Distance from left */
    }

    /* Hover effect for anchor link */
    .header a:hover {
        background-color: rgb(154, 115, 186); /* Lighter purple on hover */
    }

    /* Styling the table that displays records */
    table {
        width: 100%;                  /* Table takes full width */
        border-collapse: collapse;   /* Merge table borders */
        margin-top: 15px;            /* Space above the table */
        background: white;           /* White background for table */
        border-radius: 5px;          /* Rounded edges */
        overflow: hidden;            /* Hide overflow */
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); /* Light shadow for depth */
    }

    /* Table header and cell spacing */
    th, td {
        padding: 12px;               /* Inner cell padding */
        text-align: left;           /* Left-align text */
        border-bottom: 1px solid #ddd; /* Line below each row */
    }

    /* Header row styling */
    th {
        background: #9474af;         /* Purple header background */
        color: white;                /* White text in header */
        font-weight: bold;           /* Bold header text */
    }

    /* Alternate row background for better readability */
    tr:nth-child(even) {
        background-color: #f9f9f9;   /* Light grey for every even row */
    }

    /* Hover effect for rows */
    tr:hover {
        background-color: #f1f1f1;   /* Highlight row on hover */
    }

    /* Section title styling */
    h2 {
        color: black;                /* Black text for section headings */
        border-bottom: 2px solid #9474af; /* Bottom border in purple */
        padding-bottom: 5px;         /* Space between text and border */
        margin-top: 20px;            /* Space above the heading */
    }

    /* Paragraph message styling (e.g. success or info messages) */
    p {
        font-size: 18px;             /* Slightly bigger font */
        font-weight: bold;           /* Bold text */
        background: rgb(247, 216, 255); /* Light purple background */
        padding: 10px;               /* Padding inside message box */
        border-radius: 5px;          /* Rounded corners */
        display: inline-block;       /* Only as wide as content */
        margin-top: 10px;            /* Space above paragraph */
    }

    /* Form spacing control */
    form {
        margin-top: 10px;            /* Space above the form */
        margin-bottom: 20px;         /* Space below the form */
    }

    /* Dropdowns and buttons input styling */
    select, button {
        padding: 8px;                /* Padding inside buttons and selects */
        margin-right: 5px;           /* Space between filter and button */
        font-size: 16px;             /* Readable text */
        border-radius: 5px;          /* Rounded edges */
        border: 1px solid #ccc;      /* Light border */
    }

    /* Button specific styling */
    button {
        background-color: #9474af;   /* Purple background */
        color: white;                /* White text */
        cursor: pointer;             /* Cursor becomes pointer on hover */
    }

    /* Change button color on hover */
    button:hover {
        background-color: #7b5e9d;   
    }
</style>


</head>
<body>
    <div class="header">
        <h1>Customs Reports</h1>
        <a href="customs_dashboard.php" class="back_btn">Back to Dashboard</a>
    </div>
    <div class="container">
        <form method="GET">
            <label for="filter">Filter by Date:</label>
            <select name="filter" id="filter">
                <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Time</option>
                <option value="this_month" <?= $filter === 'this_month' ? 'selected' : '' ?>>This Month</option>
                <option value="last_month" <?= $filter === 'last_month' ? 'selected' : '' ?>>Last Month</option>
                <option value="q1" <?= $filter === 'q1' ? 'selected' : '' ?>>1st Quarter </option>
                <option value="q2" <?= $filter === 'q2' ? 'selected' : '' ?>>2nd Quarter </option>
                <option value="q3" <?= $filter === 'q3' ? 'selected' : '' ?>>3rd Quarter </option>
                <option value="q4" <?= $filter === 'q4' ? 'selected' : '' ?>>4th Quarter </option>
            </select>
            <button type="submit">Apply</button>
        </form>

        <h2>Total Shipments Processed per Month</h2>
        <table>
            <tr><th>Month</th><th>Total Shipments</th></tr>
            <?php foreach ($monthly_data as $row): ?>
                <tr><td><?= htmlspecialchars($row['month']) ?></td><td><?= $row['total'] ?></td></tr>
            <?php endforeach; ?>
        </table>

        <h2>Approval vs. Rejection Trends</h2>
        <p>Approved: <?= $trends['approved'] ?> | Rejected: <?= $trends['rejected'] ?></p>

        <h2>Most Common Rejection Reasons</h2>
        <table>
            <tr><th>Reason</th><th>Count</th></tr>
            <?php foreach ($reasons as $row): ?>
                <tr><td><?= htmlspecialchars($row['rejection_reason']) ?></td><td><?= $row['count'] ?></td></tr>
            <?php endforeach; ?>
        </table>

        <h2>Top Importers by Shipment Volume</h2>
        <table>
            <tr><th>Importer</th><th>Total Shipments</th></tr>
            <?php foreach ($importers as $row): ?>
                <tr><td><?= htmlspecialchars($row['importer_name']) ?></td><td><?= $row['total_shipments'] ?></td></tr>
            <?php endforeach; ?>
        </table>
    </div>
</body>
</html>
