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
body{font-family:Arial;background:#f4f6fa}
.login{width:350px;margin:100px auto;padding:25px;background:#fff;border-radius:8px}
input,button{width:100%;padding:10px;margin:8px 0}
button{background:#003366;color:#fff;border:none}
.error{color:red;text-align:center}
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
