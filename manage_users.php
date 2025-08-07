<?php
// Start the session to access or store session variables
session_start();

// Include the database connection file 
include 'Connection.php';

include 'activity.php'; // Include the activity logging function
// Ensure only users with 'admin' role can access this page
// If user is not logged in or is not an admin, redirect them to login page
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.html");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_user'])) {
    // Sanitize and store form input values
    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Encrypt the password securely
    $role = $_POST['role'];
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);

    // Check if the email is already used by another user
    $check_stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $check_stmt->store_result();

    if ($check_stmt->num_rows > 0) {
        // If email exists, show error message
        $_SESSION['error'] = "Email already exists.";
    } else {
        // Insert new user data into the database
        $stmt = $conn->prepare("INSERT INTO users (username, password, role, email, phone, first_name, last_name) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $username, $password, $role, $email, $phone, $first_name, $last_name);
        
        if ($stmt->execute()) {
            // If insertion is successful, store a success message
            $_SESSION['message'] = "New user added successfully.";
             // Log the activity of adding a new user
            log_activity($conn, $_SESSION['user_id'], "Added new user: $username ($email)", "User Management");
     
        } else {
            // If insertion fails, store an error message
            $_SESSION['error'] = "Error adding new user.";
        }
        $stmt->close(); // Close the insert statement
    }

    $check_stmt->close(); // Close the email check statement

    // Redirect back to the user management page after processing
    header("Location: manage_users.php");
    exit();
}

// Select all user records for display in the admin interface
$sql = "SELECT user_id, username, role, phone, email, first_name, last_name FROM users";
$result = $conn->query($sql);

