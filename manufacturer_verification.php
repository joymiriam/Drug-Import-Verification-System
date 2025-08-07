<?php
// Start the session to access session variables 
session_start();

// Include the database connection
include 'Connection.php';

// Include the activity logging function 
include 'activity.php';

// Restrict access to PPB officials only
// If user is not logged in or their role is not 'ppb', redirect to login
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "ppb") {
    header("Location: login.html");
    exit(); // Stop executing the rest of the code
}

// Store the currently logged-in user's ID from the session
$user_id = $_SESSION["user_id"];

// Retrieve the first and last name of the PPB official using the user ID
$sql_user = "SELECT first_name, last_name FROM users WHERE user_id = ?";
$stmt_user = $conn->prepare($sql_user); // Prepare the SQL statement to prevent SQL injection
$stmt_user->bind_param("i", $user_id); // Bind the user ID as an integer (i)
$stmt_user->execute(); // Execute the SQL query
$result_user = $stmt_user->get_result(); // Get the result set
$user = $result_user->fetch_assoc(); // Fetch the user record as an associative array
$stmt_user->close(); // Close the prepared statement

// Combine first and last name into one string 
$inspector_name = $user["first_name"] . " " . $user["last_name"];

// Handle the approval or rejection of a manufacturer if the form has been submitted via POST
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["manuf_id"]) && isset($_POST["status"])) {
    // Get the submitted manufacturer ID and approval status
    $manuf_id = $_POST["manuf_id"];
    $status = $_POST["status"];

    // If status is "Rejected", check if rejection reason is provided and not empty
    $rejection_reason = ($status == "Rejected" && isset($_POST["rejection_reason"]) && !empty($_POST["rejection_reason"])) 
        ? $_POST["rejection_reason"] 
        : ""; // Otherwise leave it as an empty string

    // Insert verification record or update it if it already exists 
    $sql = "INSERT INTO verification (manuf_id, status, dov, inspector_name, rejection_reason) 
            VALUES (?, ?, NOW(), ?, ?)
            ON DUPLICATE KEY UPDATE 
                status = VALUES(status), 
                dov = NOW(), 
                inspector_name = VALUES(inspector_name), 
                rejection_reason = VALUES(rejection_reason)";

    $stmt = $conn->prepare($sql); // Prepare the query
    $stmt->bind_param("isss", $manuf_id, $status, $inspector_name, $rejection_reason); // Bind parameters
    $stmt->execute(); // Run the insert or update
    $stmt->close(); // Close the statement

    // Log this verification action into the activities table
    $sql_log = "INSERT INTO activities (user_id, activity, activity_type, activity_time) 
                VALUES (?, ?, 'manufacturer', NOW())";
    $stmt_log = $conn->prepare($sql_log); // Prepare the log insert
    $action = "Verified manufacturer ID $manuf_id as $status"; // Describe the activity
    $stmt_log->bind_param("is", $user_id, $action); // Bind user ID and activity message
    $stmt_log->execute(); // Insert the log
    $stmt_log->close(); // Close the statement
}

// Fetch all manufacturers whose verification status is either NULL or still 'Pending'
// LEFT JOIN allows manufacturers to be listed even if they haven't been verified yet
$sql = "SELECT m.manuf_id, m.name, m.gmp_cert_verif, v.status, v.rejection_reason 
        FROM manufacturer m 
        LEFT JOIN verification v ON m.manuf_id = v.manuf_id 
        WHERE v.status IS NULL OR v.status = 'Pending'";

$result = $conn->query($sql); // Run the query and store the result set

