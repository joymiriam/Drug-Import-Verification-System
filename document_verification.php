<?php
// Start a new or resume an existing session 
session_start();

// Include the database connection file 
include 'Connection.php';

// Include the activity logging function to track user actions in the activities table
include 'activity.php';

// Check if the user is logged in and has the ppb role
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "ppb") {
    // If the user is not authorized, redirect them to the login page
    header("Location: login.html");
    exit(); // Stop further execution of the script
}

// Retrieve the currently logged-in user's ID from the session
$user_id = $_SESSION["user_id"];

// Prepare an SQL statement to fetch the first and last name of the logged-in PPB official
$sql_user = "SELECT first_name, last_name FROM users WHERE user_id = ?";
$stmt_user = $conn->prepare($sql_user); // Prepare the SQL statement for execution
$stmt_user->bind_param("i", $user_id);  // Bind the user_id to the statement as an integer
$stmt_user->execute();                  // Execute the prepared statement
$result_user = $stmt_user->get_result(); // Get the result set from the executed query
$user = $result_user->fetch_assoc();     // Fetch the user details as an associative array
$stmt_user->close();                     // Close the statement to free resources

// If user information was found in the database
if ($user) {
    // Store the first and last name in session 
    $_SESSION["first_name"] = $user['first_name'];
    $_SESSION["last_name"] = $user['last_name'];
} else {
    // If the user is not found in the database, terminate script with an error message
    die("Error: User not found.");
}

// Combine first name and last name into a full inspector name for verifying
$inspector_name = $_SESSION["first_name"] . " " . $_SESSION["last_name"];

// Check if the request is a POST request and contains both doc_id and status
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["doc_id"]) && isset($_POST["status"])) {
    // Get document ID and status approved/rejected from the submitted form
    $doc_id = $_POST["doc_id"];
    $status = $_POST["status"];

    // If status is Rejected, check if a rejection reason was provided
    // If not rejected, or reason is missing, default to an empty string
    $rejection_reason = ($status == "Rejected" && isset($_POST["rejection_reason"]) && !empty($_POST["rejection_reason"])) 
        ? $_POST["rejection_reason"] 
        : "";

// SQL query to insert verification record or update if it already exists 
// Get imp_id from the documents table
$imp_id = null;
$stmt_imp = $conn->prepare("SELECT imp_id FROM documents WHERE doc_id = ?");
$stmt_imp->bind_param("i", $doc_id);
$stmt_imp->execute();
$stmt_imp->bind_result($imp_id);
$stmt_imp->fetch();
$stmt_imp->close();

// Insert or update the verification record including imp_id
$sql = "INSERT INTO verification (imp_id, doc_id, status, dov, inspector_name, rejection_reason) 
        VALUES (?, ?, ?, NOW(), ?, ?)
        ON DUPLICATE KEY UPDATE imp_id = VALUES(imp_id), status = VALUES(status), dov = NOW(), inspector_name = VALUES(inspector_name), rejection_reason = VALUES(rejection_reason)";
// Prepare the SQL statement for safe execution
$stmt = $conn->prepare($sql);
// Bind the values to the placeholders in the SQL query: int, int, string, string, string
$stmt->bind_param("iisss", $imp_id, $doc_id, $status, $inspector_name, $rejection_reason);
// Execute the insert/update operation
$stmt->execute();
    // Close the statement
$stmt->close();

// Record the verification action in the activities table
    log_activity($conn, $user_id, 'Verified document', 'Document Verification');

}

// SQL query to select documents that are pending verification or not yet verified
$sql = "SELECT d.doc_id, d.category, d.type, v.status, v.rejection_reason 
        FROM documents d 
        LEFT JOIN verification v ON d.doc_id = v.doc_id 
        WHERE v.status IS NULL OR v.status = 'Pending'";

// Run the query and store the result set in $result
$result = $conn->query($sql);

