<?php
session_start();
require_once 'db.php';

/* ---------- LOGIN CHECK (DO NOT CHANGE) ---------- */
if (!isset($_SESSION['student_id'])) {
    header("Location: student-login.php");
    exit;
}

$usn = $_SESSION['student_id'];

/* ---------- FETCH STUDENT DETAILS ---------- */
$studentStmt = $pdo->prepare("
    SELECT id, student_name, usn, branch
    FROM students
    WHERE usn = ?
");
$studentStmt->execute([$usn]);
$student = $studentStmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die("Student not found");
}

$studentId = $student['id'];

/* ---------- FETCH ATTENDANCE ---------- */
$attendanceStmt = $pdo->prepare("
    SELECT 
        sub.name AS subject_name,
        COUNT(a.id) AS total_classes,
        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS attended_classes
    FROM attendance a
    JOIN subjects sub ON sub.id = a.subject_id
    WHERE a.student_id = ?
    GROUP BY sub.name
    ORDER BY sub.name
");
$attendanceStmt->execute([$studentId]);

$subjects = [];
$percentages = [];

while ($row = $attendanceStmt->fetch(PDO::FETCH_ASSOC)) {
    $subjects[] = $row['subject_name'];
    $percentages[] = ($row['total_classes'] > 0)
        ? round(($row['attended_classes'] / $row['total_classes']) * 100)
        : 0;
}

/* ---------- FETCH PRINCIPAL NOTIFICATIONS ---------- */
$notificationStmt = $pdo->query("
    SELECT message, created_at
    FROM notifications
    ORDER BY created_at DESC
    LIMIT 5
");
$notifications = $notificationStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Student Dashboard</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
body {
    font-family: Arial, sans-serif;
    background: #f4f6f9;
    margin: 0;
}

.navbar {
    background: #003366;
    padding: 12px;
    display: flex;
    justify-content: flex-end;
    gap: 15px;
}

.navbar a {
    color: white;
    text-decoration: none;
    font-weight: bold;
}

.student-strip {
    background: #e3f2fd;
    padding: 12px 20px;
    font-size: 16px;
    border-bottom: 2px solid #003366;
}

.container {
    max-width: 1000px;
    margin: 30px auto;
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
}

h2 {
    text-align: center;
    color: #003366;
}

canvas {
    margin-top: 20px;
}

.description {
    text-align: center;
    font-size: 14px;
    color: #444;
    margin-top: 10px;
}

.notifications {
    margin-top: 30px;
    background: #fff8e1;
    padding: 15px;
    border-left: 5px solid #ff9800;
    border-radius: 5px;
}

.notifications h4 {
    margin-top: 0;
}

.notifications ul {
    padding-left: 18px;
}

.notifications li {
    margin-bottom: 8px;
    font-size: 14px;
}
</style>
</head>

<body>

<!-- NAV BAR -->
<div class="navbar">
    <a href="view-timetable.php">Timetable</a>
    <a href="ia-results.php">IA Results</a>
    <a href="stlogout.php">Logout</a>
</div>

<!-- STUDENT INFO STRIP (AFTER NAVBAR, LEFT SIDE) -->
<div class="student-strip">
    <strong>Name:</strong> <?= htmlspecialchars($student['student_name']) ?>
    &nbsp;&nbsp; | &nbsp;&nbsp;
    <strong>USN:</strong> <?= htmlspecialchars($student['usn']) ?>
    &nbsp;&nbsp; | &nbsp;&nbsp;
    <strong>Branch:</strong> <?= htmlspecialchars($student['branch']) ?>
</div>

<div class="container">

    <h2>Attendance Overview</h2>

    <canvas id="attendanceChart"></canvas>

    <div class="description">
        Subject-wise attendance percentage based on recorded classes.
    </div>

    <!-- NOTIFICATIONS -->
    <div class="notifications">
        <h4>📢 Notifications from Principal</h4>
        <?php if (count($notifications) === 0): ?>
            <p>No notifications available.</p>
        <?php else: ?>
            <ul>
                <?php foreach ($notifications as $note): ?>
                    <li>
                        <strong><?= date("d-m-Y", strtotime($note['created_at'])) ?>:</strong>
                        <?= htmlspecialchars($note['message']) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
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
            backgroundColor: '#1976d2'
        }]
    },
    options: {
        scales: {
            y: {
                beginAtZero: true,
                max: 100
            }
        },
        plugins: {
            legend: { display: false }
        }
    }
});
</script>

</body>
</html>
