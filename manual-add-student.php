<?php
session_start();
require_once('db.php'); // PDO connection ($pdo)

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usn       = trim($_POST['usn'] ?? '');
    $name      = trim($_POST['name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $dob       = $_POST['dob'] ?? null;
    $address   = trim($_POST['address'] ?? '');
    $branch    = trim($_POST['branch'] ?? '');
    $semester  = (int)($_POST['semester'] ?? 1);
    $password  = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("
            INSERT INTO students (usn, student_name, email, password, dob, semester, allotted_branch_management)
            VALUES (:usn, :name, :email, :password, :dob, :semester, :branch)
        ");
        $stmt->execute([
            ':usn'      => $usn,
            ':name'     => $name,
            ':email'    => $email,
            ':password' => $password,
            ':dob'      => $dob,
            ':semester' => $semester,
            ':branch'   => $branch
        ]);
        $message = "<p class='success-message'>✅ Student added successfully!</p>";
    } catch (PDOException $e) {
        $message = "<p class='error-message'>❌ Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add Student</title>
</head>
<body>
<a href="admin-panel.php">Back to Dashboard</a>
<h2>Manually Add a Student</h2>
<?php if (!empty($message)) echo $message; ?>
<form method="POST">
    <input type="text" name="usn" placeholder="USN" required><br>
    <input type="text" name="name" placeholder="Full Name" required><br>
    <input type="email" name="email" placeholder="Email" required><br>
    <input type="date" name="dob" placeholder="Date of Birth"><br>
    <textarea name="address" placeholder="Address"></textarea><br>

    <label>Branch:</label>
    <select name="branch" required>
        <option value="CSE">CSE</option>
        <option value="ECE">ECE</option>
        <option value="MECH">MECH</option>
        <option value="CIVIL">CIVIL</option>
    </select><br>

    <label>Semester:</label>
    <select name="semester" required>
        <?php
        $semesters = $pdo->query("SELECT id, name FROM semesters ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($semesters as $sem) {
            echo "<option value='{$sem['id']}'>{$sem['name']}</option>";
        }
        ?>
    </select><br>

    <input type="password" name="password" placeholder="Password" required><br>
    <button type="submit">Add Student</button>
</form>
</body>
</html>