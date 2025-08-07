// Function to validate if any of the fields in a given list are empty
function validateEmptyFields(fieldIds) {
    // Loop through each element in the array fieldIds
    for (let i = 0; i < fieldIds.length; i++) {

        // Get the input field element from the HTML document using its ID
        let field = document.getElementById(fieldIds[i]);

        // Check if the field does not exist OR the field's value is empty after removing whitespace
        // !field means the element was not found (null)
        // .value accesses the content typed in the input field
        // .trim() removes any leading or trailing spaces
        if (!field || field.value.trim() === "") {

            // Display an alert to the user
            // field.getAttribute("name") fetches the name attribute from the input tag
            // If name is missing, use the phrase "the required field" instead
            alert(`Please enter ${field.getAttribute("name") || "the required field"}.`);

            // Set focus on the empty input field so the user can type into it
            field.focus();

            // Stop the validation and return false to indicate failure
            return false;
        }
    }

    // If the loop completes without finding any empty fields, return true
    return true;
}

// Function to validate if the email is in a basic valid format
function validateEmail() {
    // Get the value typed in the email input field and remove any spaces from both ends
    let email = document.getElementById("email").value.trim();

    // Check three conditions:
    // 1. Email is empty
    // 2. It doesn't contain an @ symbol
    // 3. It doesn't contain a dot 
    // indexOf("@") == -1 means @ is not present in the string
    if (email.length == 0 || email.indexOf("@") == -1 || email.indexOf(".") == -1) {

        // Show an error message to the user
        alert("You must enter a valid email.");

        // Focus on the email input field so the user can correct it
        document.getElementById("email").focus();

        // Return false to indicate that the email is not valid
        return false;
    }

    // If all checks pass, return true to indicate the email is valid
    return true;
}

// Function to validate if the phone number is a valid 10-digit number
function validatePhone() {
    // Get the phone input value and trim extra spaces
    let phone = document.getElementById("phone").value.trim();

    // Check two things:
    // 1. The phone number must be exactly 10 characters long
    // 2. The phone number must be all digits 
    // isNaN(phone) returns true if phone contains non-numeric characters
    if (phone.length !== 10 || isNaN(phone)) {

        // Display an alert for invalid input
        alert("Please enter a valid 10-digit phone number.");

        // Focus on the phone input field
        document.getElementById("phone").focus();

        // Return false to stop form submission
        return false;
    }

    // If phone number is valid, return true
    return true;
}

// Function to validate the entire Users form when registering or adding users
function validateUsersForm() {
    // Define a list of input field IDs that are required in the Users form
    let fieldsToCheck = ["first_name", "last_name", "username", "password", "role", "email", "phone"];
    
    // Run 3 validation functions:
    // 1. Check that all required fields are filled
    // 2. Validate email format
    // 3. Validate phone number format
    // If any of these fail , the form will not be submitted
    if (!validateEmptyFields(fieldsToCheck) || !validateEmail() || !validatePhone()) {
        return false;
    }

    // If all validations pass, return true to allow form submission
    return true;
}

// Function to validate login form inputs before submission
function validateLoginForm() {
    // Get the username input value and remove spaces
    let username = document.getElementById("Loginusername").value.trim();

    // Get the password input value and remove spaces
    let password = document.getElementById("loginPassword").value.trim();

    // Get the selected role input value and remove spaces
    let role = document.getElementById("role").value.trim();

    // Check if username field is empty
    if (username === "") {
        alert("Please enter your username."); // Show alert
        document.getElementById("Loginusername").focus(); // Focus on the field
        return false; // Stop form submission
    }

    // Check if password field is empty
    if (password === "") {
        alert("Please enter your password.");
        document.getElementById("loginPassword").focus();
        return false;
    }

    // Check if role field is empty
    if (role === "") {
        alert("Please enter your role.");
        document.getElementById("role").focus();
        return false;
    }

    // If all three fields are filled, return true to allow form submission
    return true;
}

