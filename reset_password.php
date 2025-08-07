<?php
// Start a new or resume the existing session
session_start();

// Include the database connection file
include 'Connection.php';

// Check if the 'token' GET parameter is set in the URL
if (!isset($_GET['token'])) {
    // If token is not provided, stop execution with an error message
    die("Invalid request. No token provided.");
}

// Assign the token value from the URL to a variable
$token = $_GET['token'];

// Prepare a query to check if the token exists in the 'password_resets' table
$stmt = $conn->prepare("SELECT email, expires_at FROM password_resets WHERE token = ?");
$stmt->bind_param("s", $token); // Bind the token value to the prepared statement
$stmt->execute();               // Execute the SQL statement
$stmt->store_result();          // Store the result to check how many rows matched

// If the token is not found in the table, it's either invalid or already used
if ($stmt->num_rows !== 1) {
    die("Invalid or expired token.");
}

// Bind the result columns to variables
$stmt->bind_result($email, $expires_at);
$stmt->fetch(); // Fetch the result into the bound variables
$stmt->close(); // Close the statement after use

// Check if the token has expired
if (strtotime($expires_at) < time()) {
    die("This token has expired. Please request a new password reset.");
}
// If the token is valid, proceed to handle the password reset form submission
// Check if the form has been submitted to reset the password
if (isset($_POST['reset'])) {
    // Sanitize the form inputs
    $username = trim($_POST['username']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Check if the new passwords match
    if ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } 
    // Ensure password length is secure enough
    elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters.";
    } 
    else {
        // Check if the provided username and email match an existing user
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $stmt->store_result();

        // If no match is found, show an error
        if ($stmt->num_rows !== 1) {
            $error = "The username does not match the email associated with this reset link.";
        } else {
            $stmt->close(); // Close the previous statement

            // Encrypt (hash) the new password using bcrypt
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            // Update the user's password in the database
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->bind_param("ss", $hashed_password, $email);
            $stmt->execute();
            $stmt->close();

            // Delete the used token from the password_resets table to prevent reuse
            $stmt = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $stmt->close();

            // Show success message and redirect to login after 5 seconds
            $success = "Password has been reset successfully. Redirecting to login...";
            header("refresh:5; url=login.html");
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
  <style>
    /* Apply a light blue background and set a clean sans-serif font for the entire page */
    body {
        background-color: #f2f9ff; /* Soft blue background for gentle appearance */
        font-family: Arial, sans-serif; /* Use Arial font with sans-serif fallback for readability */
    }

    /* Style the main container that holds the form */
    .container {
        max-width: 400px; /* Limit width to 400px for centered, compact layout */
        margin: 100px auto; /* Add top spacing and center horizontally */
        background: white; /* White background for contrast */
        padding: 30px; /* Internal padding for spacing */
        border-radius: 12px; /* Rounded corners */
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); /* Subtle shadow for depth */
    }

    /* Style the heading of the form */
    h2 {
        text-align: center; /* Center-align the heading text */
        margin-bottom: 20px; /* Space below the heading */
        color: #333; /* Dark gray text color for better visibility */
    }

    /* Label styling for inputs */
    label {
        font-weight: bold; /* Bold text for emphasis */
        display: block; /* Make labels appear on their own line */
        margin-bottom: 6px; /* Small space below each label */
    }

    /* Style both text and password input fields */
    input[type="text"],
    input[type="password"] {
        width: 100%; /* Full width to fill the container */
        padding: 10px; /* Padding for comfort and clarity */
        margin-bottom: 15px; /* Space below each input */
        border: 1px solid #ccc; /* Light border around the input */
        border-radius: 6px; /* Slightly rounded corners for inputs */
    }

    /* Style for the form button */
    button {
        width: 100%; /* Make the button full width */
        padding: 10px; /* Space inside the button */
        background-color: #4a90e2; /* Blue background */
        color: white; /* White text */
        border: none; /* Remove default border */
        border-radius: 6px; /* Rounded corners */
        font-size: 16px; /* Medium text size */
        cursor: pointer; /* Cursor changes to pointer on hover */
    }

    /* Button hover effect */
    button:hover {
        background-color: #357bd8; /* Slightly darker blue on hover */
    }

    /* Style for message container (used to show error/success messages) */
    .message {
        text-align: center; /* Center the text */
        margin-bottom: 15px; /* Space below the message */
        font-weight: bold; /* Make message text bold */
    }

    /* Additional styling if message is an error */
    .message.error {
        color: red; /* Red text for errors */
    }

    /* Additional styling if message is a success */
    .message.success {
        color: green; /* Green text for success messages */
    }
</style>

</head>
<body>
    <div class="container">
        <h2>Reset Your Password</h2>

        <?php if (isset($error)) echo "<p class='message error'>$error</p>"; ?>
        <?php if (isset($success)) echo "<p class='message success'>$success</p>"; ?>

        <?php if (!isset($success)) : ?>
        <form method="POST">
            <label>Username:</label>
            <input type="text" name="username" required>

            <label>New Password:</label>
            <input type="password" name="new_password" required>

            <label>Confirm Password:</label>
            <input type="password" name="confirm_password" required>

            <button type="submit" name="reset">Reset Password</button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>
