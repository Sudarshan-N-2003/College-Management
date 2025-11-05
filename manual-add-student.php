<?php
session_start();
require_once('db.php'); // Use the PDO connection ($pdo)

// Restrict access to admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usn       = trim($_POST['usn']);
    $name      = trim($_POST['name']);
    $email     = trim($_POST['email']);
    $dob       = $_POST['dob'] ?? null;
    $address   = trim($_POST['address']);
    $branch    = trim($_POST['branch']);
    $semester  = (int)($_POST['semester'] ?? 1);
    $password  = password_hash($_POST['password'], PASSWORD_DEFAULT);

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Student Manually</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f7fc; margin: 0; padding: 0; }
        .navbar { background-color: #333; color: #fff; padding: 12px; text-align: right; }
        .navbar a { color: #fff; text-decoration: none; margin: 0 15px; font-size: 16px; }
        .content { max-width: 600px; width: 90%; margin: 40px auto; background-color: #fff;
                   padding: 30px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #333; }
        form { display: flex; flex-direction: column; gap: 10px; }
        input, textarea, select, button {
            padding: 10px; font-size: 16px; border: 1px solid #ccc; border-radius: 5px;
        }
        button { background-color: #28a745; color: white; border: none; cursor: pointer; }
        button:hover { background-color: #218838; }
        .error-message { color: #721c24; background: #f8d7da; padding: 10px; border-radius: 5px; }
        .success-message { color: #155724; background: #d4edda; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>

<div class="navbar">
    <a href="admin-panel.php">Back to Dashboard</a>
</div>

<div class="content">
    <h2>Manually Add a Student</h2>
    <?php if (!empty($message)) echo $message; ?>

    <form method="POST" action="">
        <input type="text" name="usn" placeholder="USN" required>
        <input type="text" name="name" placeholder="Full Name" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="date" name="dob" placeholder="Date of Birth">
        <textarea name="address" placeholder="Address"></textarea>

        <label for="branch">Branch:</label>
        <select name="branch" id="branch" required>
            <option value="CSE">Computer Science</option>
            <option value="ECE">Electronics</option>
            <option value="MECH">Mechanical</option>
            <option value="CIVIL">Civil</option>
        </select>

        <label for="semester">Semester:</label>
        <select name="semester" id="semester" required>
            <?php
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

</body>
</html>