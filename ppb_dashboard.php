<?php
// Start the session to access session variables like user ID and role
session_start(); 

// Include the database connection script 
include 'Connection.php'; 
// Check if the user is logged in and their role is 'ppb' 
// If not, redirect them to the login page to prevent unauthorized access
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "ppb") {
    header("Location: login.html");
    exit(); // Ensure script stops after redirect
}

// Store the currently logged-in user's ID for later use
$user_id = $_SESSION["user_id"]; 

// Prepare SQL to get the first and last name of the logged-in user
$sql_user = "SELECT first_name, last_name FROM users WHERE user_id = ?";
$stmt_user = $conn->prepare($sql_user); // Prepare the SQL query
$stmt_user->bind_param("i", $user_id); // Bind the user_id as an integer to the query
$stmt_user->execute(); // Execute the query
$result_user = $stmt_user->get_result(); // Get the result set
$user = $result_user->fetch_assoc(); // Fetch the user's data as an associative array
$stmt_user->close(); // Close the prepared statement

// Store the full name of the inspector in session 
$_SESSION['inspector_name'] = $user['first_name'] . ' ' . $user['last_name'];

// Get total number of approved verifications, rejected ones, and total importers
$sql_summary = "
    SELECT 
        (SELECT COUNT(*) FROM verification WHERE status = 'approved') AS approved_shipments,
        (SELECT COUNT(*) FROM verification WHERE status = 'rejected') AS rejected_shipments,
        (SELECT COUNT(*) FROM importer) AS total_importers";
$result_summary = $conn->query($sql_summary); // Run the summary query
$summary = $result_summary->fetch_assoc(); // Store the returned data for dashboard display

// Base SQL to get the latest verifications along with importer names
// Using WHERE 1 allows easier appending of multiple filter conditions
$filter_sql = "
    SELECT v.status, v.dov, i.name AS importer_name
    FROM verification v
    LEFT JOIN importer i ON v.imp_id = i.imp_id
    WHERE i.name IS NOT NULL";

// Filter by Importer ID — if a specific importer was selected in the form 
if (!empty($_GET['imp_id'])) {
    $imp_id = intval($_GET['imp_id']); // Cast to integer for safety
    $filter_sql .= " AND v.imp_id = $imp_id"; // Append to query
}

// Filter by Verification Status (either 'approved' or 'rejected')
if (!empty($_GET['status'])) {
    $status = $conn->real_escape_string($_GET['status']); // Escape input to avoid injection
    $filter_sql .= " AND v.status = '$status'"; // Append filter
}

// Get today's date in YYYY-MM-DD format
$today = date('Y-m-d');

// Check which date range filter was selected by the user (if any)
switch ($_GET['date_range'] ?? '') {
    case 'this_month':
        // Filter entries from the current month and current year
        $filter_sql .= " AND MONTH(v.dov) = MONTH('$today') AND YEAR(v.dov) = YEAR('$today')";
        break;

    case 'this_year':
        // Filter entries from the current year only
        $filter_sql .= " AND YEAR(v.dov) = YEAR('$today')";
        break;

    case 'this_quarter':
        // Determine the current quarter using PHP 
        $quarter = ceil(date('n')/3); // 'n' = current month (1 to 12); ceil converts it to quarter (1 to 4)
        $filter_sql .= " AND QUARTER(v.dov) = $quarter AND YEAR(v.dov) = YEAR('$today')";
        break;

    // Handle specific quarter selections (q1, q2, q3, q4)
    case 'q1':
    case 'q2':
    case 'q3':
    case 'q4':
        // Extract the number (1–4) from the string 'q1'...'q4'
        $q = intval(substr($_GET['date_range'], 1)); // e.g. substr('q2', 1) = '2', then convert to integer
        $filter_sql .= " AND QUARTER(v.dov) = $q AND YEAR(v.dov) = YEAR('$today')";
        break;
}

// Add sorting and limit to get only the latest 20 verifications
$filter_sql .= " ORDER BY v.dov DESC LIMIT 20";

