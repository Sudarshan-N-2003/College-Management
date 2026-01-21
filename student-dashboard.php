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
body{margin:0;font-family:Arial;background:#eef2f7}
.header{background:#003366;color:#fff;padding:15px}
.tabs{background:#fff;padding:10px;text-align:center}
.tabs button{padding:10px 20px;margin:5px;border:none;background:#007bff;color:#fff}
.section{display:none;padding:20px}
.section.active{display:block}
.card{background:#fff;padding:20px;border-radius:8px}
table{width:100%;border-collapse:collapse}
th,td{border:1px solid #ccc;padding:8px;text-align:center}
.logout{float:right;color:#fff;text-decoration:none}
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
function showTab(id){
document.querySelectorAll('.section').forEach(s=>s.classList.remove('active'));
document.getElementById(id).classList.add('active');
}

new Chart(document.getElementById('attChart'),{
type:'bar',
data:{
labels: <?= json_encode(array_column($attendance,'name')) ?>,
datasets:[{
label:'Attendance %',
data: <?= json_encode(array_column($attendance,'percentage')) ?>,
backgroundColor:'#4caf50'
}]
},
options:{
responsive:true,
scales:{y:{beginAtZero:true,max:100}}
}
});
</script>

</body>
</html>
