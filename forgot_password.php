<?php
session_start(); // Start or resume the session to manage messages or session state

// Include the database connection and the email sending function
include 'Connection.php';           // Connects to the database
include 'send_reset_mail.php';      // Contains the function to send reset emails

// Check if the form was submitted 
if (isset($_POST['submit'])) {
    // Retrieve the email entered by the user, trim any whitespace, and convert to lowercase
    $email = strtolower(trim($_POST['email'])); 

    // Prepare an SQL statement to check if a user with that email exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email); // Bind the email as a string parameter
    $stmt->execute(); // Execute the query
    $stmt->store_result(); // Store the result for checking number of rows

    // If a user with the email exists
    if ($stmt->num_rows === 1) {
        // Generate a secure random token and set an expiry time 
        $token = bin2hex(random_bytes(32)); // 64-character secure token
        $expires = date("Y-m-d H:i:s", time() + 3600); // Format the expiry timestamp

        // Delete any existing password reset tokens for this email to avoid duplicates
        $delete = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
        $delete->bind_param("s", $email);
        $delete->execute(); // Run the delete query
        $delete->close();   // Close the delete statement

        // Close the previous SELECT statement before preparing a new one
        $stmt->close();

        // Prepare an INSERT query to store the new token and its expiry
        $insert = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
        $insert->bind_param("sss", $email, $token, $expires); // Bind email, token, and expiry timestamp

        // If the insert is successful
        if ($insert->execute()) {
            // Attempt to send the password reset email using the helper function
            if (sendResetEmail($email, $token)) {
                $message = "A reset link has been sent to your email."; // Success message
                $message_type = "success";
            } else {
                $message = "Token saved, but failed to send email."; // Email sending failed
                $message_type = "error";
            }
        } else {
            $message = "Could not initiate password reset. Please try again."; // Insert failed
            $message_type = "error";
        }

        $insert->close(); // Close the insert statement
    } else {
        // No user found with the provided email
        $message = "No account found with that email.";
        $message_type = "error";
    }
}
?>


<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password</title>
    <style>
    /* Style the entire body */
    body {
        background-color: #f2f9ff; /* Light blue background for a calm, clean look */
        font-family: Arial, sans-serif; /* Use Arial or a similar sans-serif font for readability */
    }

    /* Style the main form container */
    .container {
        max-width: 400px; /* Limit the container’s width to 400px */
        margin: 100px auto; /* Center the container horizontally and push it 100px from the top */
        background: white; /* White background to contrast the body */
        padding: 30px; /* Space inside the container around the content */
        border-radius: 12px; /* Rounded corners */
        box-shadow: 0 0 10px rgba(0,0,0,0.1); /* Light shadow around the container for depth */
    }

    /* Style the heading inside the form */
    h2 {
        text-align: center; /* Center the heading text */
        margin-bottom: 20px; /* Space below the heading */
        color: #333; /* Dark grey color for good contrast and readability */
    }

    /* Style labels for form inputs */
    label {
        font-weight: bold; /* Make label text bold */
        display: block; /* Force label to be on its own line */
        margin-bottom: 6px; /* Space below the label before the input */
    }

    /* Style the email input field */
    input[type="email"] {
        width: 100%; /* Input takes full width of the container */
        padding: 10px; /* Space inside the input field */
        margin-bottom: 15px; /* Space below the input before the next element */
        border: 1px solid #ccc; /* Light grey border around the input */
        border-radius: 6px; /* Slightly rounded corners */
    }

    /* Style the submit button */
    button {
        width: 100%; /* Button fills the container’s width */
        padding: 10px; /* Space inside the button */
        background-color: #4a90e2; /* Blue background */
        color: white; /* White text on the button */
        border: none; /* Remove border */
        border-radius: 6px; /* Rounded edges */
        font-size: 16px; /* Medium-large text size */
        cursor: pointer; /* Pointer cursor on hover to indicate it’s clickable */
    }

    /* Change button color on hover */
    button:hover {
        background-color: #357bd8; /* Darker blue when hovered over */
    }

    /* General style for message boxes (e.g. success or error messages) */
    .message {
        text-align: center; /* Center the text */
        margin-bottom: 15px; /* Space below the message */
        font-weight: bold; /* Make message text bold */
    }

    /* Style message when it indicates success */
    .message.success {
        color: green; /* Green text for success messages */
    }

    /* Style message when it indicates an error */
    .message.error {
        color: red; /* Red text for error messages */
    }
</style>

</head>
<body>
    <div class="container">
        <h2>Forgot Password</h2>

        <?php if (isset($message)) : ?>
            <p class="message <?php echo $message_type; ?>"><?php echo $message; ?></p>
        <?php endif; ?>

        <form method="POST" action="">
            <label>Enter your email address:</label>
            <input type="email" name="email" required>
            <button type="submit" name="submit">Send Reset Link</button>
        </form>
    </div>
</body>
</html>