// Execute the final SQL query to get filtered results
$result_recent = $conn->query($filter_sql); 


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>PPB Official Dashboard</title>
    <link rel="stylesheet" href="styles.css">
   <style>
    /* Set basic body styling */
    body {
        font-family: Arial, sans-serif; /* Use Arial font */
        background-image: url('images/approve _p.jpg'); /* Set background image */
        background-size: cover; /* Make background cover the full page */
        background-position: center; /* Center the background image */
        background-repeat: no-repeat; /* Prevent background from repeating */
        background-attachment: fixed; /* Fix the background while scrolling */
        display: flex; /* Use flexbox for layout */
    }

    /* Sidebar styles */
    .sidebar {
        width: 250px; /* Set sidebar width */
        background: #2c3e50; /* Dark blue background color */
        color: white; /* Text color */
        height: 100vh; /* Full height of the viewport */
        padding-top: 20px; /* Space from top inside sidebar */
        position: fixed; /* Fix the sidebar position */
        margin-top: 60px; /* Move it down to avoid header overlap */
        opacity: 0.9; /* Slight transparency */
    }

    /* Sidebar links */
    .sidebar a {
        display: block; /* Make links block level */
        color: white; /* Link text color */
        padding: 15px; /* Padding inside each link */
        text-decoration: none; /* Remove underline */
        border-bottom: 1px solid #34495e; /* Border between links */
    }

    /* Sidebar hover effect */
    .sidebar a:hover {
        background: #34495e; /* Darker color on hover */
    }

    /* Content area styles */
    .content {
        margin-top: 80px; /* Space from top for header */
        margin-left: 260px; /* Leave space for sidebar */
        padding: 20px; /* Inner spacing */
        width: calc(100% - 260px); /* Take up remaining width */
        opacity: 0.9; /* Slight transparency */
    }

    /* Header (top bar) styles */
    .header {
        background: #2c3e50; /* Dark header background */
        color: white; /* Header text color */
        opacity: 0.9; /* Slightly transparent */
        padding: 15px; /* Padding inside header */
        display: flex; /* Use flexbox */
        justify-content: space-between; /* Space between logo and logout */
        align-items: center; /* Vertically center items */
        position: fixed; /* Fix at top */
        width: 100%; /* Full width */
        top: 0; /* Stick to top */
        left: 0; /* Stick to left edge */
        z-index: 1000; /* Stay on top of other content */
    }

    /* Container for summary stats */
    .stats-container {
        display: flex; /* Arrange stats in row */
        justify-content: space-between; /* Space between cards */
        margin-bottom: 20px; /* Space below */
    }

    /* Individual stat card styles */
    .card {
        background: white; /* White background */
        padding: 15px; /* Inner padding */
        border-radius: 8px; /* Rounded corners */
        text-align: center; /* Center text */
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2); /* Drop shadow */
        width: 23%; /* Width of each card */
    }

    /* Box for recent verifications */
    .recent-verifications {
        background: white; /* Background color */
        padding: 15px; /* Inner spacing */
        border-radius: 8px; /* Rounded corners */
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2); /* Shadow effect */
    }

    /* Table styles */
    table {
        width: 100%; /* Full width table */
        border-collapse: collapse; /* Remove spacing between cells */
        margin-top: 10px; /* Space above table */
    }

    /* Table header and cell styles */
    th, td {
        padding: 10px; /* Padding in table cells */
        border: 1px solid #ddd; /* Border around cells */
        text-align: left; /* Align text to left */
    }

    /* Table header background */
    th {
        background: #2c3e50; /* Dark header */
        color: white; /* White text */
    }

    /* Logout button styling */
    .logout-btn {
        background: lavenderblush; /* Light pink background */
        color: black; /* Black text */
        padding: 5px 10px; /* Button padding */
        border-radius: 5px; /* Rounded corners */
        text-decoration: none; /* No underline */
        float: right; /* Position to right */
    }

    /* Filter form styling */
    form.filter-form {
        margin-bottom: 20px; /* Space below form */
        padding: 10px; /* Inner form padding */
        background: #ecf0f1; /* Light gray background */
        border-radius: 5px; /* Rounded form corners */
    }

    /* Select and button inside form */
    form select, form button {
        margin-right: 10px; /* Space between inputs */
        padding: 5px; /* Inner padding */
    }
</style>
</head>
<body>
    <div class="header">
        <h1>Welcome, <?php echo htmlspecialchars($user['first_name']); ?>!</h1>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <div class="sidebar">
        <h2 style="text-align: center;">PPB Dashboard</h2>
        <a href="importer_verification.php">Importer Verification</a>
        <a href="drug_verification.php">Drug Verification</a>
        <a href="shipment_verification.php">Shipment Verification</a>
        <a href="manufacturer_verification.php">Manufacturer Verification</a>
        <a href="manufacturer.html"> Manufacturer Registration</a>
        <a href="document_verification.php">Document Verification</a>
        <a href="reports.php">Reports</a>
    </div>

    <div class="content">
        <div class="stats-container">
            <div class="card">
                <h3>Approved Shipments</h3>
                <p><?php echo $summary['approved_shipments']; ?></p>
            </div>
            <div class="card">
                <h3>Rejected Shipments</h3>
                <p><?php echo $summary['rejected_shipments']; ?></p>
            </div>
            <div class="card">
                <h3>Total Importers</h3>
                <p><?php echo $summary['total_importers']; ?></p>
            </div>
        </div>

        <form method="GET" class="filter-form">
            <label for="imp_id">Filter by Importer:</label>
            <select name="imp_id">
                <option value="">All</option>
                <?php
                $importers = $conn->query("SELECT imp_id, name FROM importer");
                while ($imp = $importers->fetch_assoc()) {
                    $selected = (isset($_GET['imp_id']) && $_GET['imp_id'] == $imp['imp_id']) ? "selected" : "";
                    echo "<option value='{$imp['imp_id']}' $selected>{$imp['name']}</option>";
                }
                ?>
            </select>

            <label for="status">Status:</label>
            <select name="status">
                <option value="">All</option>
                <option value="approved" <?= (isset($_GET['status']) && $_GET['status'] == 'approved') ? 'selected' : '' ?>>Approved</option>
                <option value="rejected" <?= (isset($_GET['status']) && $_GET['status'] == 'rejected') ? 'selected' : '' ?>>Rejected</option>
            </select>

            <label for="date_range">Date Range:</label>
            <select name="date_range">
                <?php
                $options = [
                    '' => 'All',
                    'this_month' => 'This Month',
                    'this_year' => 'This Year',
                    'this_quarter' => 'This Quarter',
                    'q1' => '1st Quarter',
                    'q2' => '2nd Quarter',
                    'q3' => '3rd Quarter',
                    'q4' => '4th Quarter'
                ];
                foreach ($options as $k => $v) {
                    $sel = (isset($_GET['date_range']) && $_GET['date_range']==$k) ? 'selected':'';
                    echo "<option value='$k' $sel>$v</option>";
                }
                ?>
            </select>

            <button type="submit">Apply Filters</button>
        </form>

        <div class="recent-verifications">
            <h2>Recent Verifications</h2>
            <table>
                <tr>
                    <th>Importer Name</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
                <?php while ($row = $result_recent->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['importer_name']); ?></td>
                    <td><?php echo ucfirst(htmlspecialchars($row['status'])); ?></td>
                    <td><?php echo htmlspecialchars($row['dov']); ?></td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>
    </div>
</body>
</html>
