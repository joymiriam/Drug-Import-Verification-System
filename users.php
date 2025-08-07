<?php
// Database connection
include 'Connection.php'; // Include database connection
include 'activity.php';   // Include the file where log_activity is defined

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form values from POST request
    $first_name = $_POST["first_name"];
    $last_name = $_POST["last_name"];
    $phone = $_POST["phone"];
    $email = $_POST["email"];
    $username = $_POST["username"];
    $password = $_POST["password"]; 
    $role = $_POST["role"];

    //Check if any field is empty
    if (empty($first_name) || empty($last_name) || empty($phone) || empty($email) || empty($username) || empty($password) || empty($role)) {
        die("All fields are required.");
    }

    // Hash the password before storing it in the database
    // This ensures that the actual password is not stored, making the system more secure
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Prepare the SQL INSERT statement using prepared statements to avoid SQL injection
    $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, phone, email, username, password, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $first_name, $last_name, $phone, $email, $username, $hashed_password, $role);

    // Execute the SQL statement
    if ($stmt->execute()) {
        // Get the ID of the newly registered user
        $new_user_id = $conn->insert_id;

        // Log the registration activity
        log_activity($conn, $new_user_id, "User registered with email: ", "User Registration");

        // Redirect to the login page with a success message
        echo "<script>alert('User registered successfully!'); window.location.href='login.html';</script>";
        exit();
    } else {
        // Display error if something goes wrong
        echo "Error: " . $stmt->error;
    }

    // Close the statement and database connection
    $stmt->close();
    $conn->close();
}
?>