if (isset($_GET['delete'])) {
    // Get the user ID to delete from the URL query string
    $user_id = intval($_GET['delete']); // Convert to integer to prevent SQL injection

    // Prepare and execute the delete statement
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        $_SESSION['message'] = "User deleted successfully.";
        log_activity($conn, $_SESSION['user_id'], "Deleted user with ID: $user_id", "User Management");

    } else {
        $_SESSION['error'] = "Error deleting user.";
    }

    // Redirect back to the user management page
    header("Location: manage_users.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_role"])) {
    // Get the user ID and the new role from the form
    $user_id = intval($_POST["user_id"]);
    $new_role = $_POST["new_role"];

    // Update the user's role in the database
    $stmt = $conn->prepare("UPDATE users SET role = ? WHERE user_id = ?");
    $stmt->bind_param("si", $new_role, $user_id);

    if ($stmt->execute()) {
        $_SESSION['message'] = "User role updated successfully.";
        log_activity($conn, $_SESSION['user_id'], "Changed role of user ID $user_id to $new_role", "User Management");

    } else {
        $_SESSION['error'] = "Error updating role.";
    }

    // Redirect 
    header("Location: manage_users.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Users</title>
    <link rel="stylesheet" href="styles.css">
<style>
    /* Set base font and background color for the whole page, remove default margins/padding */
    body {
        font-family: Arial, sans-serif;
        background-color: #f4f4f4;
        margin: 0;
        padding: 0;
    }

    /* Style for the header bar at the top of the page */
    .header {
        background: linear-gradient(to right, #9474af, #e5d4ef); /* Gradient from purple to light violet */
        color: white;                         /* White text */
        padding: 15px;                        /* Padding around the text */
        text-align: center;                   /* Center-align text */
        font-size: 24px;                      /* Large font */
        font-weight: bold;                    /* Bold text */
    }

    /* Main content container style */
    .container {
        width: 90%;                           /* Take 90% of the screen width */
        margin: auto;                         /* Center the container horizontally */
        padding: 20px;                        /* Padding inside the container */
        background: white;                    /* White background */
        border-radius: 8px;                   /* Rounded corners */
        box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1); /* Soft shadow around the box */
        margin-top: 20px;                     /* Space above the container */
    }

    /* Basic button styling */
    .btn {
        padding: 8px 12px;                    /* Top-bottom and left-right padding */
        background: #9474af;                  /* Purple background */
        color: white;                         /* White text */
        text-decoration: none;                /* Remove underline */
        border-radius: 5px;                   /* Rounded corners */
        border: none;                         /* No border */
        cursor: pointer;                      /* Cursor becomes pointer on hover */
    }

    /* Button hover effect - darken the background slightly */
    .btn:hover {
        background: #815a9b;
    }

    /* Special style for delete button */
    .delete-btn {
        background: red;                      /* Red background */
    }

    /* Delete button hover effect - darker red */
    .delete-btn:hover {
        background: darkred;
    }

    /* Table styling */
    table {
        width: 100%;                          /* Full width */
        border-collapse: collapse;            /* Remove spacing between borders */
        margin-top: 20px;                     /* Space above the table */
    }

    /* Header and cell styling */
    th, td {
        padding: 12px;                        /* Padding inside each cell */
        text-align: left;                     /* Align text to the left */
        border-bottom: 1px solid #ddd;        /* Light gray border under each row */
    }

    /* Header cell specific styling */
    th {
        background: #9474af;                  /* Purple background */
        color: white;                         /* White text */
    }

    /* Row hover effect for table */
    tr:hover {
        background: #f1e6f7;                  /* Light purple on hover */
    }

    /* Add margin and padding to select and button elements */
    select, .btn {
        margin-top: 5px;
        padding: 6px;
    }

    /* Message style for success or confirmation */
    .message {
        color: green;
        font-weight: bold;
    }

    /* Message style for error display */
    .error {
        color: red;
        font-weight: bold;
    }

    /* Style for the add user form container */
    form.add-user-form {
        margin-bottom: 30px;                  /* Space below the form */
        background: #f9f9f9;                  /* Light gray background */
        padding: 15px;                        /* Padding inside the form */
        border-radius: 8px;                   /* Rounded corners */
    }

    /* Style for inputs and selects inside the form */
    form.add-user-form input,
    form.add-user-form select {
        width: 100%;                          /* Full width input fields */
        margin-bottom: 10px;                  /* Space between fields */
        padding: 10px;                        /* Padding inside inputs */
        border: 1px solid #ccc;               /* Light gray border */
        border-radius: 5px;                   /* Rounded corners */
    }
</style>

    <script>
        function confirmDelete(userId) {
            if (confirm("Are you sure you want to delete this user?")) {
                window.location.href = "manage_users.php?delete=" + userId;
            }
        }
    </script>
</head>
<body>
    <div class="header">
        Manage Users
        <a href="admin_dashboard.php" class="btn" style="float: right; margin-top: -5px;">Back to Dashboard</a>
    </div>

    <div class="container">
        <h2>User Management</h2>

        <?php if (isset($_SESSION['message'])): ?>
            <p class="message"><?= $_SESSION['message']; unset($_SESSION['message']); ?></p>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <p class="error"><?= $_SESSION['error']; unset($_SESSION['error']); ?></p>
        <?php endif; ?>

        <h3>Add New User</h3>
        <form method="post" class="add-user-form">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <input type="text" name="first_name" placeholder="First Name" required>
            <input type="text" name="last_name" placeholder="Last Name" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="text" name="phone" placeholder="Phone" required>
            <select name="role" required>
                <option value="">Select Role</option>
                <option value="importer">Importer</option>
                <option value="Customs">Customs</option>
                <option value="PPB">PPB</option>
                <option value="admin">Admin</option>
            </select>
            <button type="submit" name="add_user" class="btn">Add User</button>
        </form>

        <table>
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Role</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['user_id'] ?></td>
                        <td><?= htmlspecialchars($row['username']) ?></td>
                        <td><?= htmlspecialchars($row['first_name'] . " " . $row['last_name']) ?></td>
                        <td>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="user_id" value="<?= $row['user_id'] ?>">
                                <select name="new_role">
                                    <option value="importer" <?= $row['role'] == 'importer' ? 'selected' : '' ?>>Importer</option>
                                    <option value="admin" <?= $row['role'] == 'admin' ? 'selected' : '' ?>>Admin</option>
                                    <option value="Customs" <?= $row['role'] == 'Customs' ? 'selected' : '' ?>>Customs</option>
                                    <option value="PPB" <?= $row['role'] == 'PPB' ? 'selected' : '' ?>>PPB</option>
                                </select>
                                <button type="submit" name="update_role" class="btn">Update</button>
                            </form>
                        </td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td><?= htmlspecialchars($row['phone']) ?></td>
                        <td>
                            <button class="btn delete-btn" onclick="confirmDelete(<?= $row['user_id'] ?>)">Delete</button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
