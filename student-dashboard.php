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
$student = $stuStmt->fetch();

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
$attendance = $attStmt->fetchAll();

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
$results = $resStmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
<title>Student Dashboard</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: Arial, sans-serif;
    background-color: #eef2f7;
    color: #333;
    line-height: 1.6;
    font-size: 16px;
}

/* Header */
.header {
    background-color: #003366;
    color: #fff;
    padding: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
}

.header b {
    font-size: 1.2rem;
}

.logout {
    color: #fff;
    text-decoration: none;
    font-weight: bold;
    transition: color 0.3s ease;
}

.logout:hover {
    color: #ddd;
}

/* Tabs */
.tabs {
    background-color: #fff;
    padding: 10px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.tabs button {
    padding: 10px 20px;
    margin: 5px;
    border: none;
    background-color: #007bff;
    color: #fff;
    cursor: pointer;
    border-radius: 4px;
    font-size: 1rem;
    transition: background-color 0.3s ease;
}

.tabs button:hover {
    background-color: #0056b3;
}

/* Sections */
.section {
    display: none;
    padding: 20px;
    max-width: 1200px;
    margin: 0 auto;
}

.section.active {
    display: block;
}

/* Cards */
.card {
    background-color: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    margin-bottom: 20px;
}

/* Tables */
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

th, td {
    border: 1px solid #ccc;
    padding: 12px;
    text-align: center;
}

th {
    background-color: #f8f9fa;
    font-weight: bold;
}

/* Chart canvas */
#attChart {
    max-width: 100%;
    height: auto;
}

/* Responsive design */
@media (max-width: 768px) {
    .header {
        flex-direction: column;
        text-align: center;
        padding: 10px;
    }

    .header b {
        font-size: 1rem;
    }

    .tabs {
        padding: 5px;
    }

    .tabs button {
        display: block;
        width: 100%;
        margin: 5px 0;
        padding: 12px;
    }

    .section {
        padding: 10px;
    }

    .card {
        padding: 15px;
    }

    table {
        font-size: 0.9rem;
        overflow-x: auto;
        display: block;
        white-space: nowrap;
    }

    th, td {
        padding: 8px;
    }
}

@media (max-width: 480px) {
    body {
        font-size: 14px;
    }

    .header {
        padding: 8px;
    }

    .section {
        padding: 5px;
    }

    .card {
        padding: 10px;
    }

    th, td {
        padding: 6px;
    }
}
</style>
</head>
<body>

<div class="header">
<b><?= htmlspecialchars($student['student_name'] ?? '') ?></b> |
USN: <?= htmlspecialchars($student['usn'] ?? '') ?> |
Branch: <?= htmlspecialchars($student['branch'] ?? '') ?>
<a href="student-logout.php" class="logout">Logout</a>
</div>

<div class="tabs">
<button onclick="showTab('attendance')">Attendance</button>
<button onclick="showTab('results')">Results</button>
<button onclick="showTab('assignments')">Assignments</button>
</div>

<!-- ATTENDANCE -->
<div id="attendance" class="section active">
<div class="card">
<canvas id="attChart" height="120"></canvas>
</div>
</div>

<!-- RESULTS -->
<div id="results" class="section">
<div class="card">
<table>
<tr><th>Subject</th><th>Marks</th><th>Max</th></tr>
<?php foreach($results as $r): ?>
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
<p>No assignments published yet.</p>
</div>
</div>

<script>
new Chart(document.getElementById('attChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($attendance,'name')) ?>,
        datasets: [{
            label: 'Attendance %',
            data: <?= json_encode(array_column($attendance,'percentage')) ?>,
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
                ticks: {
                    stepSize: 20
                }
            }
        },
        plugins: {
            legend: {
                display: false
            }
        }
    }
});
</script>


</body>
</html>


