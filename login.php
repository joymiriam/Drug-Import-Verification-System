<?php 
session_start(); // Start the session 

include 'Connection.php'; // Include the database connection file
include 'activity.php';   // Include the file that contains the log_activity function

// Check if the form was submitted using the POST method
if ($_SERVER["REQUEST_METHOD"] == "POST") {
   
    // Retrieve submitted form data
    $username = $_POST["Loginusername"];    // The username entered by the user
    $password = $_POST["loginPassword"];    // The password entered by the user
    $role = $_POST["role"];                 // The selected user role 

    // Make sure no field is left empty
    if (empty($username) || empty($password) || empty($role)) {
        die("Fill all the required fields."); // Exit the script if any field is empty
    }

    // Prepare a SQL query to check for a user with the provided username and role
    $sql = "SELECT * FROM users WHERE username = ? AND role = ?";
    $stmt = $conn->prepare($sql);                    // Prepare the SQL statement to prevent SQL injection
    $stmt->bind_param("ss", $username, $role);       // Bind parameters: both are strings (s, s)
    $stmt->execute();                                // Execute the prepared statement
    $result = $stmt->get_result();                   // Get the result set

    // Check if a user with the given credentials was found
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc(); // Fetch user data as an associative array

        // Verify the entered password against the hashed password stored in the database
        // password_verify() will hash the entered password and compare it securely to the stored hash
        if (password_verify($password, $user["password"])) {

            // Store key user information in session variables
            $_SESSION["user_id"] = $user["user_id"];
            $_SESSION["username"] = $user["username"];
            $_SESSION["role"] = $user["role"];
            

            // Log the login event to the activity table
            log_activity($conn, $user["user_id"], "User logged in", "Login");

            // If the user is an importer, fetch their importer ID (imp_id)
            if ($role === "importer") {
                $importer_sql = "SELECT imp_id FROM importer WHERE user_id = ?";
                $importer_stmt = $conn->prepare($importer_sql);
                $importer_stmt->bind_param("i", $user["user_id"]); 
                $importer_stmt->execute();
                $importer_result = $importer_stmt->get_result();

                // If the importer is found, store their imp_id in session
                if ($importer_result->num_rows == 1) {
                    $importer = $importer_result->fetch_assoc();
                    $_SESSION["imp_id"] = $importer["imp_id"];
                }

                $importer_stmt->close(); // Close importer query statement

                // Redirect the importer to their dashboard
                header("Location: importer_dashboard.php");

            } elseif ($role === "ppb") {
                // Redirect PPB users to the PPB dashboard
                header("Location: ppb_dashboard.php");

            } elseif ($role === "admin") {
                // Redirect Admin users to the Admin dashboard
                header("Location: admin_dashboard.php");

            } elseif ($role === "Customs") {
                // Redirect Customs officers to the Customs dashboard
                header("Location: customs_dashboard.php");
            }

            exit(); // Terminate script after redirection

        } else {
            // Password did not match
            echo "Invalid username or password.";
        }

    } else {
        // No user found with that username and role
        echo "Invalid username or password.";
    }

    // Clean up: close the prepared statement and database connection
    $stmt->close();
    $conn->close();
}
?>
