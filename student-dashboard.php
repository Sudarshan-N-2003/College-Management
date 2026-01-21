<?php
session_start();
require 'db-config.php'; // PDO connection ($conn)

// LOGIN CHECK (unchanged logic)
if (!isset($_SESSION['student_id'])) {
    header("Location: student-login.php");
    exit;
}

$student_id = $_SESSION['student_id'];


// ==========================
// FETCH STUDENT DETAILS
// ==========================
$studentStmt = $conn->prepare("
    SELECT 
        student_name,
        usn,
        email,
        dob,
        address,
        branch,
        semester,
        section
    FROM students
    WHERE id = $1
");
$studentStmt->execute([$student_id]);
$student = $studentStmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die("Student not found");
}


// ==========================
// FETCH ATTENDANCE
// ==========================
$attendanceStmt = $conn->prepare("
    SELECT 
        s.name AS subject_name,
        a.total_classes,
        a.attended_classes
    FROM attendance a
    JOIN subjects s ON s.id = a.subject_id
    WHERE a.student_id = $1
");
$attendanceStmt->execute([$student_id]);

$subjects = [];
$percentages = [];

while ($row = $attendanceStmt->fetch(PDO::FETCH_ASSOC)) {
    $subjects[] = $row['subject_name'];

    $percentages[] = ($row['total_classes'] > 0)
        ? round(($row['attended_classes'] / $row['total_classes']) * 100)
        : 0;
}


// ==========================
// FETCH PRINCIPAL NOTIFICATIONS
// ==========================
$noteStmt = $conn->query("
    SELECT message, created_at
    FROM notifications
    ORDER BY created_at DESC
    LIMIT 5
");
$notifications = $noteStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: Arial;
            margin: 0;
            background: #f4f6f9;
        }

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

        .student-strip {
            background: #e3f2fd;
            padding: 12px 20px;
            font-size: 15px;
            display: flex;
            justify-content: space-between;
        }

        .container {
            width: 75%;
            margin: 20px auto;
            background: white;
            padding: 25px;
            border-radius: 8px;
        }

        canvas {
            margin-top: 20px;
        }

        .note-box {
            margin-top: 30px;
            background: #fff8e1;
            padding: 15px;
            border-left: 5px solid #ffc107;
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<div class="navbar">
    <a href="view-timetable.php">Timetable</a>
    <a href="ia-results.php">IA Results</a>
    <a href="stlogout.php">Logout</a>
</div>

<!-- STUDENT INFO STRIP (AFTER NAVBAR, LEFT SIDE) -->
<div class="student-strip">
    <div>
        <strong>Name:</strong> <?= htmlspecialchars($student['student_name']) ?><br>
        <strong>USN:</strong> <?= htmlspecialchars($student['usn']) ?>
    </div>
    <div>
        <strong>Branch:</strong> <?= htmlspecialchars($student['branch']) ?><br>
        <strong>Semester:</strong> <?= htmlspecialchars($student['semester']) ?>
    </div>
</div>

<div class="container">
    <h2>Attendance Overview</h2>
    <canvas id="attendanceChart"></canvas>

    <p style="text-align:center; margin-top:10px;">
        Attendance percentage per subject
    </p>

    <!-- NOTIFICATIONS -->
    <div class="note-box">
        <strong>📢 Principal Notifications</strong>
        <ul>
            <?php foreach ($notifications as $n): ?>
                <li>
                    <?= htmlspecialchars($n['message']) ?>
                    <small>(<?= date("d-m-Y", strtotime($n['created_at'])) ?>)</small>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<script>
const ctx = document.getElementById('attendanceChart');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($subjects) ?>,
        datasets: [{
            label: 'Attendance %',
            data: <?= json_encode($percentages) ?>,
            backgroundColor: 'rgba(54,162,235,0.7)'
        }]
    },
    options: {
        scales: {
            y: { beginAtZero: true, max: 100 }
        }
    }
});
</script>

</body>
</html>
