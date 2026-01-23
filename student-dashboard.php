<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: student-login.php");
    exit;
}

$student_id = $_SESSION['student_id'];

/* STUDENT INFO */
$stuStmt = $pdo->prepare("
    SELECT student_name, usn, branch
    FROM students
    WHERE id = ?
");
$stuStmt->execute([$student_id]);
$student = $stuStmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die("Student not found");
}

/* ATTENDANCE */
$attStmt = $pdo->prepare("
    SELECT sub.name,
           ROUND(
             (SUM(CASE WHEN a.status='present' THEN 1 ELSE 0 END)::decimal
             / COUNT(*)) * 100, 2
           ) AS percentage
    FROM attendance a
    JOIN subjects sub ON sub.id = a.subject_id
    WHERE a.student_id = ?
    GROUP BY sub.name
");
$attStmt->execute([$student_id]);
$attendance = $attStmt->fetchAll(PDO::FETCH_ASSOC);

/* RESULTS */
$resStmt = $pdo->prepare("
    SELECT s.name AS subject, r.marks, r.max_marks
    FROM ia_results r
    JOIN question_papers qp ON qp.id = r.qp_id
    JOIN subjects s ON s.id = qp.subject_id
    WHERE r.student_id = ?
    ORDER BY r.created_at DESC
");
$resStmt->execute([$student_id]);
$results = $resStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Student Dashboard</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
/* ===== BASE ===== */
body {
    margin: 0;
    font-family: 'Segoe UI', sans-serif;
    background: #f2f5f9;
}

/* ===== HEADER ===== */
.header {
    background: #0b3c6f;
    color: #fff;
    padding: 12px 16px;
    font-size: 14px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
}

.header span {
    font-weight: 500;
}

.logout {
    color: #fff;
    text-decoration: none;
    font-weight: 600;
}

/* ===== TABS ===== */
.tabs {
    display: flex;
    justify-content: center;
    gap: 8px;
    padding: 10px;
    background: #fff;
}

.tabs button {
    border: none;
    padding: 8px 14px;
    border-radius: 6px;
    background: #e6eef7;
    color: #0b3c6f;
    font-weight: 600;
    cursor: pointer;
}

.tabs button.active {
    background: #0b3c6f;
    color: #fff;
}

/* ===== SECTIONS ===== */
.section {
    display: none;
    padding: 12px;
}

.section.active {
    display: block;
}

/* ===== CARD ===== */
.card {
    background: #fff;
    border-radius: 10px;
    padding: 14px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    margin-bottom: 15px;
}

/* ===== CHART FIX ===== */
.chart-wrapper {
    width: 100%;
    max-width: 900px;
    height: 860px;
    margin: auto;
}

@media (max-width: 600px) {
    .chart-wrapper {
        max-width: 320px;
        height: 220px;
    }
}

/* ===== TABLE ===== */
table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    padding: 10px;
    border: 1px solid #ccc;
    text-align: center;
}

th {
    background: #f4f6f8;
}

/* ===== MOBILE ===== */
@media (max-width: 600px) {
    table {
        font-size: 14px;
    }
}
</style>
</head>

<body>

<!-- HEADER -->
<div class="header">
    <span>
        <?= htmlspecialchars($student['student_name'] ?? '') ?> |
        USN: <?= htmlspecialchars($student['usn'] ?? '') ?> |
        Branch: <?= htmlspecialchars($student['branch'] ?? '') ?>
    </span>
    <a href="student-logout.php" class="logout">Logout</a>
</div>

<!-- TABS -->
<div class="tabs">
    <button class="active" onclick="showTab('attendance')">Attendance</button>
    <button onclick="showTab('results')">Results</button>
    <button onclick="showTab('assignments')">Assignments</button>
</div>

<!-- ATTENDANCE -->
<div id="attendance" class="section active">
    <div class="card">
        <h4>Attendance Overview</h4>
        <div class="chart-wrapper">
            <canvas id="attChart"></canvas>
        </div>
    </div>
</div>

<!-- RESULTS -->
<div id="results" class="section">
    <div class="card">
        <h4>IA Results</h4>
        <table>
            <tr>
                <th>Subject</th>
                <th>Marks</th>
                <th>Max</th>
            </tr>
            <?php foreach ($results as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['subject']) ?></td>
                <td><?= $r['marks'] ?></td>
                <td><?= $r['max_marks'] ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>

<!-- ASSIGNMENTS -->
<div id="assignments" class="section">
    <div class="card">
        <h4>Assignments</h4>
        <p>No assignments published yet.</p>
    </div>
</div>

<script>
/* TAB SWITCH */
function showTab(id) {
    document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.tabs button').forEach(b => b.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    event.target.classList.add('active');
}

/* CHART */
new Chart(document.getElementById('attChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($attendance, 'name')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($attendance, 'percentage')) ?>,
            backgroundColor: '#4caf50',
            borderRadius: 6,
            barThickness: 28
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                ticks: { stepSize: 20 }
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
