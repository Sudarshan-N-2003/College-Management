<?php
session_start();

if (!isset($_SESSION['student_id'])) {
    header("Location: student-login.php");
    exit;
}

require_once 'db.php'; // ✅ USE THIS (PDO connection)

/* ---------------- STUDENT INFO ---------------- */
$student_id = $_SESSION['student_id'];

$stmt = $pdo->prepare("
    SELECT student_name, usn, branch
    FROM students
    WHERE id = :id
");
$stmt->execute(['id' => $student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die("Student not found");
}

$name   = $student['student_name'] ?? 'Student';
$usn    = $student['usn'] ?? 'N/A';
$branch = $student['branch'] ?? 'Not Assigned';

/* ---------------- ATTENDANCE ---------------- */
$attStmt = $pdo->prepare("
    SELECT s.name AS subject, a.total_classes, a.attended_classes
    FROM attendance a
    JOIN subjects s ON s.id = a.subject_id
    WHERE a.student_id = :sid
");
$attStmt->execute(['sid' => $student_id]);

$subjects = [];
$percentages = [];

while ($row = $attStmt->fetch(PDO::FETCH_ASSOC)) {
    $subjects[] = $row['subject'];
    $percentages[] = ($row['total_classes'] > 0)
        ? round(($row['attended_classes'] / $row['total_classes']) * 100)
        : 0;
}

/* ---------------- RESULTS ---------------- */
$resStmt = $pdo->prepare("
    SELECT subject, marks
    FROM ia_results
    WHERE student_id = :sid
");
$resStmt->execute(['sid' => $student_id]);
$results = $resStmt->fetchAll(PDO::FETCH_ASSOC);

/* ---------------- ASSIGNMENTS ---------------- */
$assignments = [];
try {
    $asgStmt = $pdo->prepare("
        SELECT title, due_date
        FROM assignments
        WHERE branch = :branch
        ORDER BY due_date
    ");
    $asgStmt->execute(['branch' => $branch]);
    $assignments = $asgStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // table may not exist — ignore safely
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Student Dashboard</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
body{margin:0;font-family:Arial;background:#f4f6f9}
.header{background:#003366;color:#fff;text-align:center;padding:15px}
.student-info{background:#e9f2ff;padding:10px;font-weight:bold}
.navbar{display:flex;justify-content:center;background:#0056b3}
.navbar button{border:none;background:none;color:#fff;padding:14px 25px;font-size:16px;cursor:pointer}
.navbar button.active{background:#003366}
.container{max-width:1100px;margin:auto;padding:20px}
.window{display:none}
.window.active{display:block}
.chart-box{max-width:600px;height:320px;margin:auto}
table{width:100%;border-collapse:collapse;background:#fff}
th,td{border:1px solid #ccc;padding:10px;text-align:center}
th{background:#003366;color:#fff}
.assignment{background:#fff;padding:15px;border-left:5px solid #007bff;margin-bottom:10px}
</style>
</head>

<body>

<div class="header">
    <h2>Vijaya Vittala Institute of Technology</h2>
    <p>Student Dashboard</p>
</div>

<div class="student-info">
    Name: <?= htmlspecialchars($name) ?> |
    USN: <?= htmlspecialchars($usn) ?> |
    Branch: <?= htmlspecialchars($branch) ?>
</div>

<div class="navbar">
    <button class="tab active" onclick="showTab('attendance')">Attendance</button>
    <button class="tab" onclick="showTab('results')">Results</button>
    <button class="tab" onclick="showTab('assignments')">Assignments</button>
</div>

<div class="container">

<!-- Attendance -->
<div id="attendance" class="window active">
    <h3>Attendance Overview</h3>
    <div class="chart-box">
        <canvas id="attChart"></canvas>
    </div>
</div>

<!-- Results -->
<div id="results" class="window">
    <h3>IA Results</h3>
    <table>
        <tr><th>Subject</th><th>Marks</th></tr>
        <?php if ($results): foreach ($results as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['subject']) ?></td>
                <td><?= htmlspecialchars($r['marks']) ?></td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="2">No results</td></tr>
        <?php endif; ?>
    </table>
</div>

<!-- Assignments -->
<div id="assignments" class="window">
    <h3>Assignments</h3>
    <?php if ($assignments): foreach ($assignments as $a): ?>
        <div class="assignment">
            <strong><?= htmlspecialchars($a['title']) ?></strong><br>
            Due: <?= htmlspecialchars($a['due_date']) ?>
        </div>
    <?php endforeach; else: ?>
        <p>No assignments available</p>
    <?php endif; ?>
</div>

</div>

<script>
function showTab(id){
    document.querySelectorAll('.window').forEach(w=>w.classList.remove('active'));
    document.querySelectorAll('.tab').forEach(b=>b.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    event.target.classList.add('active');
}

new Chart(document.getElementById('attChart'),{
    type:'bar',
    data:{
        labels:<?= json_encode($subjects) ?>,
        datasets:[{
            data:<?= json_encode($percentages) ?>,
            backgroundColor:'#3498db',
            barThickness:40
        }]
    },
    options:{
        maintainAspectRatio:false,
        scales:{y:{beginAtZero:true,max:100}},
        plugins:{legend:{display:false}}
    }
});
</script>

</body>
</html>