// Function to validate the Drugs form before submission
function validateDrugsForm() {
    // Define an array of field IDs that are required in the Drugs form
    let fieldsToCheck = ["name", "batch_no", "dom", "doe", "type", "strength", "quantity"];

    // Validate that all required fields are not empty
    // If any field is empty, stop and return false
    if (!validateEmptyFields(fieldsToCheck)) {
        return false;
    }

    // Check that the DOM (Date of Manufacture) is in the past
    // and DOE (Date of Expiry) is in the future
    // These are custom functions assumed to be defined elsewhere
    if (!validatePastDate("dom") || !validateFutureDate("doe")) {
        return false;
    }

    // Get the values of DOM and DOE and remove extra whitespace
    let dom = document.getElementById("dom").value.trim();
    let doe = document.getElementById("doe").value.trim();

    // Use a compare function to ensure DOM is earlier than DOE
    // If DOM is equal to or after DOE, it's invalid
    if (compareDates(dom, doe) >= 0) {
        alert("Date of Manufacture (DOM) must be before Date of Expiry (DOE).");
        document.getElementById("dom").focus();
        return false;
    }

    // Get the quantity field value and trim spaces
    let quantity = document.getElementById("quantity").value.trim();

    // Check if the quantity is not a number or less than or equal to 0
    // isNaN checks if input is Not a Number
    if (isNaN(quantity) || quantity <= 0) {
        alert("Quantity must be a valid positive number.");
        document.getElementById("quantity").focus();
        return false;
    }

    // All validations passed, return true to allow form submission
    return true;
}

// Function to validate the Importer registration form
function validateImporterForm() {
    // List of required field IDs for importer form
    let fieldsToCheck = ["name", "phone", "address", "license_no", "email", "registration_no"];

    // Validate: fields are not empty, phone is valid, email is valid
    if (!validateEmptyFields(fieldsToCheck) || !validatePhone() || !validateEmail()) {
        return false;
    }

    // If everything is valid, allow form submission
    return true;
}

// Function to check if the user selected an option for GMP Certificate Verification
function validateGmpCertVerif() {
    // Get all radio buttons with name="gmp_cert_verif"
    let radios = document.getElementsByName("gmp_cert_verif");

    // Loop through each radio button to see if any is checked
    for (let radio of radios) {
        if (radio.checked) {
            // If one is selected, validation passes
            return true;
        }
    }

    // If none were checked, show an alert and prevent submission
    alert("Please select GMP Certificate Verification (Yes or No).");
    return false;
}

// Function to validate the Manufacturer registration form
function validateManufacturerForm() {
    // Required fields for manufacturer form
    let fieldsToCheck = ["name", "address", "gmp_certNo", "doi", "email"];

    // Check: all required fields are filled, and email is valid
    if (!validateEmptyFields(fieldsToCheck) || !validateEmail()) {
        return false;
    }

    // Validate DOI (Date of Inspection) using a general date validation function
    if (!validateDate("doi")) {
        return false;
    }

    // Ensure user selected Yes or No for GMP certificate verification
    if (!validateGmpCertVerif()) {
        return false;
    }

    // If all checks pass, return true
    return true;
}

// Function to validate the Shipment form
function validateShipmentForm() {
    // Required fields for shipment form
    let fieldsToCheck = ["method", "company", "port_of_entry", "doa"];

    // Check that none of the fields are empty
    if (!validateEmptyFields(fieldsToCheck)) {
        return false;
    }

    // Validate that the Date of Arrival is a future date
    if (!validateFutureDate("doa")) {
        return false;
    }

    // All fields valid and DOA is a future date, return true
    return true;
}
// Function to convert a date string in "dd-mm-yyyy" format into a JavaScript Date object
function parseDateDMY(dateStr) {
    // Split the string by the dash (-) character into an array of 3 parts
    let parts = dateStr.split("-");

    // If there are not exactly 3 parts (day, month, year), return null (invalid format)
    if (parts.length !== 3) return null;

    // Convert the first part to a number for the day
    let day = parseInt(parts[0], 10);

    // Convert the second part to a number for the month, subtract 1 because JavaScript months are 0-indexed (0 = January)
    let month = parseInt(parts[1], 10) - 1;

    // Convert the third part to a number for the year
    let year = parseInt(parts[2], 10);

    // Create a new Date object using the parsed year, month, and day
    let dateObj = new Date(year, month, day);

    // Validate that the Date object actually matches the original values
    // This checks for cases like "31-02-2023" which is not a real date
    if (dateObj.getFullYear() !== year || dateObj.getMonth() !== month || dateObj.getDate() !== day) {
        return null; // Invalid date
    }

    // If valid, return the Date object
    return dateObj;
}

