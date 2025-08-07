<?php
// Start or resume the session
session_start();

// Include database connection file
include 'Connection.php';

// Include the activity logging script 
include 'activity.php';

// Restrict access: Only allow logged-in users with role 'ppb' to view this page
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "ppb") {
    header("Location: login.html"); // Redirect to login if not authorized
    exit(); // Stop further execution
}

// Store the current user's ID from session
$user_id = $_SESSION["user_id"];

// Fetch the first and last name of the logged-in PPB official from the users table
$sql_user = "SELECT first_name, last_name FROM users WHERE user_id = ?";
$stmt_user = $conn->prepare($sql_user); // Prepare SQL query to prevent SQL injection
$stmt_user->bind_param("i", $user_id); // Bind the user_id as an integer
$stmt_user->execute(); // Run the query
$result_user = $stmt_user->get_result(); // Get the result set
$user = $result_user->fetch_assoc(); // Fetch the result as an associative array
$stmt_user->close(); // Close the prepared statement

// Store the fetched name in session for later use 
if ($user) {
    $_SESSION["first_name"] = $user["first_name"];
    $_SESSION["last_name"] = $user["last_name"];
}

// Combine first and last name to identify the PPB inspector
$inspector_name = $_SESSION["first_name"] . " " . $_SESSION["last_name"];

// Handle approval or rejection submitted via POST request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["imp_id"]) && isset($_POST["status"])) {
    // Get the importer ID and status from the form
    $imp_id = $_POST["imp_id"];
    $status = $_POST["status"];

    // If status is 'Rejected' and a rejection reason is provided, store it
    $rejection_reason = ($status == "Rejected" && isset($_POST["rejection_reason"]) && !empty($_POST["rejection_reason"])) 
        ? $_POST["rejection_reason"] 
        : NULL; // Otherwise, store NULL

    // Insert a new verification record or update existing one (if importer already has a record)
    $sql = "INSERT INTO verification (imp_id, status, dov, inspector_name, rejection_reason) 
    VALUES (?, ?, NOW(), ?, ?) 
    ON DUPLICATE KEY UPDATE 
        status = VALUES(status), 
        dov = VALUES(dov), 
        inspector_name = VALUES(inspector_name), 
        rejection_reason = VALUES(rejection_reason)";

    $stmt = $conn->prepare($sql); // Prepare the SQL query

    // Bind the values: i = integer (imp_id), s = string (status), s = string (inspector name), s = string or null (rejection reason)
    $stmt->bind_param("isss", $imp_id, $status, $inspector_name, $rejection_reason);
    $stmt->execute(); // Execute the query
    $stmt->close(); // Close the statement

    // Log this verification activity to the activity table
    $activity_message = "Importer verification - Importer ID: $imp_id, Status: $status";
    
    // If rejected, include the rejection reason in the activity log
    if ($rejection_reason) {
        $activity_message .= ", Reason: $rejection_reason";
    }

    // Call log function to record the action
    log_activity($conn, $user_id, $activity_message, "Verification");
}

// Fetch importers who are pending verification 
$sql = "SELECT i.imp_id, i.name, i.license_no, v.status, v.rejection_reason 
        FROM importer i 
        LEFT JOIN verification v ON i.imp_id = v.imp_id 
        WHERE v.status IS NULL OR v.status = 'Pending'";

// Run the query and store the result in $result for use in the HTML section
$result = $conn->query($sql);

// Close the database connection at the end of the script
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Importer Verification</title>
    <link rel="stylesheet" href="styles.css">
