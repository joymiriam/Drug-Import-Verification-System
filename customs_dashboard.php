<?php
session_start(); // Start a new session.

include 'Connection.php'; // Include the database connection code. 
include 'activity.php';   // Include the custom activity logging script.

// Check if the user is logged in and has the role 'Customs'. If not, redirect them to the login page.
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "Customs") {
    header("Location: login.html"); // Redirect to login page
    exit(); // Terminate script to prevent unauthorized access
}

// Store the user ID from session in a local variable for repeated use
$user_id = $_SESSION["user_id"];

// Prepare an SQL query to get the first name of the logged-in customs officer
$stmt_user = $conn->prepare("SELECT first_name FROM users WHERE user_id = ?");
$stmt_user->bind_param("i", $user_id); // Bind user_id as an integer parameter
$stmt_user->execute();                 // Run the query
$stmt_user->bind_result($first_name); // Bind the result to the variable $first_name
$stmt_user->fetch();                  // Fetch the result into $first_name
$stmt_user->close();                 // Close the prepared statement

//function to log a user's activity
function logActivity($user_id, $action, $activity_type, $conn) {
    // Prepare insert query to log action into the activities table, with current time 
    $sql = "INSERT INTO activities (user_id, activity, activity_type, activity_time) VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $user_id, $action, $activity_type); // Bind user_id , action and type 
    $stmt->execute(); // Execute the query
    $stmt->close();   // Close the statement 
}

// If a POST request is made and shipment ID and status are present (i.e., a form was submitted)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ship_id'], $_POST['status'])) {
    $ship_id = $_POST['ship_id']; // Get shipment ID from the form
    $status = $_POST['status'];   // Get approval status (Approved or Rejected)
    $reason = isset($_POST['reason']) ? $_POST['reason'] : ''; // Get optional rejection reason if provided

    // Insert or update verification table entry. If it already exists, update it.
    $stmt = $conn->prepare("INSERT INTO verification (ship_id, status, rejection_reason) VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE status = VALUES(status), rejection_reason = VALUES(rejection_reason)");
    $stmt->bind_param("iss", $ship_id, $status, $reason);

    // If update is successful, log the activity
    if ($stmt->execute()) {
        $message = "Shipment status updated successfully."; // Feedback message 
        // Create action log message
        $action = ($status == "Approved") ? "Approved shipment ID $ship_id" : "Rejected shipment ID $ship_id";
        logActivity($user_id, $action, "Shipment Verification", $conn); // Log the activity
    } else {
        $message = "Error updating shipment status."; // Error feedback
    }

    $stmt->close(); // Close statement after execution
}

// Get summary counts of pending, approved, and rejected shipments using conditional SQL aggregation
$sql_summary = "SELECT 
    SUM(CASE WHEN v.status IS NULL OR v.status = 'Pending' THEN 1 ELSE 0 END) AS pending,
    SUM(CASE WHEN v.status = 'Approved' THEN 1 ELSE 0 END) AS approved,
    SUM(CASE WHEN v.status = 'Rejected' THEN 1 ELSE 0 END) AS rejected
    FROM shipment s
    LEFT JOIN verification v ON s.ship_id = v.ship_id";

// Execute query and fetch result as an associative array
$summary = $conn->query($sql_summary)->fetch_assoc();

// Query distinct importer names for filter dropdown
// The DISTINCT keyword ensures that duplicate names are removed.
$importerOptions = $conn->query("SELECT DISTINCT name FROM importer ORDER BY name ASC");

// Query distinct port of entry values for filter dropdown
$portOptions = $conn->query("SELECT DISTINCT port_of_entry FROM shipment ORDER BY port_of_entry ASC");

// Get filter values from GET request (URL parameters), or default to empty string
$importer = $_GET['importer'] ?? '';
$port = $_GET['port'] ?? '';
$date_range = $_GET['date_range'] ?? '';

// Query to get pending or unverified shipments
$sql = "SELECT 
    s.ship_id, i.name AS importer_name, s.method, s.company, s.port_of_entry, s.doa,
    d.name AS drug_name,
    (SELECT path FROM documents 
     WHERE category = 'customs' 
     AND imp_id = s.imp_id 
     AND (drug_id IS NULL OR drug_id = s.drug_id)
    ORDER BY dou DESC LIMIT 1) AS customs_doc