// Function to validate that a given date (from a field) is in the future
function validateFutureDate(fieldId) {
    // Get the value of the input field by its ID and remove whitespace
    let dateStr = document.getElementById(fieldId).value.trim();

    // If the field is empty, show an error message
    if (dateStr === "") {
        alert(`Please enter a valid date (DD-MM-YYYY) for ${fieldId.toUpperCase()}.`);
        document.getElementById(fieldId).focus(); // Focus on the field
        return false;
    }

    // Convert the string to a Date object
    let givenDate = parseDateDMY(dateStr);

    // If the date is invalid (not parseable), show an error
    if (!givenDate) {
        alert(`Date must be in DD-MM-YYYY format and valid.`);
        document.getElementById(fieldId).focus();
        return false;
    }

    // Get today's date, with the time reset to midnight
    let today = new Date();
    today.setHours(0, 0, 0, 0); // Remove time portion for accurate comparison

    // Check if the given date is not in the future
    if (givenDate <= today) {
        alert(`${fieldId.toUpperCase()} must be in the future.`);
        document.getElementById(fieldId).focus();
        return false;
    }

    // If the date is in the future, return true
    return true;
}

// Function to validate that a given date (from a field) is in the past
function validatePastDate(fieldId) {
    // Get the input value and trim it
    let dateStr = document.getElementById(fieldId).value.trim();

    // Check if the field is empty
    if (dateStr === "") {
        alert(`Please enter a valid date (DD-MM-YYYY) for ${fieldId.toUpperCase()}.`);
        document.getElementById(fieldId).focus();
        return false;
    }

    // Parse the date string into a Date object
    let givenDate = parseDateDMY(dateStr);

    // If the date format is invalid, show an alert
    if (!givenDate) {
        alert(`Date must be in DD-MM-YYYY format and valid.`);
        document.getElementById(fieldId).focus();
        return false;
    }

    // Get today's date (with time removed)
    let today = new Date();
    today.setHours(0, 0, 0, 0);

    // Check if the date is not in the past
    if (givenDate >= today) {
        alert(`${fieldId.toUpperCase()} must be in the past.`);
        document.getElementById(fieldId).focus();
        return false;
    }

    // If the date is valid and in the past, return true
    return true;
}

// General-purpose date validation function that simply checks if the date is in the past
// It's a wrapper for validatePastDate for easier naming in some contexts
function validateDate(fieldId) {
    return validatePastDate(fieldId);
}

// Function to compare two dates in dd-mm-yyyy format
function compareDates(date1, date2) {
    // Convert both date strings to Date objects
    let d1 = parseDateDMY(date1);
    let d2 = parseDateDMY(date2);

    // If either date failed to parse, return NaN (Not a Number)
    if (!d1 || !d2) return NaN;

    // Subtract one date from another
    // JavaScript Date objects can be subtracted, resulting in the difference in milliseconds
    return d1 - d2;
}
// Function to validate the Verification form before submission
function validateVerificationForm() {
    // Define the IDs of required fields that must not be empty
    let fieldsToCheck = ["status", "dov", "inspector_name"];

    // Call the validateEmptyFields function to check if any field is empty
    if (!validateEmptyFields(fieldsToCheck)) {
        return false; // If any field is empty, stop form submission
    }

    // Call the validateDate function to check if the date of verification (dov) is a past date
    if (!validateDate("dov")) {
        return false; // If DOV is invalid (e.g., in the future), stop form submission
    }

    // Get the value of the verification status from the form (e.g., Approved or Rejected)
    let status = document.getElementById("status").value.trim();

    // Get the value of the rejection reason input field
    let rejectionReason = document.getElementById("rejection_reason").value.trim();

    // If the status is "Rejected" but no rejection reason was entered, show an alert
    if (status === "Rejected" && rejectionReason === "") {
        alert("Rejection Reason is required when status is 'Rejected'.");
        document.getElementById("rejection_reason").focus(); // Focus on the rejection reason field
        return false; // Prevent form submission
    }

    return true; // All validations passed; allow form submission
}

