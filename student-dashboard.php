<?php
session_start();

/* ---------- DB CONNECTION (INLINE – NO db-config.php REQUIRED) ---------- */
$dsn = getenv('DATABASE_URL');
if ($dsn) {
    $db = parse_url($dsn);
    $pdo = new PDO(
        "pgsql:host={$db['host']};port={$db['port']};dbname=" . ltrim($db['path'], '/').";sslmode=require",
        $db['user'],
        $db['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} else {
    // local fallback
    $pdo = new PDO(
        "pgsql:host=localhost;port=5432;dbname=college_exam_portal",
        "postgres",
        "password",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}

/* ---------- AUTH ---------- */
if (!isset($_SESSION['student_id'])) {
    header("Location: student-login.php");
    exit;
}

$student_id = $_SESSION['student_id'];

/* ---------- STUDENT INFO ---------- */
$stuStmt = $pdo->prepare("
    SELECT student_name, usn, branch 
    FROM students 
    WHERE id = :id
");
$stuStmt->execute(['id' => $student_id]);
$student = $stuStmt->fetch();

if (!$student) {
    die("Student not found");
}

/* ---------- ATTENDANCE ---------- */
$attStmt = $pdo->prepare("
    SELECT s.name AS subject,
           COUNT(a.id) AS total,
           SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present
    FROM attendance a
    JOIN subjects s ON s.id = a.subject_id
    WHERE a.student_id = :sid
    GROUP BY s.name
");
$attStmt->execute(['sid' => $student_id]);
$attendance = $attStmt->fetchAll();

/* ---------- IA RESULTS ---------- */
$resStmt = $pdo->prepare("
    SELECT 
        s.name AS subject,
        r.marks,
        r.max_marks
    FROM ia_results r
    JOIN question_papers qp ON qp.id = r.qp_id
    JOIN subjects s ON s.id = qp.subject_id
    WHERE r.student_id = :sid
    ORDER BY r.created_at DESC
");
$resStmt->execute(['sid' => $student_id]);
$results = $resStmt->fetchAll();

/* ---------- ASSIGNMENTS (optional dummy / extend later) ---------- */
$assignments = [
    "Submit DBMS Assignment by Friday",
    "Complete CN Lab Record",
    "Mini Project Phase-1 Submission"
];
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Student Dashboard</title>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
body{margin:0;font-family:Arial;background:#f4f6f9}
.header{background:#003366;color:#fff;padding:15px}
.header b{font-size:18px}
.tabs{display:flex;gap:10px;padding:10px;background:#eee}
.tabs button{padding:10px 20px;border:none;cursor:pointer}
.tabs button.active{background:#003366;color:#fff}
.section{display:none;padding:20px}
.section.active{display:block}
canvas{max-width:600px;margin:auto}
.card{background:#fff;padding:15px;margin:10px 0;border-radius:8px}
table{width:100%;border-collapse:collapse}
th,td{border:1px solid #ccc;padding:8px;text-align:center}
</style>
</head>

<body>

<div class="header">
    <b><?= htmlspecialchars($student['student_name'] ?? '') ?></b> |
    USN: <?= htmlspecialchars($student['usn'] ?? '') ?> |
    Branch: <?= htmlspecialchars($student['branch'] ?? '') ?>
</div>

<div class="tabs">
    <button class="tab active" onclick="openTab('att')">Attendance</button>
    <button class="tab" onclick="openTab('res')">Results</button>
    <button class="tab" onclick="openTab('ass')">Assignments</button>
</div>

<!-- ATTENDANCE -->
<div id="att" class="section active">
    <div class="card">
        <canvas id="attChart"></canvas>
    </div>
</div>

<!-- RESULTS -->
<div id="res" class="section">
    <div class="card">
        <table>
            <tr><th>Subject</th><th>Marks</th><th>Max</th></tr>
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
<div id="ass" class="section">
    <div class="card">
        <ul>
            <?php foreach ($assignments as $a): ?>
                <li><?= htmlspecialchars($a) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<script>
function openTab(id){
    document.querySelectorAll('.section').forEach(s=>s.classList.remove('active'));
    document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    event.target.classList.add('active');
}

/* Attendance Chart */
const labels = <?= json_encode(array_column($attendance,'subject')) ?>;
const data = <?= json_encode(array_map(fn($a)=>$a['total']>0?round($a['present']*100/$a['total']):0,$attendance)) ?>;

new Chart(document.getElementById('attChart'),{
    type:'bar',
    data:{labels,datasets:[{label:'Attendance %',data,backgroundColor:'#007bff'}]},
    options:{scales:{y:{beginAtZero:true,max:100}}}
});
</script>

</body>
</html>