FROM shipment AS s
JOIN importer AS i ON s.imp_id = i.imp_id
LEFT JOIN drugs AS d ON s.drug_id = d.drug_id
LEFT JOIN verification AS v ON s.ship_id = v.ship_id
WHERE v.status IS NULL OR v.status = 'Pending'
";

// Add importer filter if selected
if (!empty($importer)) {
    $safe_importer = $conn->real_escape_string($importer); // Prevent SQL injection
    $sql .= " AND i.name = '$safe_importer'";
}

// Add port filter if selected
if (!empty($port)) {
    $safe_port = $conn->real_escape_string($port); // Prevent SQL injection
    $sql .= " AND s.port_of_entry = '$safe_port'";
}

// Add date range filter using MySQL date functions
if (!empty($date_range)) {
    switch ($date_range) {
        case 'this_month':
            $sql .= " AND MONTH(s.doa) = MONTH(CURDATE()) AND YEAR(s.doa) = YEAR(CURDATE())";
            break;
        case 'this_year':
            $sql .= " AND YEAR(s.doa) = YEAR(CURDATE())";
            break;
        case 'this_quarter':
            $sql .= " AND QUARTER(s.doa) = QUARTER(CURDATE()) AND YEAR(s.doa) = YEAR(CURDATE())";
            break;
        case 'q1': case 'q2': case 'q3': case 'q4':
            $quarter = (int)substr($date_range, 1); // Extract quarter number from string 
            $sql .= " AND QUARTER(s.doa) = $quarter AND YEAR(s.doa) = YEAR(CURDATE())";
            break;
    }
}

$sql .= " ORDER BY s.doa DESC"; // Order results with latest shipment first
$result = $conn->query($sql);  // Run the final SQL query
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Customs Dashboard</title>
    <link rel="stylesheet" href="styles.css">
<style>
    /* Style for the entire body of the page */
    body {
        font-family: Arial, sans-serif; /* Sets the font for the entire page */
        background-image: url('images/manufacturer.png'); /* Sets a background image */
        color: #333; /* Sets default text color to dark gray */
        margin: 0; /* Removes default body margin */
        padding: 0; /* Removes default body padding */
    }

    /* Style for the top header bar */
    .header {
        background: linear-gradient(to right, #9474af, #e5d4ef); /* Gradient background from purple to light purple */
        color: #333; /* Text color inside header */
        padding: 15px; /* Space inside the header */
        display: flex; /* Enables flex layout */
        justify-content: space-between; /* Space out items horizontally */
        align-items: center; /* Vertically center items */
    }

    /* Style for the logout button */
    .logout_btn {
        background-color: gray; /* Button background color */
        color: white; /* Text color */
        padding: 10px 15px; /* Top-bottom and left-right padding */
        text-decoration: none; /* Removes underline from link */
        border-radius: 5px; /* Rounds the corners of the button */
        font-weight: bold; /* Makes the button text bold */
    }

    /* Container for the main content */
    .container {
        width: 80%; /* Makes the container 80% of the page width */
        margin: auto; /* Centers the container horizontally */
        background: #fff; /* White background */
        opacity: 0.95; /* Slight transparency for background */
        padding: 20px; /* Padding inside the container */
        border-radius: 8px; /* Rounded corners */
        margin-top: 20px; /* Adds space above the container */
    }

    /* Container for statistics boxes/cards */
    .stats-container {
        display: flex; /* Arrange stat cards in a row */
        justify-content: space-between; /* Even spacing between stat cards */
        margin-bottom: 20px; /* Space below the stat section */
    }

    /* Individual stat cards */
    .card {
        background: white; /* White background for the card */
        padding: 15px; /* Padding inside the card */
        border-radius: 8px; /* Rounded card corners */
        text-align: center; /* Center-align text in the card */
        width: 30%; /* Card takes up 30% of the row */
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2); /* Subtle shadow around the card */
    }

    /* Table styling */
    table {
        width: 100%; /* Table takes full width of container */
        border-collapse: collapse; /* Removes spacing between table borders */
    }

    /* Table header and data cell styling */
    th, td {
        border: 1px solid #ddd; /* Light gray border for table cells */
        padding: 10px; /* Padding inside each cell */
        text-align: left; /* Align text to the left */
    }

    /* Table header specific styling */
    th {
        background-color: #9474af; /* Purple background for headers */
        color: #fff; /* White text color for headers */
    }

    /* Base style for action buttons */
    .btn {
        padding: 10px 15px; /* Padding inside button */
        border: none; /* No border around button */
        cursor: pointer; /* Cursor turns to pointer on hover */
        border-radius: 5px; /* Rounded corners */
        font-size: 14px; /* Text size */
    }

    /* Approve button specific styling */
    .btn-approve {
        background-color: #4CAF50; /* Green background */
        color: white; /* White text */
        margin-right: 4px; /* Space between approve and reject buttons */
    }

    /* Reject button specific styling */
    .btn-reject {
        background-color: #f44336; /* Red background */
        color: white; /* White text */
    }
