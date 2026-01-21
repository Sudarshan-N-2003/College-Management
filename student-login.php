<?php
session_start();
require_once __DIR__ . '/db.php';

$error = "";

/* -------------------------------
   HANDLE LOGIN
--------------------------------*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = "Email and password are required.";
    } else {
        try {
            // IMPORTANT: student_name exists, NOT `name`
            $stmt = $pdo->prepare("
                SELECT id, email, password
                FROM students
                WHERE email = ?
            ");
            $stmt->execute([$email]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($student && password_verify($password, $student['password'])) {

                session_regenerate_id(true);

                $_SESSION['student_id'] = $student['id'];
                $_SESSION['student_email'] = $student['email'];

                header("Location: student-dashboard.php");
                exit;

            } else {
                $error = "Invalid email or password.";
            }

        } catch (PDOException $e) {
            error_log("Student login error: " . $e->getMessage());
            $error = "System error. Please try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Student Login</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #0f2027;
    background: linear-gradient(to right, #2c5364, #203a43, #0f2027);
    height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
}

.login-box {
    background: rgba(0,0,0,0.75);
    padding: 30px;
    border-radius: 10px;
    width: 100%;
    max-width: 380px;
    color: #fff;
    box-shadow: 0 0 20px rgba(0,0,0,0.5);
}

.login-box h2 {
    text-align: center;
    margin-bottom: 25px;
}

.form-group {
    margin-bottom: 18px;
}

label {
    display: block;
    margin-bottom: 6px;
    font-size: 14px;
}

input {
    width: 100%;
    padding: 12px;
    border-radius: 5px;
    border: none;
    font-size: 15px;
}

input:focus {
    outline: none;
    box-shadow: 0 0 5px #3498db;
}

button {
    width: 100%;
    padding: 12px;
    background: #3498db;
    border: none;
    border-radius: 5px;
    color: #fff;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
}

button:hover {
    background: #2980b9;
}

.error {
    background: #ff4d4d;
    padding: 10px;
    border-radius: 5px;
    margin-bottom: 15px;
    text-align: center;
}

.back-link {
    text-align: center;
    margin-top: 15px;
}

.back-link a {
    color: #ccc;
    text-decoration: none;
    font-size: 14px;
}
.back-link a:hover {
    color: #fff;
}
</style>
</head>

<body>

<div class="login-box">
    <h2>Student Login</h2>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" required>
        </div>

        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required>
        </div>

        <button type="submit">Login</button>
    </form>

    <div class="back-link">
        <a href="../index.php">⬅ Back to Home</a>
    </div>
</div>

</body>
</html>
