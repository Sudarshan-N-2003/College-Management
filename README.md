College Exam Management Portal (VVIT)

This project is a web-based application designed to streamline various tasks related to college examinations, staff management, and student records. It provides different dashboards and functionalities based on user roles (Admin, Staff, HOD, Principal, Student).

Features

Role-Based Access Control: Separate login and dashboard views for different user types.
Admin Dashboard:
Manage Users (Staff, HOD, Principal): Add, Edit, Remove.
Manage Subjects: Add, Edit, Remove.
Manage Students: Add (manually or via CSV), Edit, Remove.
Bulk Upload: Add multiple staff or students via CSV.
Allocate Subjects to Staff (Functionality to be implemented).
Create Timetables (Initial step implemented).
Staff/HOD/Principal Dashboards:
View allocated subjects/staff (based on role).
Generate Question Papers automatically from an Excel question bank.
Enter Student Attendance.
Enter Student IA Results.
(Other role-specific features can be added).
Student Dashboard:
View Attendance records.
View IA Results.
View Timetables (Functionality to be implemented).
Secure Login & Registration: Password hashing and secure session management.
PDF Generation: Generate PDF timetables (requires FPDF library).
Excel Processing: Read question banks from Excel files (requires PhpSpreadsheet library).
Technologies Used
Backend: PHP 8.2
Database: PostgreSQL (specifically designed for NeonDB)
Web Server: Apache (configured via Docker)
Deployment: Render (using Docker runtime)

Dependencies:
Composer (for PHP package management)
phpoffice/phpspreadsheet (for reading Excel files)
setasign/fpdf (for generating PDF files)
Frontend: HTML, CSS, JavaScript (basic interactions)
Setup and Deployment (Render + Neon)
This project is configured for deployment on Render using a Docker container and connecting to a Neon PostgreSQL database.
Clone Repository: Clone this GitHub repository to your local machine or directly link it to Render.
git clone [https://github.com/Appureddy143/amc.git](https://github.com/Appureddy143/amc.git)
cd amc


Set up Neon Database:
Create a new project and database on Neon.
Navigate to the SQL Editor for your database.
Copy the entire contents of the schema.sql file from this repository.
Paste the SQL script into the Neon SQL Editor and Run it. This will create all necessary tables, types, and the initial admin user.
Keep your Neon Connection String handy. You'll need details from it.
Configure Render:
Create a new Web Service on Render.
Connect your GitHub repository (Appureddy143/amc).
Set the Runtime to Docker. Render should automatically detect the Dockerfile.
Go to the Environment tab for your service.
Add the following Environment Variables, taking the values from your Neon connection string:
DB_HOST: e.g., ep-steep-grass-a4zzp7i4-pooler.us-east-1.aws.neon.tech
DB_PORT: 5432
DB_NAME: e.g., neondb
DB_USER: e.g., neondb_owner
DB_PASSWORD: Your Neon database password (e.g., npg_STKDhH8lomb7)
Click Save Changes.

Deploy:
Trigger a manual deploy (Manual Deploy > Deploy latest commit) or push a new commit to GitHub to trigger an automatic deployment.
Render will build the Docker image using the Dockerfile, installing PHP, Apache, Composer dependencies (PhpSpreadsheet, FPDF), and required PHP extensions (pdo_pgsql, gd, zip).
The build process also sets up the necessary permissions for the uploads directory.
Access Application: Once deployed, access your application using the URL provided by Render.
Initial Login
An admin user is created by the schema.sql script:
Email: admin@example.com
Password: admin123
Key Configuration Files
db-config.php: Connects to the database using PDO and reads credentials from Render Environment Variables.
Dockerfile: Defines the server environment, installs PHP extensions (pdo_pgsql, gd, zip), installs Composer, runs composer install, sets up the uploads directory permissions, and copies the application code.
composer.json: Lists PHP dependencies (phpoffice/phpspreadsheet, setasign/fpdf).
schema.sql: Contains the PostgreSQL database schema and initial admin user data. Run this manually in your Neon SQL Editor.

File Uploads
The application requires an uploads/ directory in the web root for storing staff photos, documents, and potentially other files.
The Dockerfile creates this directory and sets the correct permissions (www-data ownership) for Apache to write files into it.
Future Development / TODO
Implement Subject Allocation functionality.
Complete the Timetable Creation process (create-timetable-form.php).
Create dashboard pages for Staff, HOD, and Principal (staff_dashboard.php, etc.).
Implement detail view pages (view-user-details.php, view-staff-details.php, view-paper.php, etc.).

Add password reset functionality via email (currently relies on admin intervention or direct database update).

Enhance security (input validation, CSRF protection, etc.).

Refine UI/UX.
