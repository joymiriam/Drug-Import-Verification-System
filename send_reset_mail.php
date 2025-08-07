<?php
// Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Require the PHPMailer classes needed for email functionality
require 'PHPMailer/PHPMailer.php';    // Main PHPMailer class
require 'PHPMailer/SMTP.php';         // SMTP class for sending via SMTP
require 'PHPMailer/Exception.php';    // Exception class for error handling

// Define a function to send the password reset email
// - $to: recipient's email address
// - $token: the unique token to include in the password reset link
function sendResetEmail($to, $token) {
    // Create a new PHPMailer instance with error handling enabled
    $mail = new PHPMailer(true); // 'true' enables exceptions to be thrown if an error occurs

    try {
        
        $mail->isSMTP();                          // Use SMTP protocol for sending email
        $mail->Host       = 'sandbox.smtp.mailtrap.io'; // Mailtrap SMTP host 
        $mail->SMTPAuth   = true;                 // Enable SMTP authentication
        $mail->Username   = 'c55e631bbfa050';     // Mailtrap username
        $mail->Password   = '4bd470ab081d40';     // Mailtrap password
        $mail->SMTPSecure = 'tls';                // Use TLS encryption for secure transmission
        $mail->Port       = 2525;                 // TCP port used by Mailtrap

        $mail->setFrom('noreply@pharmasure.local', 'PharmaSure System'); // Sender's address and name
        $mail->addAddress($to); // Add the recipient's email address

        
        $mail->isHTML(true); // Set the email format to HTML
        $mail->Subject = 'Reset your PharmaSure password'; // Email subject line

        // Email body: contains a link with the unique token for resetting the password
        $mail->Body    = 'Click this link to reset your password:<br><br>' .
                         '<a href="http://localhost/DIVS/reset_password.php?token=' . $token . '">Reset Password</a>';

        // Send the email
        $mail->send();
        return true; // Email sent successfully

    } catch (Exception $e) {
        // If an error occurs, return false
        return false;
    }
}
?>
