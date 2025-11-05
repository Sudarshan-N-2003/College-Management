<?php
session_start();
require_once "db.php"; // Uses your PDO-based PostgreSQL connection

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Trim and collect inputs safely
    $usn = trim($_POST['usn'] ?? '');
    $student_name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $branch = trim($_POST['branch'] ?? '');
    $semester = trim($_POST['semester'] ?? '');
    $password_plain = $_POST['password'] ?? '';

    // Basic validation
    if (empty($usn) || empty($student_name) || empty($email) || empty($dob) || empty($password_plain)) {
        die("<p style='color:red;text-align:center;'>❌ All required fields must be filled.</p>");
    }

    // Hash the password
    $password = password_hash($password_plain, PASSWORD_DEFAULT);

    try {
        // Prepare insert query (PostgreSQL-style placeholders)
        $sql = "INSERT INTO students 
                (usn, student_name, email, dob, address, password, branch, semester) 
                VALUES 
                (:usn, :student_name, :email, :dob, :address, :password, :branch, :semester)";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':usn' => $usn,
            ':student_name' => $student_name,
            ':email' => $email,
            ':dob' => $dob,
            ':address' => $address,
            ':password' => $password,
            ':branch' => $branch,
            ':semester' => !empty($semester) ? (int)$semester : null
        ]);

        echo "<p style='color:green;text-align:center;'>✅ Student added successfully!</p>";
        echo "<p style='text-align:center;'><a href='add-student.php'>Back</a></p>";
        exit;

    } catch (PDOException $e) {
        echo "<p style='color:red;text-align:center;'>❌ Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p style='text-align:center;'><a href='add-student.php'>Back</a></p>";
        exit;
    }
} else {
    header("Location: add-student.php");
    exit;
}
?>