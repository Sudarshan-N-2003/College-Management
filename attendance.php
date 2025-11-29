<?php
// 1. INCLUDE SESSION CONFIG
// If this file is missing, you will get a fatal error.
if (file_exists('session_config.php')) {
    require_once 'session_config.php';
} else {
    die("<h3 style='color:red'>CRITICAL ERROR: session_config.php is missing!</h3><p>You must create this file to fix login issues.</p>");
}

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db.php'; // PDO Connection

// =================================================================
// 🔍 DIAGNOSTIC MODE (No Redirects)
// =================================================================
echo "<div style='background:#222; color:#fff; padding:20px; font-family:monospace; border-bottom: 5px solid red;'>";
echo "<h2>🕵️ Login & Session Debugger</h2>";

// CHECK 1: Session ID
echo "<strong>1. Session ID:</strong> " . session_id() . "<br>";

// CHECK 2: User ID
echo "<strong>2. User ID:</strong> ";
if (isset($_SESSION['user_id'])) {
    echo "<span style='color:#00c851'>FOUND (" . $_SESSION['user_id'] . ")</span><br>";
} else {
    echo "<span style='color:#ff4444'>MISSING</span> <small>(This is why you are being redirected)</small><br>";
    echo "<p><strong>Potential Causes:</strong><br>";
    echo "- Browser is blocking cookies.<br>";
    echo "- session_config.php is not set up correctly for HTTPS.<br>";
    echo "- You simply haven't logged in yet.</p>";
    echo "<a href='login.php' style='color:#4dabf7; font-weight:bold; font-size:1.2em;'>&laquo; Go to Login Page</a>";
    exit; // Stop here
}

// CHECK 3: Role
$role = strtolower($_SESSION['role'] ?? 'none');
echo "<strong>3. Your Role:</strong> <span style='color:yellow'>$role</span><br>";

$allowed_roles = ['admin', 'staff', 'hod', 'principal'];

if ($role === 'student') {
    echo "<h3 style='color:#ff4444'>❌ Redirect Triggered</h3>";
    echo "<p>You are logged in as a <strong>Student</strong>.</p>";
    echo "<p>This page is for Staff only. You should be redirected to:</p>";
    echo "<a href='student-dashboard.php' style='color:#4dabf7'>student-dashboard.php</a>";
    exit;
}

if (!in_array($role, $allowed_roles)) {
    echo "<h3 style='color:#ff4444'>❌ Access Denied</h3>";
    echo "<p>Your role '<strong>$role</strong>' is not allowed on this page.</p>";
    exit;
}

echo "<h3 style='color:#00c851'>✅ ALL CHECKS PASSED</h3>";
echo "<p>If you see this, the logic is correct. The issue might have been a cached redirect.</p>";
echo "</div>"; 
// =================================================================
// END DIAGNOSTIC BLOCK
// =================================================================

// -------------------------------------------------------------------------
// 3. SELF-HEALING: Ensure Attendance Table Exists & Has Columns
// -------------------------------------------------------------------------
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS attendance (
            id SERIAL PRIMARY KEY,
            student_id INT NOT NULL,
            subject_id INT NOT NULL,
            attendance_date DATE NOT NULL,
            status VARCHAR(10) DEFAULT 'present',
            marked_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(student_id, subject_id, attendance_date)
        );
    ");
    try { $pdo->exec("ALTER TABLE attendance ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'present'"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE attendance ADD COLUMN IF NOT EXISTS subject_id INT"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE attendance ADD COLUMN IF NOT EXISTS attendance_date DATE"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE attendance ADD COLUMN IF NOT EXISTS marked_by INT"); } catch(Exception $e){}
} catch (PDOException $e) {
    die("Database Repair Failed: " . $e->getMessage());
}

// -------------------------------------------------------------------------
// 4. FETCH DATA
// -------------------------------------------------------------------------
$message = '';
$attendance_records = [];
$selected_date = $_GET['date'] ?? date('Y-m-d');
$selected_subject = $_GET['subject_id'] ?? '';

try {
    $subjects = $pdo->query("SELECT id, name FROM subjects ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    
    if ($selected_subject) {
        $sql = "
            SELECT 
                s.student_name,
                s.usn,
                a.status,
                a.attendance_date
            FROM attendance a
            JOIN students s ON a.student_id = s.id
            WHERE a.subject_id = :sub_id 
            AND a.attendance_date = :date
            ORDER BY s.student_name ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['sub_id' => $selected_subject, 'date' => $selected_date]);
        $attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $message = "<div class='error'>Database Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Attendance</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7f6; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        .filters { display: flex; gap: 15px; background: #eef2f3; padding: 20px; border-radius: 8px; margin-bottom: 20px; flex-wrap: wrap; }
        select, input { padding: 10px; border: 1px solid #ccc; border-radius: 5px; flex: 1; }
        button { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px; border-bottom: 1px solid #ddd; text-align: left; }
        th { background: #007bff; color: white; }
        .status-present { color: #28a745; font-weight: bold; }
        .status-absent { color: #dc3545; font-weight: bold; }
        .no-data { text-align: center; padding: 20px; color: #666; font-style: italic; }
        .error { color: red; background: #fee; padding: 10px; margin-bottom: 10px; }
        .nav-link { display: block; margin-top: 20px; text-align: center; color: #666; text-decoration: none; }
        .btn { text-decoration: none; background: #28a745; color: white; padding: 10px 15px; border-radius: 5px; }
    </style>
</head>
<body>

<div class="container">
    <h2>📋 View Attendance Sheet</h2>
    <?= $message ?>

    <form method="GET" class="filters">
        <select name="subject_id" required>
            <option value="">-- Select Subject --</option>
            <?php foreach ($subjects as $sub): ?>
                <option value="<?= htmlspecialchars($sub['id']) ?>" <?= $selected_subject == $sub['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($sub['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="date" value="<?= htmlspecialchars($selected_date) ?>" required>
        <button type="submit">View</button>
    </form>

    <?php if ($selected_subject): ?>
        <?php if (count($attendance_records) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>USN</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attendance_records as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['student_name'] ?? 'Unknown') ?></td>
                            <td><?= htmlspecialchars($row['usn'] ?? '-') ?></td>
                            <td>
                                <?php 
                                    $status = strtolower($row['status'] ?? '');
                                    $class = ($status === 'present') ? 'status-present' : 'status-absent';
                                    echo "<span class='$class'>" . ucfirst($status) . "</span>";
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-data">No attendance found for this date/subject.</div>
        <?php endif; ?>
    <?php endif; ?>
    
    <div style="text-align:center; margin-top:30px;">
        <a href="enter-attendance-daily.php" class="btn">📝 Enter New Attendance</a>
    </div>
    
    <a href="admin-panel.php" class="nav-link">&laquo; Back to Dashboard</a>
</div>

</body>
</html>
