<?php
session_start();
require_once __DIR__ . '/db.php';

$error = "";

// Handle POST request
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = "Email and password are required.";
    } else {
        try {
            // Fetch student by email
            $stmt = $conn->prepare(
                "SELECT id, email, password 
                 FROM students 
                 WHERE email = :email"
            );

            $stmt->execute(['email' => $email]);
            $student = $stmt->fetch();

            if ($student && password_verify($password, $student['password'])) {

                session_regenerate_id(true);

                $_SESSION['student_id'] = $student['id'];
                $_SESSION['student_email'] = $student['email'];
                $_SESSION['role'] = 'student';

                header("Location: student-dashboard.php");
                exit;

            } else {
                $error = "Invalid email or password.";
            }

        } catch (PDOException $e) {
            error_log("Student Login Error: " . $e->getMessage());
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
        * { box-sizing: border-box; font-family: Arial, sans-serif; }
        body {
            margin: 0;
            min-height: 100vh;
            background: #000;
        }
        .video-background {
            position: fixed;
            inset: 0;
            z-index: -1;
        }
        #bg-video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .login-form {
            background: rgba(0,0,0,0.75);
            padding: 30px;
            width: 100%;
            max-width: 400px;
            border-radius: 10px;
            color: #fff;
        }
        h2 { text-align: center; margin-bottom: 20px; }
        label { display: block; margin-top: 15px; }
        input {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            background: #222;
            border: 1px solid #555;
            color: #fff;
            border-radius: 5px;
        }
        button {
            width: 100%;
            margin-top: 20px;
            padding: 12px;
            background: #3498db;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
        }
        button:hover { background: #2980b9; }
        .error-message {
            margin-top: 15px;
            padding: 10px;
            background: rgba(255,0,0,0.15);
            border: 1px solid red;
            border-radius: 5px;
            color: #ff6b6b;
            text-align: center;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #ccc;
            text-decoration: none;
        }
    </style>
</head>
<body>

<div class="video-background">
    <video autoplay muted loop playsinline id="bg-video">
        <source src="../video/back.mp4" type="video/mp4">
    </video>
</div>

<div class="login-container">
    <div class="login-form">
        <h2>Student Login</h2>

        <?php if ($error): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <label>Email</label>
            <input type="email" name="email" required>

            <label>Password</label>
            <input type="password" name="password" required>

            <button type="submit">Login</button>
        </form>

        <a href="../index.php" class="back-link">Back to Home</a>
    </div>
</div>

</body>
</html>