// Close the database connection 
$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <title>Document Verification</title>
    <link rel="stylesheet" href="styles.css">
    <style>
    /* Sets font and background for the body */
    body {
        font-family: Arial, sans-serif; /* Sets a clean, readable font */
        background-color: #f4f4f4;      /*  background color */
        text-align: center;             /* Center-aligns text globally */
    }

    /* Styles the main container box holding the content */
    .container {
        width: 80%;                            /* Container takes 80% of page width */
        margin: 50px auto;                     /* Centered with 50px space on top and bottom */
        background: white;                     /* White background for contrast */
        text-align: left;                      /* Text aligned to the left inside the box */
        padding: 20px;                         /* Padding inside the container */
        border-radius: 10px;                   /* Rounded corners */
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); /* Soft shadow for depth */
    }

    /* Container specifically for table area */
    .table-container {
        margin: 20px 0;                        /* Adds vertical spacing around the table */
    }

    /* Basic table layout */
    table {
        width: 100%;                           /* Table takes full width of container */
        border-collapse: collapse;             /* Collapses border spacing between cells */
    }

    /* Table cell styling for both headers and data */
    th, td {
        padding: 12px;                         /* Inner space for cells */
        border: 1px solid #ddd;                /* Light gray borders between cells */
        text-align: left;                      /* Left-aligned content */
    }

    /* Header row styling */
    th {
        background-color: #9474af;             /* Purple shade background */
        color: white;                          /* White text for contrast */
    }

    /* Zebra-striping for even rows for readability */
    tr:nth-child(even) {
        background-color: #f9f9f9;             /* Light gray for alternating rows */
    }

    /* Hidden by default: field for entering rejection reason */
    .rejection-reason { 
        display: none;                         /* Hides the rejection reason text area */
        margin-top: 5px;                       /* Adds small spacing above it when visible */
    }

    /* Shared styles for all buttons */
    .btn {
        padding: 10px 15px;                    /* Internal button padding */
        border: none;                          /* No border */
        cursor: pointer;                       /* Cursor becomes pointer on hover */
        border-radius: 5px;                    /* Rounded edges */
        font-size: 14px;                       /* Standard font size for buttons */
    }

    /* Green approve button */
    .btn-approve {
        background-color: #4CAF50;             /* Green background */
        color: white;                          /* White text */
        margin-right: 5px;                     /* Adds space after the approve button */
    }

    /* Red reject button */
    .btn-reject {
        background-color: #f44336;             /* Red background */
        color: white;                          /* White text */
    }

    /* Gray back button */
    .btn-back {
        background-color: #555;                /* Dark gray background */
        color: white;                          /* White text */
        display: inline-block;                 /* Allows padding and margin */
        padding: 10px 20px;                    /* Padding inside the back button */
        border-radius: 5px;                    /* Rounded edges */
        text-decoration: none;                 /* Removes underline from text */
    }

    /* Container for back button, aligns it to the right */
    .back-container {
        text-align: right;                     /* Aligns child elements (back button) to the right */
        width: 100%;                           /* Takes full width of the page/container */
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
        <h1>Document Verification</h1>
        <a href="ppb_dashboard.php" class="btn-back">Back to Dashboard</a>
    </header>
    <div class="container">
        
        <div class="table-container">
            <table>
                <tr>
                    <th>Document Name</th>
                    <th>Type</th>
                    <th>Verification Status</th>
                    <th>Action</th>
                </tr>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['category']); ?></td>
                        <td><?php echo htmlspecialchars($row['type']); ?></td>
                        <td>
                            <?php echo ($row['status']) 
                                ? htmlspecialchars($row['status']) 
                                : '<span style="color: red;">Pending</span>'; ?>
                        </td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="doc_id" value="<?php echo $row['doc_id']; ?>">
                                <button type="submit" name="status" value="Approved" class="btn btn-approve">Approve</button>
                                <button type="button" class="btn btn-reject" onclick="showRejectionReason(<?php echo $row['doc_id']; ?>)">Reject</button>
                                <div id="reason_<?php echo $row['doc_id']; ?>" class="rejection-reason">
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
