<?php

// Include the config.php file that holds the encrypted password and database credentials.
// This file also contains the encryption key and initialization vector (IV) used to decrypt the password.
include 'config.php';

// Decrypt the encrypted password using OpenSSL before passing it to the database connection.
// openssl_decrypt() uses the AES-128-CBC encryption algorithm.
// Parameters used must match exactly with those in encryption: method, key, and IV.
$password = openssl_decrypt($db_pass_encrypted, "AES-128-CBC", $encryption_key, 0, $encryption_iv);

// Declare the name of the database server.
// 'localhost' means the database is hosted on the same machine as the PHP script.
$servername = $db_host;

// Declare the username used to log into the MySQL database.
$username = $db_user;

// Declare the name of the database you want to connect to.
$dbname = $db_name;

// The $conn variable is being assigned the result of the mysqli object constructor.
// new mysqli() is a constructor function, and it creates a new MySQL database connection object.
// This function takes four parameters: servername, username, password (now decrypted), and database name.
// $conn is a variable that stores the object that allows us to interact with the database.
$conn = new mysqli($servername, $username, $password, $dbname);

// This block checks if the connection to the database failed.
// The connect_error property belongs to the mysqli object ($conn).
// If an error occurred, the program stops using the 'die()' function and prints an error message.
if ($conn->connect_error) {
    // Terminate the script and show the specific connection error message.
    die("Connection failed: " . $conn->connect_error);
}

// If this code is reached, the connection was successful and $conn can be used for queries.
?>