$conn->close(); // Close the database connection 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manufacturer Verification</title>
    <link rel="stylesheet" href="styles.css">
    <style>
    /* Style for the overall page body */
    body {
        font-family: Arial, sans-serif; /* Use Arial font for text */
        background-color: #f4f4f4;       /* Light gray background for the page */
        text-align: center;              /* Center-align all text by default */
    }

    /* Main container box holding the content */
    .container {
        width: 80%;                      /* Container takes 80% of the page width */
        margin: 50px auto;               /* Vertically 50px margin, horizontally centered */
        background: white;              /* White background for contrast */
        text-align: left;                /* Align content inside container to the left */
        padding: 20px;                   /* Add space inside the container */
        border-radius: 10px;             /* Rounded corners for a smooth look */
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); /* Subtle shadow for elevation effect */
    }

    /* Wrapper around the table for spacing */
    .table-container {
        margin: 20px 0;                  /* Vertical spacing before and after the table */
    }

    /* Style for all tables */
    table {
        width: 100%;                     /* Table stretches full container width */
        border-collapse: collapse;       /* Remove spacing between table cells */
    }

    /* Style for table header cells and table data cells */
    th, td {
        padding: 12px;                   /* Padding inside cells for spacing */
        border: 1px solid #ddd;          /* Light gray border around each cell */
        text-align: left;                /* Align text to the left in each cell */
    }

    /* Style for header cells */
    th {
        background-color: #9474af;       /* Light purple background for headers */
        color: white;                    /* White text for contrast */
    }

    /* Give alternating rows a different background for readability */
    tr:nth-child(even) {
        background-color: #f9f9f9;       /* Very light gray background for even rows */
    }

    /* Hidden container for rejection reason text inputs (shown when rejecting) */
    .rejection-reason {
        display: none;                   /* Hide by default */
        margin-top: 5px;                 /* Small spacing above */
    }

    /* Base style for all buttons */
    .btn {
        padding: 10px 15px;              /* Button padding for click area */
        border: none;                    /* Remove border */
        cursor: pointer;                 /* Change cursor to pointer on hover */
        border-radius: 5px;              /* Rounded corners */
        font-size: 14px;                 /* Set font size */
    }

    /* Approve button styling */
    .btn-approve {
        background-color: #4CAF50;       /* Green background */
        color: white;                    /* White text */
        margin-right: 5px;               /* Small space to the right */
    }

    /* Reject button styling */
    .btn-reject {
        background-color: #f44336;       /* Red background */
        color: white;                    /* White text */
    }

    /* Button that acts like a back link */
    .btn-back {
        background-color: #555;          /* Dark gray background */
        color: white;                    /* White text */
        display: inline-block;           /* Allows margin and padding */
        padding: 10px 20px;              /* Button padding */
        border-radius: 5px;              /* Rounded corners */
        text-decoration: none;           /* Remove underline from link */
    }

    /* Container that aligns the back button to the right */
    .back-container {
        text-align: right;               /* Align child elements (e.g., button) to the right */
        width: 100%;                     /* Take full width of its parent */
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
        <h1>Manufacturer Verification </h1>
        <a href="ppb_dashboard.php" class="btn-back">Back to Dashboard</a>
    </header>
    <div class="container">
        
        <div class="table-container">
            <table>
                <tr>
                    <th>Manufacturer Name</th>
                    <th>GMP Certificate Verified</th>
                    <th>Verification Status</th>
                    <th>Action</th>
                </tr>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td>
                            <?php 
                                if ($row['gmp_cert_verif'] == 1) {
                                    echo "<span style='color: green;'>Yes</span>";
                                } else {
                                    echo "<span style='color: red;'>No</span>";
                                }
                            ?>
                        </td>
                        <td>
                            <?php echo ($row['status']) 
                                ? htmlspecialchars($row['status']) 
                                : '<span style="color: red;">Pending</span>'; ?>
                        </td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="manuf_id" value="<?php echo $row['manuf_id']; ?>">
                                <button type="submit" name="status" value="Approved" class="btn btn-approve">Approve</button>
                                <button type="button" class="btn btn-reject" onclick="showRejectionReason(<?php echo $row['manuf_id']; ?>)">Reject</button>
                                <div id="reason_<?php echo $row['manuf_id']; ?>" class="rejection-reason">
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
