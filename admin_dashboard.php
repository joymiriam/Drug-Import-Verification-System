<?php
// Start the session so that session variables like user_id and role can be accessed
session_start();

// Include the database connection file to use the $conn variable to run queries
include 'Connection.php';

// Check if a user is NOT logged in OR the logged-in user is NOT an admin
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    // If either condition is true, redirect to login page
    header("Location: login.html");
    exit(); // Stop executing further code
}

// Get the currently logged-in user's ID from session
$user_id = $_SESSION["user_id"];

// Prepare an SQL statement to get the admin’s first name for the welcome message
$stmt_user = $conn->prepare("SELECT first_name FROM users WHERE user_id = ?");
$stmt_user->bind_param("i", $user_id); // Bind the user_id as an integer
$stmt_user->execute();
$stmt_user->bind_result($first_name);  // Get the result of the query into $first_name
$stmt_user->fetch();                   // Fetch the row from the database
$stmt_user->close();                   

// Get counts of users by role for dashboard stats
$sql_summary = "SELECT 
    COUNT(CASE WHEN role = 'Importer' THEN 1 END) AS importers,
    COUNT(CASE WHEN role = 'Customs' THEN 1 END) AS customs,
    COUNT(CASE WHEN role = 'PPB' THEN 1 END) AS ppb
    FROM users";

// Run the query and fetch the result as an associative array 
// An associative array in PHP is an array where each value is assigned to a named key (a string) instead of a numeric index.
$summary = $conn->query($sql_summary)->fetch_assoc();

// Handle filtering of activity logs by time period
// Get selected filter from the GET request (URL parameter), default to empty string if not set
$date_range = $_GET['log_range'] ?? ''; // `??` is a null coalescing operator (returns '' if not set)

// Start with no filter
$where = '';

// Check what filter is selected and build appropriate WHERE clause
// Uses MySQL functions like MONTH(), YEAR(), and QUARTER() to match activity_time.
if (!empty($date_range)) {
    switch ($date_range) {
        case 'this_month':
            // Filter logs for the current month and year
            $where = "WHERE MONTH(activity_time) = MONTH(CURDATE()) AND YEAR(activity_time) = YEAR(CURDATE())";
            break;
        case 'this_year':
            // Filter logs for the current year
            $where = "WHERE YEAR(activity_time) = YEAR(CURDATE())";
            break;
        case 'this_quarter':
            // Filter logs for the current quarter of the year
            $where = "WHERE QUARTER(activity_time) = QUARTER(CURDATE()) AND YEAR(activity_time) = YEAR(CURDATE())";
            break;
        case 'q1': case 'q2': case 'q3': case 'q4':
            // Filter logs for a specific quarter (1, 2, 3, or 4)
            $quarter = (int)substr($date_range, 1); // Extract number from 'q1', 'q2', etc.
            $where = "WHERE QUARTER(activity_time) = $quarter AND YEAR(activity_time) = YEAR(CURDATE())";
            break;
    }
}

// Get recent activity logs (limit to 15) with user details using JOIN
// This ensures that only records with non-empty activity descriptions are returned

$sql_logs = "SELECT
                activities.user_id, 
                users.first_name, 
                users.last_name, 
                users.role, 
                users.email, 
                users.phone, 
                activities.activity, 
                activities.activity_time 
            FROM activities 
            JOIN users ON activities.user_id = users.user_id 
            $where
            " . (empty($where) ? "WHERE" : "AND") . " TRIM(activities.activity) != ''             
            ORDER BY activities.activity_time DESC 
            LIMIT 15";


// Execute the query and store results in $logs
$logs = $conn->query($sql_logs);
?>

