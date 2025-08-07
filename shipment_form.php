<?php
// Start the session to manage session variables across pages
session_start();

// Include the file that contains the database connection
include 'Connection.php';

// Check if the database connection was successful
if ($conn->connect_error) {
    // If there's a connection error, stop the script and display an error message
    die("Connection failed: " . $conn->connect_error);
}

// Initialize a variable to store the <option> elements for the dropdown
$drug_options = "";

// SQL query to select drug ID and name from the drugs table, ordered alphabetically by name
$sql = "SELECT drug_id, name FROM drugs ORDER BY name ASC";

// Execute the SQL query and store the result in $result
$result = $conn->query($sql);

// Check if the query returned any rows (if there are drugs in the database)
if ($result->num_rows > 0) {
    // If there are rows, loop through each row of the result
    while ($row = $result->fetch_assoc()) {
        // Sanitize the data using htmlspecialchars to prevent XSS attacks
        $drug_id = htmlspecialchars($row['drug_id']);
        $drug_name = htmlspecialchars($row['name']);

        // Add each drug as an <option> tag to the dropdown options string
        $drug_options .= "<option value=\"$drug_id\">$drug_name</option>";
    }
} else {
    // If no rows were returned, show a default option indicating no drugs are available
    $drug_options = "<option value=\"\">No drugs available</option>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Shipment Registration</title>
    <script src="validation.js" defer></script>
    <link rel="stylesheet" href="styles.css">
    <style>
    /* Apply default margin, padding, and box-sizing to all elements */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box; /* Ensures padding doesn't increase total width/height */
        font-family: Arial, sans-serif; /* Sets a consistent font */
    }

    /* Style the body with a full-screen background image */
    body {
        background-image: url('images/gradient.jpg'); /* Sets the background image */
        background-size: 100%; /* Stretches image to full width */
        background-position: center; /* Centers the image */
        background-repeat: no-repeat; /* Prevents image repetition */
        background-attachment: fixed; /* Keeps the image fixed during scroll */
        min-height: 100vh; /* Ensures full height of viewport */
    }

    /* Header styling for the top section */
    header {
        background-color: rgba(90, 62, 139, 0.9); /* Semi-transparent purple */
        color: white; /* White text color */
        padding: 20px; /* Space inside the header */
        text-align: center; /* Centered header content */
        position: relative; /* Allows positioning of child elements */
        z-index: 10; /* Stacks header above other elements */
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3); /* Adds subtle shadow */
    }

    /* Button inside header that acts as a "Back" link */
    .btn-back {
        position: absolute; /* Removes from normal flow to position freely */
        right: 20px; /* Distance from the right edge of header */
        top: 20px; /* Distance from the top edge of header */
        background-color: #555; /* Dark grey background */
        color: white; /* White text color */
        padding: 10px 20px; /* Space inside the button */
        border-radius: 5px; /* Rounded corners */
        text-decoration: none; /* Removes underline from the link */
    }

    /* Main container for the form */
    .form-container {
        background: rgba(255, 255, 255, 0.95); /* Semi-transparent white background */
        margin: 60px auto; /* Top margin + center horizontally */
        padding: 30px; /* Inside spacing */
        border-radius: 10px; /* Rounded corners */
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); /* Slight shadow effect */
        width: 850px; /* Fixed width of the form */
        text-align: left; /* Align text inside form to the left */
    }

    /* Main heading style (usually for the header, unused here) */
    h1 {
        font-family: Verdana; /* Sets different font for emphasis */
        color: #f0f0f0; /* Light color (may not be visible if background is also light) */
        margin-bottom: 20px; /* Adds space below the heading */
    }

    /* Secondary heading style (used inside the form) */
    h2 {
        font-family: Verdana; /* Consistent font family with h1 */
        color: #5a3e8b; /* Dark purple */
        margin-bottom: 20px; /* Space below the heading */
    }

    /* Label styles for form inputs */
    label {
        display: block; /* Each label on its own line */
        margin: 10px 0 5px; /* Space above and below label */
        font-weight: bold; /* Makes label text bold */
        color: #333; /* Dark gray text */
    }

    /* Input and select field styling */
    input, select {
        width: 100%; /* Full width of container */
        padding: 10px; /* Inner spacing */
        margin-bottom: 15px; /* Space below each field */
        border: 1px solid #ccc; /* Light gray border */
        border-radius: 5px; /* Rounded corners */
        font-size: 16px; /* Medium font size */
    }

    /* Button styling for submission or actions */
    button {
        background-color: #7551b2; /* Purple background */
        color: white; /* White text */
        border: none; /* No border */
        padding: 12px; /* Inside spacing */
        font-size: 16px; /* Text size */
        border-radius: 5px; /* Rounded edges */
        transition: 0.3s ease; /* Smooth hover transition */
        width: 100%; /* Full-width button */
    }

    /* Button hover effect for user feedback */
    button:hover {
        background-color: #583f84; /* Darker purple on hover */
    }
</style>

</head>
<body>
     <header>
        <h1>Shipment Registration Form</h1>
        <a href="importer_dashboard.php" class="btn-back">Back to Dashboard</a>
    </header>
    <div class="form-container">
        <h2>Shipment Registration Form</h2>
        <form id="shipmentForm" action="shipment.php" method="post">
            <label for="drug_id">Select Drug:</label>
            <select id="drug_id" name="drug_id">
                <option value="" selected> Select Drug </option>
                <?php echo $drug_options; ?>
            </select>

            <label for="method">Shipment Method:</label>
            <select id="method" name="method">
                <option value="" selected> Select Method </option>
                <option value="Air">Air</option>
                <option value="Sea">Sea</option>
                <option value="Land">Land</option>
            </select>

            <label for="company">Shipping Company:</label>
            <input type="text" id="company" name="company">

            <label for="port_of_entry">Port of Entry:</label>
            <select id="port_of_entry" name="port_of_entry">
                <option value="" selected> Select Port </option>
                <!-- Airports -->
                <option value="Jomo Kenyatta International Airport">Jomo Kenyatta International Airport </option>
                <option value="Moi International Airport">Moi International Airport </option>
                <option value="Eldoret International Airport">Eldoret International Airport</option>
                <option value="Kisumu International Airport">Kisumu International Airport</option>
                <option value="Wilson Airport">Wilson Airport </option>
                <!-- Seaports -->
                <option value="Port of Mombasa">Port of Mombasa</option>
                <option value="Lamu Port">Lamu Port</option>
                <!-- Land Borders -->
                <option value="Busia">Busia </option>
                <option value="Malaba">Malaba </option>
                <option value="Namanga">Namanga </option>
                <option value="Lunga Lunga">Lunga Lunga </option>
                <option value="Taveta">Taveta </option>
                <option value="Isebania">Isebania </option>
                <option value="Moyale">Moyale </option>
                <option value="Nadapal">Nadapal </option>
                <option value="Mandera">Mandera </option>
                <option value="Liboi">Liboi </option>
            </select>

            <label for="doa">Date of Arrival:</label>
            <input type="text" id="doa" name="doa">

            <button type="submit">Submit</button>
        </form>
    </div>
</body>
</html>
<?php $conn->close(); ?>