</style>

</head>
<body>
    <div class="header">
        <h1>Welcome, <?= htmlspecialchars($first_name) ?></h1>
        <a href="logout.php" class="logout_btn">Logout</a>
    </div>
    <div class="container">
        <div class="stats-container">
            <div class="card"><h3>Pending Shipments</h3><p><?= $summary['pending'] ?></p></div>
            <div class="card"><h3>Approved Shipments</h3><p><?= $summary['approved'] ?></p></div>
            <div class="card"><h3>Rejected Shipments</h3><p><?= $summary['rejected'] ?></p></div>
        </div>

        <form method="get" style="margin-bottom: 20px;">
            <label>Importer Name:</label>
            <select name="importer">
                <option value="">All</option>
                <?php while ($row = $importerOptions->fetch_assoc()): ?>
                    <option value="<?= htmlspecialchars($row['name']) ?>" <?= ($importer === $row['name']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($row['name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label>Port of Entry:</label>
            <select name="port">
                <option value="">All</option>
                <?php while ($row = $portOptions->fetch_assoc()): ?>
                    <option value="<?= htmlspecialchars($row['port_of_entry']) ?>" <?= ($port === $row['port_of_entry']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($row['port_of_entry']) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label>Date Range:</label>
            <select name="date_range">
                <option value="">All</option>
                <option value="this_month" <?= $date_range == 'this_month' ? 'selected' : '' ?>>This Month</option>
                <option value="this_year" <?= $date_range == 'this_year' ? 'selected' : '' ?>>This Year</option>
                <option value="this_quarter" <?= $date_range == 'this_quarter' ? 'selected' : '' ?>>This Quarter</option>
                <option value="q1" <?= $date_range == 'q1' ? 'selected' : '' ?>>1st Quarter</option>
                <option value="q2" <?= $date_range == 'q2' ? 'selected' : '' ?>>2nd Quarter</option>
                <option value="q3" <?= $date_range == 'q3' ? 'selected' : '' ?>>3rd Quarter</option>
                <option value="q4" <?= $date_range == 'q4' ? 'selected' : '' ?>>4th Quarter</option>
            </select>

            <button type="submit">Apply Filters</button>
        </form>

        <a href="customs_report.php" class="logout_btn">View Reports</a>
        <h2>Customs Shipment Verification</h2>

<?php if ($result->num_rows > 0): ?>
    <table>
        <thead>
            <tr>
                <th>Shipment ID</th>
                <th>Importer Name</th>
                <th>Method</th>
                <th>Company</th>
                <th>Port of Entry</th>
                <th>Date of Arrival</th>
                <th>Drug Name</th>
                <th>Customs Document</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['ship_id']); ?></td>
                    <td><?php echo htmlspecialchars($row['importer_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['method']); ?></td>
                    <td><?php echo htmlspecialchars($row['company']); ?></td>
                    <td><?php echo htmlspecialchars($row['port_of_entry']); ?></td>
                    <td><?php echo htmlspecialchars($row['doa']); ?></td>
                    <td><?php echo htmlspecialchars($row['drug_name']); ?></td>
                    <td>
                        <?php if (!empty($row['customs_doc'])): ?>
                           
                           <a href="view_Customs_Documents.php?ship_id=<?= $row['ship_id'] ?>" target="_blank">View</a>

                        <?php else: ?>
                            Not Available
                        <?php endif; ?>
                    </td>
                    <td>
                         <form method="post">
                                <input type="hidden" name="ship_id" value="<?= $row['ship_id'] ?>">
                                <button type="submit" name="status" value="Approved" class="btn btn-approve">Approve</button>
                                <button type="button" onclick="this.nextElementSibling.style.display='block'" class="btn btn-reject">Reject</button>
                                <div style="display: none;">
                                    <input type="text" name="reason" placeholder="Reason for rejection">
                                    <button type="submit" name="status" value="Rejected" class="btn btn-reject">Confirm Reject</button>
                                </div>
                            </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>No pending shipments found.</p>
<?php endif; ?>

    </div>
</body>
</html>
