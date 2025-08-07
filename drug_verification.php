<?php
session_start(); // Start a new session or resume the existing session

include 'Connection.php'; // Include database connection script
include 'activity.php';   // Include custom activity logging functions

// Ensure only users with PPB role can access this page
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "ppb") {
    header("Location: login.html"); // Redirect to login if not logged in or not PPB
    exit(); // Stop further script execution
}

$user_id = $_SESSION["user_id"]; // Store logged-in user's ID

// Fetch the PPB official's first and last name
$sql_user = "SELECT first_name, last_name FROM users WHERE user_id = ?";
$stmt_user = $conn->prepare($sql_user); // Prepare the SQL query
$stmt_user->bind_param("i", $user_id); // Bind user_id to the query
$stmt_user->execute(); // Execute the query
$result_user = $stmt_user->get_result(); // Get the result
$user = $result_user->fetch_assoc(); // Fetch the user's details as an associative array
$stmt_user->close(); // Close the statement
$inspector_name = $user['first_name'] . " " . $user['last_name']; // Combine first and last name

// Check if form was submitted for drug verification
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["drug_id"]) && isset($_POST["status"])) {
    $drug_id = $_POST["drug_id"]; // Get the drug ID from form
    $status = $_POST["status"];   // Get the verification status 

    // If rejected, store the reason; otherwise, leave as NULL
    $rejection_reason = ($status == "Rejected" && isset($_POST["rejection_reason"]) && !empty($_POST["rejection_reason"])) 
        ? $_POST["rejection_reason"] 
        : NULL;

    // Check if this drug has already been verified
    $sql_check = "SELECT drug_id FROM verification WHERE drug_id = ?";
    $stmt_check = $conn->prepare($sql_check); // Prepare query
    $stmt_check->bind_param("i", $drug_id);   // Bind drug ID
    $stmt_check->execute();                   // Execute the query
    $stmt_check->store_result();              // Store the result for row count check
    $exists = $stmt_check->num_rows > 0;      // Check if a record exists
    $stmt_check->close();                     // Close the statement

    if ($exists) {
        // Update existing verification record if it already exists
        $sql_update = "UPDATE verification SET status = ?, dov = NOW(), inspector_name = ?, rejection_reason = ? WHERE drug_id = ?";
        $stmt = $conn->prepare($sql_update); // Prepare the update query
        $stmt->bind_param("sssi", $status, $inspector_name, $rejection_reason, $drug_id); // Bind parameters
    } else {
        // Insert a new verification record if it doesn't exist
        $sql_insert = "INSERT INTO verification (drug_id, status, dov, inspector_name, rejection_reason) VALUES (?, ?, NOW(), ?, ?)";
        $stmt = $conn->prepare($sql_insert); // Prepare the insert query
        $stmt->bind_param("isss", $drug_id, $status, $inspector_name, $rejection_reason); // Bind parameters
    }

    // Execute the insert or update query
    if ($stmt->execute()) {
        // Log the verification activity to the activities table
        $sql_log = "INSERT INTO activities (user_id, activity, activity_type, activity_time) 
        VALUES (?, ?, 'drug', NOW())";
        $stmt_log = $conn->prepare($sql_log); // Prepare log query
        $action = "Verified drug ID $drug_id as $status"; // Action message
        $stmt_log->bind_param("is", $user_id, $action); // Bind user ID and message
        $stmt_log->execute(); // Execute the log insert
        $stmt_log->close();   // Close the log statement

        header("Location: drug_verification.php"); // Reload page after successful submission
        exit(); // Exit to prevent further code execution
    } else {
        echo "Error: " . $stmt->error; // Show SQL error if query fails
    }

    $stmt->close(); // Close the insert/update statement
}

// Fetch all drugs that are pending verification (status is NULL or Pending)
$sql = "SELECT d.drug_id, d.name, d.batch_no, v.status, v.rejection_reason, m.name AS manufacturer_name, m.gmp_cert_verif
        FROM drugs d 
        LEFT JOIN verification v ON d.drug_id = v.drug_id 
        LEFT JOIN manufacturer m ON d.manuf_id = m.manuf_id 
        WHERE v.status IS NULL OR v.status = 'Pending'";
