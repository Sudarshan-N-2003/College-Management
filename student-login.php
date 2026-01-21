<?php
session_start();
require_once 'db.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if ($email && $password) {
        $stmt = $pdo->prepare(
            "SELECT id, password FROM students WHERE email = ?"
        );
        $stmt->execute([$email]);
        $student = $stmt->fetch();

        if ($student && password_verify($password, $student['password'])) {
            session_regenerate_id(true);
            $_SESSION['student_id'] = $student['id'];
            header("Location: student-dashboard.php");
            exit;
        } else {
            $error = "Invalid email or password";
        }
    } else {
        $error = "All fields are required";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Student Login</title>
<style>
/* Reset and base styles */
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: Arial, sans-serif;
    background-color: #f4f6fa;
    color: #333;
    line-height: 1.6;
    font-size: 16px;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
}

/* Login container */
.login {
    width: 350px;
    padding: 25px;
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    text-align: center;
}

.login h2 {
    margin-bottom: 20px;
    font-size: 1.5rem;
    color: #003366;
}

/* Form elements */
input, button {
    width: 100%;
    padding: 10px;
    margin: 8px 0;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 1rem;
}

input:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 5px rgba(0, 123, 255, 0.5);
}

button {
    background-color: #003366;
    color: #fff;
    border: none;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

button:hover {
    background-color: #002244;
}

/* Error message */
.error {
    color: red;
    text-align: center;
    margin-bottom: 15px;
    font-weight: bold;
}

/* Responsive design */
@media (max-width: 480px) {
    .login {
        width: 90%;
        max-width: 350px;
        padding: 20px;
        margin: 20px auto;
    }

    .login h2 {
        font-size: 1.3rem;
    }

    input, button {
        padding: 12px;
        font-size: 1rem;
    }
}
</style>
</head>
<body>
<div class="login">
<h2>Student Login</h2>
<?php if($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post">
<input type="email" name="email" placeholder="Email" required>
<input type="password" name="password" placeholder="Password" required>
<button type="submit">Login</button>
</form>
</div>
</body>
</html>