<!-- Start of HTML -->
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin Dashboard</title>

    <!-- Link to external CSS file -->
    <link rel="stylesheet" href="styles.css">

    <!-- Internal CSS for styling the dashboard page -->
    <style>
        /* Apply font and background to the whole page */
        body { 
            font-family: Arial, sans-serif; 
            background-color: #f4f4f4; 
            margin: 0; 
            padding: 0; 
        }

        /* Header bar at the top */
        .header { 
            background: linear-gradient(to right, #9474af, #e5d4ef); /* Gradient background */
            color: white; 
            padding: 15px; 
            text-align: center; 
            font-size: 24px; 
            font-weight: bold; 
        }

        /* Main container for page content */
        .container { 
            width: 90%; 
            margin: auto; 
            padding: 20px; 
            background: white; 
            border-radius: 8px; 
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1); 
            margin-top: 20px; 
        }

        /* Container for dashboard statistics cards */
        .stats { 
            display: flex; 
            justify-content: space-around; 
            margin-bottom: 20px; 
        }

        /* Individual stat card */
        .card { 
            background: #ddd; 
            padding: 15px; 
            border-radius: 8px; 
            text-align: center; 
            flex: 1; /* Allow equal width */
            margin: 0 10px; 
            font-size: 18px; 
        }

        /* Button styling  */
        .btn { 
            padding: 8px 15px; 
            background: gray; 
            color: white; 
            text-decoration: none; 
            border-radius: 5px; 
            display: inline-block; 
            margin-right: 10px; 
        }

        /* Change button color when hovered */
        .btn:hover { 
            background: darkgrey; 
        }

        /* Table layout */
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px; 
        }

        /* Table headers and cells */
        th, td { 
            padding: 12px; 
            text-align: left; 
            border-bottom: 1px solid #ddd; 
        }

        /* Header styling */
        th { 
            background: #9474af; 
            color: white; 
        }

        /* Highlight row on hover */
        tr:hover { 
            background: #f1e6f7; 
        }
    </style>
</head>
<body>
    <!-- Welcome bar with logout button -->
    <div class="header">
        Welcome, <?= htmlspecialchars($first_name) ?> 
        <a href="logout.php" class="btn" style="float: right; margin-top: -5px;">Logout</a>
    </div>

    <!-- Main content container -->
    <div class="container">
        <h2>Admin Dashboard</h2>

        <!-- Statistics cards section -->
        <div class="stats">
            <div class="card">
                <h3>Importers</h3>
                <p><?= $summary['importers'] ?></p> 
            </div>
            <div class="card">
                <h3>Customs Officials</h3>
                <p><?= $summary['customs'] ?></p>
            </div>
            <div class="card">
                <h3>PPB Officials</h3>
                <p><?= $summary['ppb'] ?></p>
            </div>
        </div>

        <!-- Action buttons -->
        <a href="manage_users.php" class="btn">Manage Users</a>
        <a href="admin_reports.php" class="btn">View Reports</a>

        <!-- Logs filter section -->
        <h3>Recent Activity Logs</h3>
        <form method="get" style="margin-bottom: 15px;">
            <label for="log_range">Filter by:</label>
            <select name="log_range" id="log_range">
                <option value="">All</option>
                <!-- PHP: show selected option after form submission -->
                <option value="this_month" <?= $date_range == 'this_month' ? 'selected' : '' ?>>This Month</option>
                <option value="this_year" <?= $date_range == 'this_year' ? 'selected' : '' ?>>This Year</option>
                <option value="this_quarter" <?= $date_range == 'this_quarter' ? 'selected' : '' ?>>This Quarter</option>
                <option value="q1" <?= $date_range == 'q1' ? 'selected' : '' ?>>1st Quarter</option>
                <option value="q2" <?= $date_range == 'q2' ? 'selected' : '' ?>>2nd Quarter</option>
                <option value="q3" <?= $date_range == 'q3' ? 'selected' : '' ?>>3rd Quarter</option>
                <option value="q4" <?= $date_range == 'q4' ? 'selected' : '' ?>>4th Quarter</option>
            </select>
            <button type="submit" class="btn">Apply</button>
        </form>

        <!-- Activity logs table -->
        <table>
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Full Name</th>
                    <th>Role</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Activity</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                <!-- PHP loop to show each log row from $logs -->
                <?php while ($row = $logs->fetch_assoc()): ?>
<!-- Use htmlspecialchars() to convert special characters (like <, >, &, ") into HTML entities.
This prevents cross-site scripting (XSS) attacks by ensuring that any user-provided content is displayed as plain text in the browser instead of being interpreted as HTML or JavaScript.-->

                    <tr>
                        <td><?= $row['user_id'] ?></td>
                        <td><?= htmlspecialchars($row['first_name'] . " " . $row['last_name']) ?></td>
                        <td><?= htmlspecialchars($row['role']) ?></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td><?= htmlspecialchars($row['phone']) ?></td>
                        <td><?= htmlspecialchars($row['activity']) ?></td>
                        <td><?= $row['activity_time'] ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
