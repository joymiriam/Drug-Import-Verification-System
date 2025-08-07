# PharmaSure – Drug Import Verification System

PharmaSure is a web-based system designed to streamline and secure the process of drug importation in Kenya. It allows importers, customs officers, and PPB (Pharmacy and Poisons Board) officers to manage and verify imported drugs in a centralized and efficient platform.

## Features

- **Importer Dashboard**
  - Register as an importer
  - Submit drug and shipment details
  - View import history and shipment status

- **PPB Officer Dashboard**
  - View pending verification requests
  - Approve or reject drug imports
  - Manage manufacturer and drug records

- **Customs Officer Dashboard**
  - View approved shipments
  - Approve or reject customs clearance
  - Generate shipment reports with filters

- **Admin Dashboard**
  - Manage users
  - View system-wide reports and analytics

- **Authentication**
  - User login, session management
  - Role-based access control
  - Email verification & password reset using Mailtrap

- **Report Filters**
  - Filter data by date (month, quarter, year)
  - Generate summaries by role and report type

## Tech Stack

- **Frontend:** HTML, CSS (light pastel theme), JavaScript
- **Backend:** PHP (no frameworks)
- **Database:** MySQL
- **Tools:** Apache server, PHPMailer, Mailtrap

## How to Run

1. Clone or download the repository
2. Import the SQL database (if provided)
3. Update your `config.php` with your own database credentials
4. Host on Apache (e.g., XAMPP or standalone Apache)
5. Access via `http://localhost/pharmasure`

>  Note: Some credentials and settings have been removed for security.

