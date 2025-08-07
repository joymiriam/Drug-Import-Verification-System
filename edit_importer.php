<?php
session_start(); // Start the session to access session variables
include 'Connection.php'; // Include the file that establishes a database connection

// Ensure that only logged-in users with the 'importer' role can access this page
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "importer") {
    header("Location: login.html"); // Redirect to login page if user is not an importer or not logged in
    exit(); // Stop script execution
}

$user_id = $_SESSION["user_id"]; // Store the currently logged-in user's ID from the session

// Fetch current importer details from the database using the user_id
$sql = "SELECT * FROM importer WHERE user_id = ?";
$stmt = $conn->prepare($sql); // Prepare the SQL query
$stmt->bind_param("i", $user_id); // Bind the user_id as an integer parameter
$stmt->execute(); // Execute the query
$result = $stmt->get_result(); // Get the result set
$importer = $result->fetch_assoc(); // Fetch importer data as an associative array
$stmt->close(); // Close the prepared statement

// Check if the form was submitted via POST method
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Get and trim input values from the submitted form
    $name = trim($_POST["name"]);     
    $email = trim($_POST["email"]);   
    $phone = trim($_POST["phone"]);   

    // Update the importer record in the database
    $update_sql = "UPDATE importer SET name = ?, email = ?, phone = ? WHERE user_id = ?";
    $stmt_update = $conn->prepare($update_sql); // Prepare the update query
    $stmt_update->bind_param("sssi", $name, $email, $phone, $user_id); // Bind the updated values and user_id
    $stmt_update->execute(); // Execute the update
    $stmt_update->close(); // Close the update statement

    // Redirect the user back to the dashboard after updating the record
    header("Location: importer_dashboard.php");
    exit(); // Stop further script execution
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <title>Edit Importer Info</title>
    <link rel="stylesheet" href="styles.css">
    <style>
    /* Apply font and background color to the entire body */
    body {
        font-family: Arial, sans-serif; /* Sets the font for the whole page */
        background-color: #eef0f3; /* Light grey background for a soft appearance */
    }

    /* Style the main container for the form */
    .form-container {
        width: 100%; /* Container takes full width of its parent */
        max-width: 600px; /* But does not exceed 600px for readability */
        margin: 60px auto; /* Center the container horizontally with top and bottom margin */
        padding: 30px; /* Add inner spacing */
        background: #fff; /* White background for contrast */
        border-radius: 10px; /* Rounded corners */
        box-shadow: 0 6px 12px rgba(0,0,0,0.15); /* Drop shadow for depth */
    }

    /* Style the heading inside the form */
    h2 {
        text-align: center; /* Center the heading text */
        margin-bottom: 20px; /* Space below the heading */
        color: #5a4080; /* Purple color to match theme */
    }

    /* Style the labels within the form */
    form label {
        display: block; /* Makes label appear on its own line */
        margin-bottom: 8px; /* Space below the label */
        font-weight: bold; /* Bold text for emphasis */
        text-align: left; /* Align label text to the left */
    }

    /* Style all input fields within the form */
    form input {
        width: 100%; /* Full width input fields */
        padding: 10px; /* Space inside input for better readability */
        margin-bottom: 20px; /* Space below each input field */
        border: 1px solid #ccc; /* Light grey border */
        border-radius: 6px; /* Rounded edges for a soft look */
    }

    /* Style the submit button of the form */
    .form-btn {
        background-color: #9474af; /* Primary purple color */
        color: white; /* White text on the button */
        padding: 12px 20px; /* Padding inside the button */
        border: none; /* No border */
        border-radius: 6px; /* Rounded corners */
        cursor: pointer; /* Cursor changes to pointer on hover */
        font-weight: bold; /* Bold text to stand out */
        width: 100%; /* Button takes full width */
    }

    /* Change button color when hovered over */
    .form-btn:hover {
        background-color: #6d4f8d; /* Darker purple on hover */
    }

    /* Style the "back" link below the form */
    .back-link {
        display: block; /* Makes the link take full width */
        margin-top: 15px; /* Space above the link */
        text-align: center; /* Center the link text */
        text-decoration: none; /* Remove underline */
        color: #555; /* Dark grey color for the link */
    }

    /* Underline link when hovered */
    .back-link:hover {
        text-decoration: underline; /* Underline appears on hover */
    }
</style>

</head>
<body>

<div class="form-container">
    <h2>Edit Company Information</h2>
    <form method="POST" action="">
        <label for="name">Company Name</label>
        <input type="text" name="name" id="name" required value="<?php echo htmlspecialchars($importer['name']); ?>">

        <label for="email">Email</label>
        <input type="email" name="email" id="email" required value="<?php echo htmlspecialchars($importer['email']); ?>">

        <label for="phone">Phone</label>
        <input type="text" name="phone" id="phone" required value="<?php echo htmlspecialchars($importer['phone']); ?>">

        <button type="submit" class="form-btn">Update Information</button>
    </form>

    <a href="importer_dashboard.php" class="back-link">Back to Dashboard</a>
</div>

</body>
</html>