// Function to validate the Document Verification form before it is submitted
function validateDocumentVerificationForm() {
    // Define the IDs of the required fields that must be filled
    let fieldsToCheck = ["category", "type", "identification_number", "path"];

    // Use the validateEmptyFields function to check for empty fields
    if (!validateEmptyFields(fieldsToCheck)) {
        return false; // Stop submission if any field is empty
    }

    // Get the file input element for the uploaded document
    let fileInput = document.getElementById("path");

    // Validate the uploaded file (type and size)
    if (!validateFile(fileInput)) {
        return false; // Stop submission if file validation fails
    }

    return true; // All checks passed; form is valid for submission
}

// Function to validate an uploaded file
// It ensures that only PDF files are accepted and the file size does not exceed 5MB
function validateFile(fileInput) {
    // Check if any file is selected (fileInput.files will be an array of selected files)
    if (fileInput.files.length === 0) {
        alert("Please upload a document."); // Show alert if no file selected
        fileInput.focus(); // Focus on the file input field
        return false; // Prevent form submission
    }

    // Access the first (and usually only) uploaded file
    let file = fileInput.files[0];

    // Get the name of the file and convert it to lowercase to make extension checks case-insensitive
    let fileName = file.name.toLowerCase();

    // Check if the file extension is not ".pdf"
    if (!fileName.endsWith(".pdf")) {
        alert("Only PDF files are allowed."); // Show alert if file is not a PDF
        fileInput.value = ""; // Clear the file input field
        fileInput.focus(); // Focus back on file input
        return false; // Stop the form from submitting
    }

    // Check if the file size exceeds 5MB (5 * 1024 * 1024 bytes)
    if (file.size > 5 * 1024 * 1024) {
        alert("File size must be less than 5MB."); // Alert user if file is too large
        fileInput.value = ""; // Clear the file input
        fileInput.focus(); // Refocus the input field
        return false; // Stop form submission
    }

    return true; // File is valid (PDF and within size limit)
}

// Wait until the entire HTML document is fully loaded before running any JavaScript
document.addEventListener("DOMContentLoaded", function () {

    // Get a reference to the login form using its ID
    const form = document.getElementById("loginForm");

    // Get a reference to the paragraph element where the CAPTCHA question will be displayed
    const captchaQuestion = document.getElementById("captchaQuestion");

    // Get a reference to the input field where the user will enter their answer to the CAPTCHA
    const captchaInput = document.getElementById("captchaAnswer");

    // Get a reference to the element where error messages (if any) will be shown
    const captchaError = document.getElementById("captchaError");

    // Define an array of numbers that will be used to generate random values for the CAPTCHA
    const numbers = [1, 2, 3, 4, 5, 6, 7, 8, 9];

    // Define an array of arithmetic operators to be randomly selected for the CAPTCHA
    const operators = ['+', '-'];

    // Declare a variable to store the correct answer to the generated CAPTCHA question
    let correctAnswer;

    // Define a function that generates a new arithmetic CAPTCHA question
    function generateCaptcha() {
        // Select a random number from the numbers array as the first operand
        const num1 = numbers[Math.floor(Math.random() * numbers.length)];

        // Select another random number from the numbers array as the second operand
        const num2 = numbers[Math.floor(Math.random() * numbers.length)];

        // Select a random operator from the operators array
        const op = operators[Math.floor(Math.random() * operators.length)];

        // Combine the two numbers and operator into a string 
        let question = `${num1} ${op} ${num2}`;

        // Use eval() to calculate the result of the question 
        correctAnswer = eval(question);

        // Display the question in the HTML page by setting the inner text of the paragraph
        captchaQuestion.innerText = `What is ${question}?`;
    }

    // Call the generateCaptcha function immediately so a question appears as soon as the page loads
    generateCaptcha();

    // Add an event listener to the form so that when the user tries to submit it, this function runs
    form.addEventListener("submit", function (e) {

        // Convert the user's input from string to number and compare it to the correct answer
        if (parseInt(captchaInput.value) !== correctAnswer) {
            // If the answer is incorrect, prevent the form from being submitted
            e.preventDefault();

            // Display an error message in red below the input field
            captchaError.textContent = "Incorrect. Please try again.";

            // Generate a new CAPTCHA question since the previous one has already been attempted
            generateCaptcha();

            // Clear the user's input so they can enter a new answer
            captchaInput.value = "";
        } else {
            // If the answer is correct, clear any previous error messages
            captchaError.textContent = "";
            // The form will be submitted normally
        }
    });
});

