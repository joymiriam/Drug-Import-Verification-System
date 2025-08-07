<?php
session_start(); // Start the session

include 'Connection.php'; // Include the database connection 

// Redirect the user to login page if not logged in or if not an importer
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "importer") {
    header("Location: login.html"); // Send user to login if not authorized
    exit(); // Stop script execution
}

$user_id = $_SESSION["user_id"]; // Store the logged-in user's ID from session

// Fetch the user's first name from the users table for personalized greeting
$sql_user = "SELECT first_name FROM users WHERE user_id = ?";
$stmt_user = $conn->prepare($sql_user); // Prepare SQL to prevent SQL injection
$stmt_user->bind_param("i", $user_id); // Bind user_id as an integer
$stmt_user->execute(); // Execute the SQL query
$result_user = $stmt_user->get_result(); // Get the result
$user = $result_user->fetch_assoc(); // Fetch the first row as associative array
$stmt_user->close(); // Close the statement

// Fetch full importer details using the user ID
$sql = "SELECT * FROM importer WHERE user_id = ?";
$stmt = $conn->prepare($sql); // Prepare the query to fetch importer details
$stmt->bind_param("i", $user_id); // Bind user ID
$stmt->execute(); // Execute the query
$result = $stmt->get_result(); // Get the result
$importer = $result->fetch_assoc(); // Fetch importer details
$stmt->close(); // Close the statement

// If the user has not filled the importer registration form, redirect them to it
if (!$importer) {
    header("Location: importer.html"); // Redirect to importer form
    exit(); // Stop script execution
}

// Fetch shipment records submitted by the importer with their verification status
// DISTINCT Ensures that the result set contains only unique rows.
// COALESCE Returns the first non-null value from a list of arguments.
$sql_imports = "SELECT DISTINCT s.ship_id, d.name AS drug_name, d.quantity, s.doa, s.method, s.company, s.port_of_entry,
                       COALESCE(v.status, 'Under Review') AS status
                FROM shipment s
                JOIN drugs d ON s.drug_id = d.drug_id
                LEFT JOIN verification v ON s.ship_id = v.ship_id
                WHERE s.imp_id = ?";
$stmt_imports = $conn->prepare($sql_imports); // Prepare the query
$stmt_imports->bind_param("i", $importer['imp_id']); // Bind importer ID
$stmt_imports->execute(); // Execute the query
$result_imports = $stmt_imports->get_result(); // Get the shipment records
$stmt_imports->close(); // Close the statement

// Fetch documents uploaded by the importer along with verification status
$sql_docs = "SELECT documents.doc_id, documents.category, documents.dou, COALESCE(verification.status, 'Pending') AS status 
             FROM documents 
             LEFT JOIN verification ON documents.doc_id = verification.doc_id 
             WHERE documents.imp_id = ?";
$stmt_docs = $conn->prepare($sql_docs); // Prepare document query
$stmt_docs->bind_param("i", $importer['imp_id']); // Bind importer ID
$stmt_docs->execute(); // Run the query
$result_docs = $stmt_docs->get_result(); // Get the documents and statuses
$stmt_docs->close(); // Close the statement

// Fetch upcoming approved shipments that haven't arrived yet (future DOA)
$sql_transit = "SELECT s.ship_id, s.doa, s.method, s.company, s.port_of_entry 
                FROM shipment s
                JOIN verification v ON s.ship_id = v.ship_id
                WHERE s.imp_id = ? AND v.status = 'Approved' AND s.doa > CURDATE()";
$stmt_transit = $conn->prepare($sql_transit); // Prepare query for future shipments
$stmt_transit->bind_param("i", $importer['imp_id']); // Bind importer ID
$stmt_transit->execute(); // Execute the query
$result_transit = $stmt_transit->get_result(); // Get results
$stmt_transit->close(); // Close the statement

//Close the database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en"> <!-- Declares the document as HTML5 and sets the language to English -->
<head>
    <title>Importer Dashboard</title> <!-- The title displayed in the browser tab -->
    <link rel="stylesheet" href="styles.css"> 

    <style>

        /* Style the body element */
        body {
            font-family: Arial, sans-serif; /* Sets font type */
            background-color: #f4f4f4; 
            text-align: center; /* Centers all text */
        }

        /* Class for setting a fixed background image on dashboard */
        .importer-dashboard {
            background-image: url('images/importer.jpg'); 
            background-size: cover; /* Covers the whole background area */
            background-position: center; /* Centers the background image */
            background-repeat: no-repeat; 
            background-attachment: fixed; /* Background stays fixed when scrolling */
        }

        /* Styling for the main container box */
        .container {
            opacity: 0.9; /* Slight transparency */
            width: 100%;
            max-width: 1100px; /* Limits width to 1100px */
            margin: 50px auto; /* Centers container vertically with top/bottom spacing */
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); /* Soft shadow around the container */
        }

        /* Wraps tables with margin and alignment */
        .table-container {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 20px 0;
        }

        /* General table styling */
        table {
            width: 80%;
            border-collapse: collapse;
            margin: auto;
        }

        /* Style for table headers and data cells */
        th, td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: center;
        }

        /* Styling table headers */
        th {
            background-color: #9474af; 
            color: white;
        }

        /* Zebra-stripe alternate rows */
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        /* Common style for action buttons */
        .btn-view, .btn-remove {
            padding: 6px 12px;
            margin: 2px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
            color: white;
        }

        .btn-view {
            background-color: #9474af;
        }

        .btn-view:hover {
            background-color: #6d4f8d; 
        }

        .btn-remove {
            background-color: #9474af;
        }

        .btn-remove:hover {
            background-color: #9474af; 
        }
    </style>
