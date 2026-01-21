<?php
session_start();

/* ================= DATABASE CONNECTION (NEON / RENDER) ================= */

$database_url = getenv("postgresql://neondb_owner:npg_STKDhH8lomb7@ep-steep-grass-a4zzp7i4-pooler.us-east-1.aws.neon.tech/neondb?sslmode=require&channel_binding=require");
if (!$database_url) {
    die("DATABASE_URL not set");
}

$db = parse_url($database_url);

try {
    $pdo = new PDO(
        "pgsql:host={$db['host']};port={$db['port']};dbname=" . ltrim($db['path'], '/') . ";sslmode=require",
        $db['user'],
        $db['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed");
}

/* ================= AUTH ================= */

if (!isset($_SESSION['student_id'])) {
    header("Location: student-login.php");
    exit;
}

$student_id = $_SESSION['student_id'];

/* ================= STUDENT INFO ================= */

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

/* ================= ATTENDANCE ================= */

$attStmt = $pdo->prepare("
    SELECT sub.name AS subject,
           COUNT(a.id) FILTER (WHERE a.status='present') * 100.0 / NULLIF(COUNT(a.id),0) AS percent
    FROM attendance a
    JOIN subjects sub ON sub.id = a.subject_id
    WHERE a.student_id = ?
    GROUP BY sub.name
");
$attStmt->execute([$student_id]);
$attendance = $attStmt->fetchAll();

/* ================= IA RESULTS ================= */

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

/* ================= ASSIGNMENTS ================= */

$asgStmt = $pdo->prepare("
    SELECT title, due_date 
    FROM assignments 
    WHERE branch = ?
    ORDER BY due_date
");
$asgStmt->execute([$student['branch'] ?? '']);
$assignments = $asgStmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Student Dashboard</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
body{margin:0;font-family:Arial;background:#f4f6fa}
.header{background:#003366;color:#fff;padding:15px}
.header span{font-weight:bold}
.tabs{display:flex;justify-content:center;background:#007bff}
.tabs button{border:none;background:none;color:#fff;padding:14px 25px;cursor:pointer}
.tabs button.active{background:#003366}
.section{display:none;padding:20px}
.section.active{display:block}
.card{background:#fff;padding:20px;border-radius:8px;max-width:900px;margin:auto}
canvas{max-width:600px!important;margin:auto}
table{width:100%;border-collapse:collapse}
th,td{border:1px solid #ccc;padding:10px;text-align:center}
.assignment{background:#e6f2ff;padding:10px;margin:10px 0;border-left:4px solid #007bff}
</style>
</head>

<body>

<div class="header">
    Name: <?= htmlspecialchars($student['student_name'] ?? '') ?>
    | USN: <?= htmlspecialchars($student['usn'] ?? '') ?>
    | Branch: <?= htmlspecialchars($student['branch'] ?? '') ?>
</div>

<div class="tabs">
    <button class="tab active" onclick="showTab('attendance')">Attendance</button>
    <button class="tab" onclick="showTab('results')">Results</button>
    <button class="tab" onclick="showTab('assignments')">Assignments</button>
</div>

<!-- ================= ATTENDANCE ================= -->
<div id="attendance" class="section active">
<div class="card">
<h3>Attendance Overview</h3>
<canvas id="attChart"></canvas>
</div>
</div>

<!-- ================= RESULTS ================= -->
<div id="results" class="section">
<div class="card">
<h3>IA Results</h3>
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

<!-- ================= ASSIGNMENTS ================= -->
<div id="assignments" class="section">
<div class="card">
<h3>Assignments</h3>
<?php foreach($assignments as $a): ?>
<div class="assignment">
<strong><?= htmlspecialchars($a['title']) ?></strong><br>
Due: <?= htmlspecialchars($a['due_date']) ?>
</div>
<?php endforeach; ?>
</div>
</div>

<script>
function showTab(id){
    document.querySelectorAll('.section').forEach(s=>s.classList.remove('active'));
    document.querySelectorAll('.tab').forEach(b=>b.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    event.target.classList.add('active');
}

const ctx = document.getElementById('attChart');
new Chart(ctx,{
    type:'bar',
    data:{
        labels: <?= json_encode(array_column($attendance,'subject')) ?>,
        datasets:[{
            data: <?= json_encode(array_map(fn($a)=>round($a['percent']),$attendance)) ?>,
            backgroundColor:'#007bff'
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

