<?php
session_start();
require_once 'db.php'; // uses PDO $pdo

// Restrict access to admin only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$message = "";

// Handle CSV upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['student_csv'])) {
    $fileTmp = $_FILES['student_csv']['tmp_name'];

    if ($_FILES['student_csv']['size'] > 0) {
        $file = fopen($fileTmp, "r");

        // Skip header if present
        $header = fgetcsv($file);

        $insertSQL = "
            INSERT INTO students (usn, student_name, email, dob, semester, password)
            VALUES (:usn, :student_name, :email, :dob, :semester, :password)
            ON CONFLICT (usn) DO NOTHING
        ";
        $stmt = $pdo->prepare($insertSQL);

        $count = 0;

        while (($data = fgetcsv($file, 1000, ",")) !== FALSE) {
            // Expected columns: USN, Name, Email, DOB, Semester, Password
            $usn = trim($data[0] ?? '');
            $name = trim($data[1] ?? '');
            $email = trim($data[2] ?? '');
            $dob = !empty($data[3]) ? date('Y-m-d', strtotime($data[3])) : null;
            $semester = (int)($data[4] ?? 1);
            $password = !empty($data[5]) ? password_hash($data[5], PASSWORD_DEFAULT) : password_hash('123456', PASSWORD_DEFAULT);

            if ($usn && $name && $email) {
                $stmt->execute([
                    ':usn' => $usn,
                    ':student_name' => $name,
                    ':email' => $email,
                    ':dob' => $dob,
                    ':semester' => $semester,
                    ':password' => $password
                ]);
                $count++;
            }
        }

        fclose($file);
        $message = "✅ Successfully added $count students!";
    } else {
        $message = "⚠️ Please upload a valid CSV file.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Students</title>
<style>
body {
    font-family: Arial, sans-serif;
    background-color: #f4f4f9;
    margin: 0; padding: 0;
}
.navbar {
    background: #333; color: #fff; padding: 10px;
    text-align: center;
}
.navbar a { color: #fff; text-decoration: none; margin: 0 10px; }
.content {
    width: 85%; margin: 30px auto;
    background: #fff; padding: 25px;
    border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
h2 { text-align: center; color: #333; }
form { display: flex; flex-direction: column; align-items: center; }
input, select, textarea, button {
    width: 80%; max-width: 400px; padding: 10px; margin: 8px 0;
    border: 1px solid #ccc; border-radius: 5px;
}
button {
    background: #4CAF50; color: #fff; border: none; cursor: pointer;
}
button:hover { background: #45a049; }
.message {
    text-align: center; font-weight: bold; color: #155724;
    background: #d4edda; border: 1px solid #c3e6cb;
    padding: 10px; border-radius: 5px; margin-bottom: 15px;
}
</style>
</head>
<body>

<div class="navbar">
    <a href="admin-panel.php">← Back to Dashboard</a>
</div>

<div class="content">
    <h2>Add Students</h2>

    <?php if (!empty($message)) echo "<p class='message'>$message</p>"; ?>

    <h3>📂 Upload CSV (Bulk)</h3>
    <form action="add-student.php" method="POST" enctype="multipart/form-data">
        <input type="file" name="student_csv" accept=".csv" required>
        <button type="submit">Upload</button>
        <button type="button" onclick="downloadSample()">Download Sample CSV</button>
    </form>

    <h3>👤 Add Student Manually</h3>
    <form action="manual-add-student.php" method="POST">
        <input type="text" name="usn" placeholder="USN" required>
        <input type="text" name="name" placeholder="Full Name" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="date" name="dob" required>

        <!-- Semester -->
        <select name="semester" required>
            <option value="">-- Select Semester --</option>
            <?php
            // Fetch semesters dynamically from DB
            $semesters = $pdo->query("SELECT id, name FROM semesters ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($semesters as $sem) {
                echo "<option value='{$sem['id']}'>{$sem['name']}</option>";
            }
            ?>
        </select>

        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Add Student</button>
    </form>
</div>

<script>
function downloadSample() {
    const csvContent = "USN,Name,Email,DOB,Semester,Password\n1RV23CS001,John Doe,john@example.com,2004-06-12,3,123456";
    const blob = new Blob([csvContent], { type: "text/csv" });
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = "sample_students.csv";
    a.click();
}
</script>
</body>
</html>