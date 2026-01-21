<?php
session_start();
if (!isset($_SESSION['student_id'])) {
    header("Location: student-login.php");
    exit;
}

include('db-config.php'); // must return $conn as PDO

$student_id = $_SESSION['student_id'];

/* ================= STUDENT DETAILS ================= */
$studentStmt = $conn->prepare(
    "SELECT usn, name, branch FROM students WHERE id = ?"
);
$studentStmt->execute([$student_id]);
$student = $studentStmt->fetch(PDO::FETCH_ASSOC);

/* ================= ATTENDANCE ================= */
$attStmt = $conn->prepare("
    SELECT s.name AS subject,
           ROUND((a.attended_classes / a.total_classes) * 100) AS percentage
    FROM attendance a
    JOIN subjects s ON s.id = a.subject_id
    WHERE a.student_id = ?
");
$attStmt->execute([$student_id]);
$attendanceData = $attStmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= RESULTS ================= */
$resultStmt = $conn->prepare("
    SELECT s.name AS subject, r.marks
    FROM ia_results r
    JOIN subjects s ON s.id = r.subject_id
    WHERE r.student_id = ?
");
$resultStmt->execute([$student_id]);
$results = $resultStmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= ASSIGNMENTS ================= */
$assignStmt = $conn->prepare("
    SELECT s.name AS subject, a.title, a.description, a.due_date
    FROM assignments a
    JOIN subjects s ON s.id = a.subject_id
    ORDER BY a.due_date ASC
");
$assignStmt->execute();
$assignments = $assignStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Student Dashboard</title>
    <style>
        /* keep your SAME CSS */
    </style>
</head>
<body>

<div class="dashboard">
    <div class="header">
        <h1>Student Dashboard</h1>
        <p><?= htmlspecialchars($student['name']) ?> | <?= htmlspecialchars($student['branch']) ?></p>
    </div>

    <div class="controls">
        <button class="control-btn active" data-window="attendance">Attendance</button>
        <button class="control-btn" data-window="results">Results</button>
        <button class="control-btn" data-window="assignments">Assignments</button>
    </div>

    <div class="windows">

        <!-- ATTENDANCE -->
        <div class="window active" id="attendance">
            <h2>Attendance</h2>
            <div class="chart">
                <?php foreach ($attendanceData as $a): ?>
                    <div class="bar-container">
                        <div class="bar" style="height: <?= $a['percentage'] ?>%;">
                            <?= $a['percentage'] ?>%
                        </div>
                        <div class="label"><?= htmlspecialchars($a['subject']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- RESULTS -->
        <div class="window" id="results">
            <h2>IA Results</h2>
            <table border="1" width="100%">
                <tr>
                    <th>Subject</th>
                    <th>Marks</th>
                    <th>Grade</th>
                </tr>
                <?php foreach ($results as $r): 
                    $grade = $r['marks'] >= 90 ? 'A+' :
                             ($r['marks'] >= 75 ? 'A' :
                             ($r['marks'] >= 60 ? 'B' : 'C'));
                ?>
                <tr>
                    <td><?= htmlspecialchars($r['subject']) ?></td>
                    <td><?= $r['marks'] ?></td>
                    <td><?= $grade ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <!-- ASSIGNMENTS -->
        <div class="window" id="assignments">
            <h2>Assignments</h2>
            <ul>
                <?php foreach ($assignments as $a): ?>
                    <li>
                        <strong><?= htmlspecialchars($a['subject']) ?>:</strong>
                        <?= htmlspecialchars($a['title']) ?><br>
                        <?= htmlspecialchars($a['description']) ?><br>
                        <b>Due:</b> <?= date("d M Y", strtotime($a['due_date'])) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

    </div>
</div>

<script>
document.querySelectorAll('.control-btn').forEach(btn => {
    btn.onclick = () => {
        document.querySelectorAll('.control-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.window').forEach(w => w.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById(btn.dataset.window).classList.add('active');
    };
});
</script>

</body>
</html>
