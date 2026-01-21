<?php
session_start();
require_once __DIR__ . '/db.php';

/* -------------------------------
   AUTH CHECK
--------------------------------*/
if (!isset($_SESSION['student_id'])) {
    header("Location: student-login.php");
    exit;
}

$student_id = $_SESSION['student_id'];

/* -------------------------------
   FETCH STUDENT DETAILS
   (MATCHES YOUR NEON TABLE)
--------------------------------*/
$studentStmt = $pdo->prepare("
    SELECT 
        usn,
        student_name,
        branch,
        email
    FROM students
    WHERE id = ?
");
$studentStmt->execute([$student_id]);
$student = $studentStmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die("Student not found");
}

/* -------------------------------
   FETCH ATTENDANCE (SUBJECT-WISE)
--------------------------------*/
$attendanceStmt = $pdo->prepare("
    SELECT 
        sub.name AS subject_name,
        COUNT(a.id) AS total_classes,
        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS attended
    FROM attendance a
    JOIN subjects sub ON sub.id = a.subject_id
    WHERE a.student_id = ?
    GROUP BY sub.name
    ORDER BY sub.name
");
$attendanceStmt->execute([$student_id]);
$attendanceRows = $attendanceStmt->fetchAll(PDO::FETCH_ASSOC);

$subjects = [];
$percentages = [];

foreach ($attendanceRows as $row) {
    $subjects[] = $row['subject_name'];
    $percentages[] = $row['total_classes'] > 0
        ? round(($row['attended'] / $row['total_classes']) * 100)
        : 0;
}

/* -------------------------------
   FETCH PRINCIPAL NOTIFICATIONS
--------------------------------*/
$notifyStmt = $pdo->query("
    SELECT message, created_at
    FROM notifications
    ORDER BY created_at DESC
    LIMIT 5
");
$notifications = $notifyStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Student Dashboard</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f4f6f9;
}

/* NAVBAR */
.navbar {
    background: #003366;
    padding: 12px;
    display: flex;
    justify-content: flex-end;
    gap: 15px;
}
.navbar a {
    color: #fff;
    text-decoration: none;
    font-weight: bold;
}
.navbar a:hover {
    text-decoration: underline;
}

/* STUDENT INFO BAR */
.student-bar {
    background: #e3f2fd;
    padding: 10px 20px;
    font-size: 15px;
}

/* MAIN CARD */
.container {
    max-width: 1000px;
    margin: 25px auto;
    background: #fff;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
}

h2 {
    text-align: center;
    color: #003366;
}

/* GRAPH */
canvas {
    max-width: 100%;
    margin-top: 20px;
}

/* NOTIFICATIONS */
.notifications {
    margin-top: 30px;
    padding: 15px;
    background: #fff3cd;
    border-left: 5px solid #ffc107;
    border-radius: 6px;
}
.notifications h4 {
    margin-top: 0;
}
.notifications ul {
    margin: 0;
    padding-left: 18px;
}
.notifications li {
    margin-bottom: 6px;
}

/* FOOTER BUTTONS */
.actions {
    margin-top: 25px;
    text-align: center;
}
.actions a {
    display: inline-block;
    padding: 10px 18px;
    margin: 5px;
    background: #003366;
    color: white;
    text-decoration: none;
    border-radius: 5px;
}
.actions a.logout {
    background: #c62828;
}
</style>
</head>

<body>

<!-- NAV -->
<div class="navbar">
    <a href="view-timetable.php">Timetable</a>
    <a href="ia-results.php">IA Results</a>
    <a href="stlogout.php">Logout</a>
</div>

<!-- STUDENT INFO -->
<div class="student-bar">
    <strong>Name:</strong> <?= htmlspecialchars($student['student_name']) ?> |
    <strong>USN:</strong> <?= htmlspecialchars($student['usn']) ?> |
    <strong>Branch:</strong> <?= htmlspecialchars($student['branch']) ?>
</div>

<div class="container">
    <h2>Attendance Overview</h2>

    <canvas id="attendanceChart"></canvas>

    <div class="notifications">
        <h4>📢 Principal Notifications</h4>
        <ul>
            <?php if (empty($notifications)): ?>
                <li>No notifications available</li>
            <?php else: ?>
                <?php foreach ($notifications as $n): ?>
                    <li>
                        <strong><?= date("d-m-Y", strtotime($n['created_at'])) ?>:</strong>
                        <?= htmlspecialchars($n['message']) ?>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>

    <div class="actions">
        <a href="ia-results.php">View IA Results</a>
        <a href="stlogout.php" class="logout">Logout</a>
    </div>
</div>

<script>
const ctx = document.getElementById('attendanceChart').getContext('2d');

new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($subjects) ?>,
        datasets: [{
            label: 'Attendance (%)',
            data: <?= json_encode($percentages) ?>,
            backgroundColor: 'rgba(54, 162, 235, 0.7)'
        }]
    },
    options: {
        scales: {
            y: {
                beginAtZero: true,
                max: 100
            }
        }
    }
});
</script>

</body>
</html>
