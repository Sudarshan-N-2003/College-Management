<?php
session_start();
include('db-config.php'); // Your PDO connection file

// --- Check if admin is logged in ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// --- Check if ID provided ---
if (!isset($_GET['id'])) {
    header("Location: view-students.php");
    exit;
}

$student_id = (int)$_GET['id'];
$error = '';
$student = null;

try {
    // Ensure no aborted transaction from before
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    // --- Handle Update ---
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $usn = trim($_POST['usn']);
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);

        // Begin safe transaction
        $conn->beginTransaction();

        $update_sql = "UPDATE students SET usn = ?, name = ?, email = ? WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->execute([$usn, $name, $email, $student_id]);

        $conn->commit();

        header("Location: view-students.php?status=updated");
        exit;
    }

    // --- Fetch student details for form ---
    $stmt = $conn->prepare("SELECT id, usn, name, email FROM students WHERE id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        header("Location: view-students.php");
        exit;
    }

} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $error = "Database Error: " . htmlspecialchars($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Student</title>
    <style>
        body {
            font-family: "Segoe UI", Arial, sans-serif;
            background: linear-gradient(135deg, #e3f2fd, #ffffff);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            background-color: #fff;
            padding: 35px;
            border-radius: 12px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.1);
            width: 90%;
            max-width: 480px;
        }
        h2 {
            color: #333;
            text-align: center;
            margin-bottom: 25px;
        }
        label {
            display: block;
            margin-top: 12px;
            font-weight: bold;
            color: #555;
        }
        input {
            width: 100%;
            padding: 10px;
            margin-top: 6px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 15px;
            box-sizing: border-box;
        }
        button {
            margin-top: 22px;
            padding: 12px;
            width: 100%;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }
        button:hover {
            background: #0056b3;
        }
        .error {
            color: #b71c1c;
            background: #ffebee;
            border: 1px solid #ef9a9a;
            padding: 10px;
            border-radius: 6px;
            text-align: center;
            margin-bottom: 10px;
        }
        .back-link {
            display: block;
            margin-top: 15px;
            text-align: center;
            color: #007bff;
            text-decoration: none;
            font-size: 14px;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Edit Student Details</h2>

    <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>

    <?php if ($student): ?>
        <form action="edit-student.php?id=<?= htmlspecialchars($student_id) ?>" method="POST">
            <label for="usn">USN:</label>
            <input type="text" id="usn" name="usn" value="<?= htmlspecialchars($student['usn']) ?>" required>

            <label for="name">Full Name:</label>
            <input type="text" id="name" name="name" value="<?= htmlspecialchars($student['name']) ?>" required>

            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($student['email']) ?>" required>

            <button type="submit">Update Details</button>
        </form>
    <?php else: ?>
        <p>Could not find student details.</p>
    <?php endif; ?>

    <a href="view-students.php" class="back-link">← Back to View Students</a>
</div>
</body>
</html>