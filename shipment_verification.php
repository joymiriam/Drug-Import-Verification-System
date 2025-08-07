<?php
// Start the session to access session variables
session_start();

// Include the database connection
include 'Connection.php';

// Ensure only users with the 'ppb' role (PPB officers) can access this page
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "ppb") {
    // If not logged in or not a PPB officer, redirect to login page
    header("Location: login.html");
    exit(); // Stop script execution
}

// Store the logged-in user's ID from the session
$user_id = $_SESSION["user_id"];

// --- Fetch the PPB official's name (first and last name) from the users table
$sql_user = "SELECT first_name, last_name FROM users WHERE user_id = ?";
$stmt_user = $conn->prepare($sql_user);            // Prepare the SQL statement
$stmt_user->bind_param("i", $user_id);        // Bind the user_id as an integer
$stmt_user->execute();                                    // Execute the query
$result_user = $stmt_user->get_result();                  // Get the result set
$user = $result_user->fetch_assoc();                      // Fetch the user record
$stmt_user->close();                                      // Close the statement

// If user is found, store their name in the session
if ($user) {
    $_SESSION["first_name"] = $user['first_name'];
    $_SESSION["last_name"] = $user['last_name'];
} else {
    // If user not found (unexpected), stop the script
    die("Error: User not found.");
}

// Concatenate the inspector's full name 
$inspector_name = $_SESSION["first_name"] . " " . $_SESSION["last_name"];

//Handle approval or rejection of a shipment 
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["ship_id"]) && isset($_POST["status"])) {
    // Retrieve shipment ID and status (Approved or Rejected) from form
    $ship_id = $_POST["ship_id"];
    $status = $_POST["status"];

    // If rejected and rejection reason is provided, store it. Otherwise, keep blank.
    $rejection_reason = ($status == "Rejected" && isset($_POST["rejection_reason"]) && !empty($_POST["rejection_reason"])) 
        ? $_POST["rejection_reason"] 
        : "";

    // SQL to insert or update verification record for the given shipment
    $sql = "INSERT INTO verification (ship_id, status, dov, inspector_name, rejection_reason) 
            VALUES (?, ?, NOW(), ?, ?)
            ON DUPLICATE KEY UPDATE 
                status = VALUES(status), 
                dov = NOW(), 
                inspector_name = VALUES(inspector_name), 
                rejection_reason = VALUES(rejection_reason)";

    // Prepare and bind parameters for the query
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isss", $ship_id, $status, $inspector_name, $rejection_reason);
    $stmt->execute();     // Execute insert/update
    $stmt->close();       // Close the statement
}

//Fetch all pending shipments that need verification
$sql = "SELECT 
            s.ship_id, s.imp_id, s.method, s.company, s.port_of_entry, s.doa, 
            v.status, v.rejection_reason 
        FROM shipment s 
        LEFT JOIN verification v ON s.ship_id = v.ship_id 
        WHERE v.status IS NULL OR v.status = 'Pending'";

// Execute the query to get shipments that haven't been verified yet or are still pending
$result = $conn->query($sql);

// Close the database connection after operations are complete
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Shipment Verification</title>
    <link rel="stylesheet" href="styles.css">
   <style>
    /* Style for the whole page */
    body {
        font-family: Arial, sans-serif;         /* Set default font */
        background-color: #f4f4f4;              /* Light gray background */
        text-align: center;                     /* Center-align text globally */
    }

    /* Container wrapping all content */
    .container {
        width: 80%;                             /* Set width to 80% of screen */
        margin: 50px auto;                      /* Center the container with vertical spacing */
        background: white;                      /* White background for contrast */
        text-align: left;                       /* Align text to the left inside container */
        padding: 20px;                          /* Add padding inside */
        border-radius: 10px;                    /* Round corners */
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);/* Soft shadow around the container */
    }

    /* Wrapper for the table to give spacing around it */
    .table-container {
        margin: 20px 0;                         /* Add top and bottom spacing */
    }

    /* Basic table styling */
    table {
        width: 100%;                            /* Full width table */
        border-collapse: collapse;              /* Remove gaps between cells */
    }

    /* Table cell (header and data) styling */
    th, td {
        padding: 12px;                          /* Space inside cells */
        border: 1px solid #ddd;                 /* Light border for grid effect */
        text-align: left;                       /* Align content to the left */
    }

    /* Style for table headers */
    th {
        background-color: #9474af;              /* Purple background */
        color: white;                           /* White text */
    }

    /* Style alternating row colors for better readability */
    tr:nth-child(even) {
        background-color: #f9f9f9;              /* Very light gray for even rows */
    }

    /* Hidden text area for entering rejection reason */
    .rejection-reason {
        display: none;                          /* Hidden by default */
        margin-top: 5px;                        /* Small space above when shown */
    }

    /* General button styling */
    .btn {
        padding: 10px 15px;                     /* Padding inside buttons */
        border: none;                           /* Remove default border */
        cursor: pointer;                        /* Show pointer on hover */
        border-radius: 5px;                     /* Rounded corners */
        font-size: 14px;                        /* Medium font size */
    }

    /* Green approve button */
    .btn-approve {
        background-color: #4CAF50;              /* Green color */
        color: white;                           /* White text */
        margin-right: 5px;                      /* Space to the right for separation */
    }

    /* Red reject button */
    .btn-reject {
        background-color: #f44336;              /* Red color */
        color: white;                           /* White text */
    }

    /* Back button styling */
    .btn-back {
        background-color: #555;                 /* Dark gray background */
        color: white;                           /* White text */
        display: inline-block;                  /* Keep it inline with padding */
        padding: 10px 20px;                     /* Add spacing inside */
        border-radius: 5px;                     /* Rounded edges */
        text-decoration: none;                  /* Remove underline */
    }

    /* Container to right-align the back button */
    .back-container {
        text-align: right;                      /* Align content (like back button) to right */
        width: 100%;                            /* Full width to push button to the edge */
    }
</style>

    <script>
        function showRejectionReason(id) {
            document.getElementById("reason_" + id).style.display = "block";
        }
    </script>
</head>
<body>
    <div class="container">
        <h2>Shipment Verification</h2>
        <div class="back-container">
            <a href="ppb_dashboard.php" class="btn-back">Back</a>
        </div>
        <div class="table-container">
        <table>
    <tr>
        <th>Importer ID</th>
        <th>Shipment Method</th>
        <th>Company</th>
        <th>Port of Entry</th>
        <th>Date of Arrival</th>
        <th>Verification Status</th>
        <th>Action</th>
    </tr>
    <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?php echo htmlspecialchars($row['imp_id'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($row['method'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($row['company'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($row['port_of_entry'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($row['doa'] ?? ''); ?></td>
            <td>
                <?php echo ($row['status']) 
                    ? htmlspecialchars($row['status']) 
                    : '<span style="color: red;">Pending</span>'; ?>
            </td>
            <td>
                <form method="POST">
                    <input type="hidden" name="ship_id" value="<?php echo $row['ship_id']; ?>">
                    <button type="submit" name="status" value="Approved" class="btn btn-approve">Approve</button>
                    <button type="button" class="btn btn-reject" onclick="showRejectionReason(<?php echo $row['ship_id']; ?>)">Reject</button>
                    <div id="reason_<?php echo $row['ship_id']; ?>" class="rejection-reason">
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