$result = $conn->query($sql); // Run the SQL query and store the result

$conn->close(); // Close the database connection
?> 

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Drug Verification</title>
    <link rel="stylesheet" href="styles.css">
    <style>
    /* Apply a default font and background color to the body */
    body {
        font-family: Arial, sans-serif;       /* Use Arial font for text */
        background-color: #f4f4f4;            /* Light grey background color */
        text-align: center;                   /* Center align text by default */
    }

    /* Style for the main container box */
    .container {
        width: 80%;                           /* Container takes 80% of the page width */
        margin: 50px auto;                    /* Centered with 50px margin at the top */
        background: white;                    /* White background */
        text-align: left;                     /* Align text to the left inside the box */
        padding: 20px;                        /* Inner spacing of 20px */
        border-radius: 10px;                  /* Rounded corners */
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); /* Soft shadow effect */
    }

    /* Add spacing above and below the table */
    .table-container {
        margin: 20px 0;                       /* 20px margin on top and bottom */
    }

    /* Basic table layout */
    table {
        width: 100%;                          /* Table spans full container width */
        border-collapse: collapse;            /* Remove spacing between cells */
    }

    /* Style for table header and data cells */
    th, td {
        padding: 12px;                        /* Inner padding for readability */
        border: 1px solid #ddd;               /* Light grey border */
        text-align: left;                     /* Align content to the left */
    }

    /* Style for table header cells */
    th {
        background-color: #9474af;            /* Purple background */
        color: white;                         /* White text */
    }

    /* Zebra-striping for even-numbered rows */
    tr:nth-child(even) {
        background-color: #f9f9f9;            /* Light grey background for even rows */
    }

    /* Hidden rejection reason text area by default */
    .rejection-reason { 
        display: none;                        /* Hidden unless triggered by JS */
        margin-top: 5px;                      /* Spacing from the element above */
    }

    /* General button styles */
    .btn {
        padding: 10px 15px;                   /* Padding inside button */
        border: none;                         /* No border */
        cursor: pointer;                      /* Cursor changes to pointer on hover */
        border-radius: 5px;                   /* Rounded corners */
        font-size: 14px;                      /* Standard font size */
    }

    /* Approve button style */
    .btn-approve {
        background-color: #4CAF50;            /* Green background */
        color: white;                         /* White text */
        margin-right: 5px;                    /* Space between buttons */
    }

    /* Reject button style */
    .btn-reject {
        background-color: #f44336;            /* Red background */
        color: white;                         /* White text */
    }

    /* Style for back button (e.g., to return to previous page) */
    .btn-back {
        background-color: #555;               /* Dark grey background */
        color: white;                         /* White text */
        display: inline-block;                /* Allows padding and margin */
        padding: 10px 20px;                   /* Inner spacing */
        border-radius: 5px;                   /* Rounded corners */
        text-decoration: none;                /* Remove underline from links */
    }

    /* Container to align the back button to the right */
    .back-container {
        text-align: right;                    /* Align child elements to the right */
        width: 100%;                          /* Take full width of parent */
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
        <h1>Drugs Verification</h1>
        <a href="ppb_dashboard.php" class="btn-back">Back to Dashboard</a>
    </header>
    <div class="container">
        
        <div class="table-container">
            <table>
                <tr>
                    <th>Drug Name</th>
                    <th>Batch Number</th>
                    <th>Manufacturer</th>
                    <th>GMP Certified</th>
                    <th>Verification Status</th>
                    <th>Action</th>
                </tr>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['batch_no']); ?></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo ($row['gmp_cert_verif'] ? 'Yes' : 'No'); ?></td>
                        <td>
                            <?php echo ($row['status']) 
                                ? htmlspecialchars($row['status']) 
                                : '<span style="color: red;">Pending</span>'; ?>
                        </td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="drug_id" value="<?php echo $row['drug_id']; ?>">
                                <button type="submit" name="status" value="Approved" class="btn btn-approve">Approve</button>
                                <button type="button" class="btn btn-reject" onclick="showRejectionReason(<?php echo $row['drug_id']; ?>)">Reject</button>
                                <div id="reason_<?php echo $row['drug_id']; ?>" class="rejection-reason">
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