<style>
    /* Set default styles for the body */
    body {
        font-family: Arial, sans-serif;          /* Use Arial or a fallback sans-serif font */
        background-color: #f4f4f4;              /* Light grey background color */
        text-align: center;                     /* Center-align all text inside body */
    }

    /* Style the main content container */
    .container {
        width: 80%;                             /* Set container width to 80% of the page */
        margin: 50px auto;                      /* 50px margin on top and bottom, centered horizontally */
        background: white;                      /* Set container background to white */
        text-align: left;                       /* Align text to the left inside the container */
        padding: 20px;                          /* Add 20px padding inside the container */
        border-radius: 10px;                    /* Round the corners of the container */
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);/* Add a subtle shadow around the container */
    }

    /* Container for the table, adds vertical spacing */
    .table-container {
        margin: 20px 0;                         /* Add 20px margin above and below */
    }

    /* Style for the entire table */
    table {
        width: 100%;                            /* Make table take full width of container */
        border-collapse: collapse;              /* Merge table borders for cleaner look */
    }

    /* Style for both table headers and table data cells */
    th, td {
        padding: 12px;                          /* Add 12px padding inside each cell */
        border: 1px solid #ddd;                 /* Light grey border for all cells */
        text-align: left;                       /* Align text to the left inside cells */
    }

    /* Specific styles for table header cells */
    th {
        background-color: #9474af;              /* Purple background for header */
        color: white;                           /* White text color for header */
    }

    /* Apply alternating row color for better readability */
    tr:nth-child(even) {
        background-color: #f9f9f9;              /* Light grey background for even rows */
    }

    /* Hidden div to display rejection reason text input */
    .rejection-reason { 
        display: none;                          /* Hide it by default */
        margin-top: 5px;                        /* Add spacing above the reason field */
    }

    /* Base button style for all buttons */
    .btn {
        padding: 10px 15px;                     /* Add padding inside the button */
        border: none;                           /* Remove border */
        cursor: pointer;                        /* Show pointer cursor on hover */
        border-radius: 5px;                     /* Slightly rounded corners */
        font-size: 14px;                        /* Set font size for button text */
    }

    /* Approve button styling */
    .btn-approve {
        background-color: #4CAF50;              /* Green background */
        color: white;                           /* White text color */
        margin-right: 5px;                      /* Space between approve and reject buttons */
    }

    /* Reject button styling */
    .btn-reject {
        background-color: #f44336;              /* Red background */
        color: white;                           /* White text color */
    }

    /* Back button styling */
    .btn-back {
        background-color: #555;                 /* Dark grey background */
        color: white;                           /* White text color */
        display: inline-block;                  /* Allow padding and margin to apply */
        padding: 10px 20px;                     /* Larger padding for bigger button */
        border-radius: 5px;                     /* Rounded corners */
        text-decoration: none;                  /* Remove underline from link */
    }

    /* Container that holds the back button, aligns it right */
    .back-container {
        text-align: right;                      /* Align back button to the right */
        width: 100%;                            /* Take full width of parent container */
    }
</style>

    <script>
        function showRejectionReason(id) {
            document.getElementById("reason_" + id).style.display = "block";
        }
    </script>
</head>
<body>
<header>
        <h1>Importer Verification </h1>
        <a href="ppb_dashboard.php" class="btn-back">Back to Dashboard</a>
    </header>
    <div class="container">
       

        <div class="table-container">
            <table>
                <tr>
                    <th>Importer Name</th>
                    <th>License Number</th>
                    <th>Verification Status</th>
                    <th>Action</th>
                </tr>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['license_no']); ?></td>
                        <td>
                            <?php echo ($row['status']) 
                                ? htmlspecialchars($row['status']) 
                                : '<span style="color: red;">Pending</span>'; ?>
                        </td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="imp_id" value="<?php echo $row['imp_id']; ?>">
                                <button type="submit" name="status" value="Approved" class="btn btn-approve">Approve</button>
                                <button type="button" class="btn btn-reject" onclick="showRejectionReason(<?php echo $row['imp_id']; ?>)">Reject</button>
                                <div id="reason_<?php echo $row['imp_id']; ?>" class="rejection-reason">
                                    <input type="text" name="rejection_reason" placeholder="Reason for rejection">
                                    <button type="submit" name="status" value="Rejected" class="btn btn-reject">Confirm Reject</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </table>
        </div>
        
    </div>
</body>
</html>
