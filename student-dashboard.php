<?php
session_start();
require 'db-config.php'; // MUST create $pdo (PDO connection)

// 🔐 Login check (DO NOT CHANGE)
if (!isset($_SESSION['student_id'])) {
    header("Location: student-login.php");
    exit;
}

// ✅ SESSION STORES USN
$student_usn = $_SESSION['student_id'];

/* ============================
   FETCH STUDENT DETAILS
   ============================ */
$studentStmt = $pdo->prepare("
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
    WHERE usn = :usn
");
$studentStmt->execute(['usn' => $student_usn]);
$student = $studentStmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die("Student not found");
}

/* ============================
   FETCH ATTENDANCE DATA
   ============================ */
$attendanceStmt = $pdo->prepare("
    SELECT 
        sub.name AS subject,
        a.total_classes,
        a.attended_classes
    FROM attendance a
    JOIN subjects sub ON sub.id = a.subject_id
    WHERE a.student_id = (
        SELECT id FROM students WHERE usn = :usn
    )
");
$attendanceStmt->execute(['usn' => $student_usn]);
$attendanceRows = $attendanceStmt->fetchAll(PDO::FETCH_ASSOC);

/* Prepare chart data */
$subjects = [];
$percentages = [];

foreach ($attendanceRows as $row) {
    $subjects[] = $row['subject'];
    $percentages[] = ($row['total_classes'] > 0)
        ? round(($row['attended_classes'] / $row['total_classes']) * 100)
        : 0;
}

/* ============================
   FETCH PRINCIPAL NOTIFICATIONS
   ============================ */
$notifyStmt = $pdo->query("
    SELECT message, created_at
    FROM notifications
    ORDER BY created_at DESC
    LIMIT 5
");
$notifications = $notifyStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { margin:0; font-family: Arial; background:#f4f6f9; }
        .navbar {
            background:#003366;
            padding:12px;
            display:flex;
            justify-content:flex-end;
            gap:15px;
        }
        .navbar a {
            color:#fff;
            text-decoration:none;
            padding:6px 12px;
            border-radius:4px;
        }
        .navbar a:hover { background:#0056b3; }

        .student-strip {
            background:#e3f2fd;
            padding:10px 20px;
            font-weight:bold;
            color:#003366;
        }

        .container {
            width:70%;
            margin:20px auto;
            background:#fff;
            padding:20px;
            border-radius:8px;
            box-shadow:0 0 10px rgba(0,0,0,0.1);
        }

        h2 { text-align:center; }

        .notifications {
            margin-top:30px;
            background:#fff3cd;
            padding:15px;
            border-radius:6px;
            border-left:5px solid #ffc107;
        }

        canvas { margin-top:20px; }
    </style>
</head>
<body>

<!-- NAVBAR -->
<div class="navbar">
    <a href="view-timetable.php">Timetable</a>
    <a href="ia-results.php">IA Results</a>
    <a href="stlogout.php">Logout</a>
</div>

<!-- STUDENT INFO (AFTER NAVBAR, LEFT SIDE) -->
<div class="student-strip">
    Name: <?= htmlspecialchars($student['student_name']) ?> |
    USN: <?= htmlspecialchars($student['usn']) ?> |
    Branch: <?= htmlspecialchars($student['branch']) ?>
</div>

<div class="container">
    <h2>Attendance Overview</h2>
    <canvas id="attendanceChart"></canvas>

    <p style="text-align:center; color:#555;">
        Subject-wise attendance percentage
    </p>

    <!-- NOTIFICATIONS -->
    <div class="notifications">
        <strong>📢 Principal Notifications</strong>
        <ul>
            <?php if ($notifications): ?>
                <?php foreach ($notifications as $n): ?>
                    <li>
                        <?= htmlspecialchars($n['message']) ?>
                        <small>(<?= date("d-m-Y", strtotime($n['created_at'])) ?>)</small>
                    </li>
                <?php endforeach; ?>
            <?php else: ?>
                <li>No notifications</li>
            <?php endif; ?>
        </ul>
    </div>
</div>

<script>
const ctx = document.getElementById('attendanceChart').getContext('2d');
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
            y: {
                beginAtZero:true,
                max:100
            }
        }
    }
});
</script>

</body>
</html>