</head>

<body class="importer-dashboard"> 

<header>
    <!-- Welcome message using session variable for the logged-in user -->
    <h1>Welcome, <?php echo ($user['first_name']); ?>!</h1>

    <!-- Logout button to terminate session -->
    <a href="logout.php" class="logout_btn">Logout</a>
</header>

<div class="container">
    <!-- Show success or error message if it exists in the session -->
    <?php if (isset($_SESSION['message'])): ?>
    <p style="background: #dff0d8; color: #3c763d; padding: 10px; border-radius: 5px;">
        <?= $_SESSION['message']; unset($_SESSION['message']); ?>
    </p>
    <?php endif; ?>

    <!-- Sidebar for navigation buttons -->
    <div class="sidebar">
        <a href="drugs.html" class="sidebar-btn">Register a Drug</a>
        <a href="shipment_form.php" class="sidebar-btn">Register a Shipment</a>
        <a href="documents.html" class="sidebar-btn">Upload Documents</a>
        <a href="importer_reports.php" class="sidebar-btn">Reports</a>
    </div>

    <!-- Importer information section -->
    <section class="importer-info">
        <h2>Importer Details</h2>
        <div class="importer-grid">
            <div><strong>Company Name:</strong></div>
            <div><?php echo htmlspecialchars($importer['name']); ?></div>

            <div><strong>Email:</strong></div>
            <div><?php echo htmlspecialchars($importer['email']); ?></div>

            <div><strong>Phone:</strong></div>
            <div><?php echo htmlspecialchars($importer['phone']); ?></div>
        </div>

        <!-- Button to edit importer company info -->
        <a href="edit_importer.php" class="btn-view" style="margin-top:10px; display:inline-block;">Edit Company Info</a>
    </section>

    <!-- Past import records table -->
    <section class="import-history">
        <h2>Past Import Records</h2>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Import ID</th>
                        <th>Drug Name</th>
                        <th>Quantity</th>
                        <th>Date Imported</th>
                        <th>Method</th>
                        <th>Company</th>
                        <th>Port of Entry</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Loop through previous imports -->
                    <?php while ($row = $result_imports->fetch_assoc()): ?>
                        <!-- htmlspecialchars prevents XSS attacks by converting special HTML characters -->
                        <tr>
                            <td><?php echo htmlspecialchars($row['ship_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['drug_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['quantity']); ?></td>
                            <td><?php echo htmlspecialchars($row['doa']); ?></td>
                            <td><?php echo htmlspecialchars($row['method']); ?></td>
                            <td><?php echo htmlspecialchars($row['company']); ?></td>
                            <td><?php echo htmlspecialchars($row['port_of_entry']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Section for approved shipments still in transit -->
    <section class="transit-shipments">
        <h2>Approved Shipments Still in Transit</h2>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Shipment ID</th>
                        <th>Date of Arrival</th>
                        <th>Method</th>
                        <th>Company</th>
                        <th>Port of Entry</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Check if there are any shipments in transit -->
                    <?php if ($result_transit->num_rows > 0): ?>
                        <?php while ($ship = $result_transit->fetch_assoc()): ?>
                            <tr>
<!-- Use htmlspecialchars() to convert special characters (like <, >, &, ") into HTML entities.
This prevents cross-site scripting (XSS) attacks by ensuring that any user-provided content is displayed as plain text in the browser instead of being interpreted as HTML or JavaScript.-->
                                <td><?php echo htmlspecialchars($ship['ship_id']); ?></td>
                                <td><?php echo htmlspecialchars($ship['doa']); ?></td>
                                <td><?php echo htmlspecialchars($ship['method']); ?></td>
                                <td><?php echo htmlspecialchars($ship['company']); ?></td>
                                <td><?php echo htmlspecialchars($ship['port_of_entry']); ?></td>
                                <td><span style="color:black;">Approved - In Transit</span></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <!-- If no shipments in transit -->
                        <tr><td colspan="6">No approved shipments are currently in transit.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Section to show documents uploaded by the importer -->
    <section class="document-history">
        <h2>Submitted Documents</h2>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Document ID</th>
                        <th>Category</th>
                        <th>Date Uploaded</th>
                        <th>Verification Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Loop through submitted documents -->
                    <?php while ($doc = $result_docs->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($doc['doc_id']); ?></td>
                            <td><?php echo htmlspecialchars($doc['category']); ?></td>
                            <td><?php echo htmlspecialchars($doc['dou']); ?></td>
                            <td><?php echo htmlspecialchars($doc['status']); ?></td>
                            <td>
                                <!-- View document opens in a new tab -->
                                <a href="view_documents.php?doc_id=<?php echo $doc['doc_id']; ?>" class="btn-view" target="_blank">View</a>

                                <!-- Remove document with confirmation alert -->
                                <a href="remove_documents.php?doc_id=<?php echo $doc['doc_id']; ?>" class="btn-remove" onclick="return confirm('Are you sure you want to delete this document?');">Remove</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<!-- Footer contact information -->
<footer>
    <h2>Contact Us</h2>
    <p>Email: support@pharmasure.com</p>
    <p>Phone: +123 456 789</p>
    <p>&copy; 2025 PharmaSure. All rights reserved.</p>
</footer>

</body>
</html>