// Event listeners for form validation
document.addEventListener("DOMContentLoaded", function () {
    // Wait until the full HTML document is loaded before running the code

    let loginForm = document.getElementById("loginForm");
    // Get the login form element by its ID

    if (loginForm) {
        // Check if the login form exists in the document
        loginForm.addEventListener("submit", function (event) {
            // Add a submit event listener to the login form
            if (!validateLoginForm()) {
                // Call the login form validation function; if it returns false
                event.preventDefault();
                // Stop the form from submitting
            }
        });
    }

    let usersForm = document.getElementById("UsersForm");
    // Get the user registration form by its ID

    if (usersForm) {
        // Check if the user form exists
        usersForm.addEventListener("submit", function (event) {
            // Add a submit listener to the user form
            if (!validateUsersForm()) {
                // If the validation fails (returns false)
                event.preventDefault();
                // Prevent form submission
            }
        });
    }

    let drugsForm = document.getElementById("drugsForm");
    // Get the drug registration form by its ID

    if (drugsForm) {
        // If the drug form exists
        drugsForm.addEventListener("submit", function (event) {
            // Add submit listener to the drug form
            if (!validateDrugsForm()) {
                // Call validation function for the drug form
                event.preventDefault();
                // Stop submission if validation fails
            }
        });
    }

    let importerForm = document.getElementById("importerForm");
    // Get the importer registration form by its ID

    if (importerForm) {
        // If the importer form is present
        importerForm.addEventListener("submit", function (event) {
            // Attach submit listener to the importer form
            if (!validateImporterForm()) {
                // Run validation function for importer form
                event.preventDefault();
                // Prevent form from submitting if validation fails
            }
        });
    }

    let manufacturerForm = document.getElementById("manufacturerForm");
    // Get the manufacturer registration form element

    if (manufacturerForm) {
        // If the manufacturer form exists
        manufacturerForm.addEventListener("submit", function (event) {
            // Add event listener for form submission
            if (!validateManufacturerForm()) {
                // Validate manufacturer form inputs
                event.preventDefault();
                // Prevent form submission if validation fails
            }
        });
    }

    let shipmentForm = document.getElementById("shipmentForm");
    // Get the shipment form from the document

    if (shipmentForm) {
        // If the shipment form exists
        shipmentForm.addEventListener("submit", function (event) {
            // Attach an event listener to the shipment form
            if (!validateShipmentForm()) {
                // Call the shipment form validation function
                event.preventDefault();
                // Prevent form from submitting if inputs are invalid
            }
        });
    }

    let verificationForm = document.getElementById("verificationForm");
    // Get the verification form from the page

    if (verificationForm) {
        // Check if the verification form exists
        verificationForm.addEventListener("submit", function (event) {
            // Attach a submit listener to the verification form
            if (!validateVerificationForm()) {
                // Run validation for verification form
                event.preventDefault();
                // Block the form from being submitted if validation fails
            }
        });
    }

    let documentVerificationForm = document.getElementById("documentSubmissionForm");
    // Get the document submission form element

    if (documentVerificationForm) {
        // If the document submission form is found
        documentVerificationForm.addEventListener("submit", function (event) {
            // Attach a submit event listener to the document form
            if (!validateDocumentVerificationForm()) {
                // Run validation function for this form
                event.preventDefault();
                // Prevent form from being submitted if validation fails
            }
        });
    }

});
