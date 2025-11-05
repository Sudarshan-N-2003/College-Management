<?php
session_start();
include('db-config.php'); // PDO connection ($conn)

// Restrict access to admin only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Ensure valid student ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: view-students.php");
    exit;
}

$student_id = (int)$_GET['id'];
$error = '';
$student = null;

try {
    // --- Handle update form submission ---
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $usn = trim($_POST['usn']);
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $semester = (int)$_POST['semester'];

        // Basic validation
        if ($usn === '' || $name === '' || $email === '') {
            $error = "All fields are required.";
        } else {
            $update_sql = "UPDATE students SET usn = ?, student_name = ?, email = ?, semester = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);

            if ($update_stmt->execute([$usn, $name, $email, $semester, $student_id])) {
                header("Location: view-students.php?status=updated");
                exit;
            } else {
                $error = "Error updating student details. Please try again.";
            }
        }
    }

    // --- Fetch existing student details ---
    $stmt = $conn->prepare("SELECT id, usn, student_name AS name, email, semester FROM students WHERE id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        header("Location: view-students.php?error=notfound");
        exit;
    }

} catch (PDOException $e) {
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
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: #eef2f7;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            background: #fff;
            padding: 30px 40px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            width: 90%;
            max-width: 480px;
        }
        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 25px;
        }
        form {
            display: flex;
            flex-direction: column;
        }
        label {
            font-weight: bold;
            margin-top: 12px;
            color: #444;
        }
        input, select {
            margin-top: 6px;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 15px;
        }
        button {
            margin-top: 20px;
            padding: 12px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        button:hover {
            background-color: #218838;
        }
        .error {
            background-color: #f8d7da;
            color: #842029;
            border: 1px solid #f5c2c7;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 10px;
            text-align: center;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Edit Student Details</h2>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($student): ?>
            <form method="POST" action="edit-student.php?id=<?= $student_id ?>">
                <label for="usn">USN:</label>
                <input type="text" id="usn" name="usn" value="<?= htmlspecialchars($student['usn']) ?>" required>

                <label for="name">Full Name:</label>
                <input type="text" id="name" name="name" value="<?= htmlspecialchars($student['name']) ?>" required>

                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($student['email']) ?>" required>

                <label for="semester">Semester:</label>
                <select name="semester" id="semester" required>
                    <option value="">Select Semester</option>
                    <?php for ($i = 1; $i <= 8; $i++): ?>
                        <option value="<?= $i ?>" <?= ($student['semester'] == $i) ? 'selected' : '' ?>>Semester <?= $i ?></option>
                    <?php endfor; ?>
                </select>

                <button type="submit">Update Student</button>
            </form>
        <?php else: ?>
            <p>Student record not found.</p>
        <?php endif; ?>

        <a href="view-students.php" class="back-link">← Back to View Students</a>
    </div>
</body>
</html>