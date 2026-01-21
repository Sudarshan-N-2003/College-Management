<?php
session_start();
if (!isset($_SESSION['student_id'])) {
    header("Location: student-login.php");
    exit;
}

include('db-config.php');
$student_id = $_SESSION['student_id'];

/* ================= STUDENT DETAILS ================= */
$studentStmt = $conn->prepare("SELECT usn, name, branch FROM students WHERE id=?");
$studentStmt->bind_param("i", $student_id);
$studentStmt->execute();
$student = $studentStmt->get_result()->fetch_assoc();

/* ================= ATTENDANCE ================= */
$attStmt = $conn->prepare("
    SELECT s.name AS subject,
           ROUND((a.attended_classes / a.total_classes) * 100) AS percentage
    FROM attendance a
    JOIN subjects s ON s.id = a.subject_id
    WHERE a.student_id = ?
");
$attStmt->bind_param("i", $student_id);
$attStmt->execute();
$attResult = $attStmt->get_result();

$attendanceData = [];
while ($row = $attResult->fetch_assoc()) {
    $attendanceData[] = $row;
}

/* ================= RESULTS ================= */
$resultStmt = $conn->prepare("
    SELECT s.name AS subject, r.marks
    FROM ia_results r
    JOIN subjects s ON s.id = r.subject_id
    WHERE r.student_id = ?
");
$resultStmt->bind_param("i", $student_id);
$resultStmt->execute();
$results = $resultStmt->get_result();

/* ================= ASSIGNMENTS ================= */
$assignStmt = $conn->prepare("
    SELECT s.name AS subject, a.title, a.description, a.due_date
    FROM assignments a
    JOIN subjects s ON s.id = a.subject_id
    ORDER BY a.due_date ASC
");
$assignStmt->execute();
$assignments = $assignStmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Student Dashboard</title>

<style>
/* 🔹 YOUR EXACT STYLES (UNCHANGED) */
<?= file_get_contents("student-dashboard-style.css") ?>
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

        <!-- ================= ATTENDANCE ================= -->
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

        <!-- ================= RESULTS ================= -->
        <div class="window" id="results">
            <h2>IA Results</h2>
            <div class="marks-card">
                <table width="100%" border="1">
                    <tr>
                        <th>Subject</th>
                        <th>Marks</th>
                        <th>Grade</th>
                    </tr>
                    <?php while ($r = $results->fetch_assoc()): 
                        $grade = ($r['marks'] >= 90) ? 'A+' :
                                 (($r['marks'] >= 75) ? 'A' :
                                 (($r['marks'] >= 60) ? 'B' : 'C'));
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($r['subject']) ?></td>
                        <td><?= $r['marks'] ?></td>
                        <td><?= $grade ?></td>
                    </tr>
                    <?php endwhile; ?>
                </table>
                <button class="print-btn" onclick="window.print()">Print</button>
            </div>
        </div>

        <!-- ================= ASSIGNMENTS ================= -->
        <div class="window" id="assignments">
            <h2>Assignments</h2>
            <ul class="assignments">
                <?php while ($a = $assignments->fetch_assoc()): ?>
                    <li>
                        <strong><?= htmlspecialchars($a['subject']) ?>:</strong>
                        <?= htmlspecialchars($a['title']) ?><br>
                        <?= htmlspecialchars($a['description']) ?><br>
                        <b>Due:</b> <?= date("d M Y", strtotime($a['due_date'])) ?>
                    </li>
                <?php endwhile; ?>
            </ul>
        </div>

    </div>
</div>

<script>
const buttons = document.querySelectorAll('.control-btn');
const windows = document.querySelectorAll('.window');

buttons.forEach(btn => {
    btn.onclick = () => {
        buttons.forEach(b => b.classList.remove('active'));
        windows.forEach(w => w.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById(btn.dataset.window).classList.add('active');
    };
});
</script>

</body>
</html>
