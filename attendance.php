<?php
// 1. INCLUDE SESSION FIX (Crucial for Localhost)
require_once 'session_config.php';

session_start();
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db.php'; // PDO Connection

// =================================================================
// DEBUGGING BLOCK (Removes Redirects)
// =================================================================
echo "<div style='background:#222; color:#fff; padding:20px; font-family:sans-serif; margin-bottom:20px; border-bottom: 5px solid red;'>";
echo "<h2>🕵️ Authorization Debugger</h2>";

// 1. Check Session
if (!isset($_SESSION['user_id'])) {
    echo "<h3 style='color:#ff4444'>❌ FAIL: You are not logged in.</h3>";
    echo "<p>Session 'user_id' is missing.</p>";
    echo "<a href='login.php' style='color:#4dabf7; font-weight:bold;'>Go to Login</a>";
    echo "</div>";
    exit;
}
echo "<p style='color:#00c851'>✅ Logged In (User ID: " . $_SESSION['user_id'] . ")</p>";

// 2. Check Role
$role = strtolower($_SESSION['role'] ?? 'none');
echo "<p>Your Role: <strong>" . htmlspecialchars($role) . "</strong></p>";

$allowed_roles = ['admin', 'staff', 'hod', 'principal'];

if (!in_array($role, $allowed_roles)) {
    echo "<h3 style='color:#ff4444'>❌ ACCESS DENIED</h3>";
    echo "<p>This page is for <strong>Staff & Admins</strong> only.</p>";
    if ($role === 'student') {
        echo "<p>👉 <a href='student-dashboard.php' style='color:#4dabf7'>Go to Student Dashboard</a> to view your attendance.</p>";
    }
    echo "</div>";
    exit;
}

echo "<p style='color:#00c851'>✅ Access Granted. Loading Page...</p>";
echo "</div>"; 
// =================================================================
// END DEBUGGING
// =================================================================

$message = '';
$attendance_records = [];
$selected_date = $_GET['date'] ?? date('Y-m-d');
$selected_subject = $_GET['subject_id'] ?? '';

// 2. Fetch Data for Filters
try {
    $subjects = $pdo->query("SELECT id, name FROM subjects ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    
    // 3. Fetch Attendance Records if filter selected
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
        button:hover { background: #0056b3; }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px; border-bottom: 1px solid #ddd; text-align: left; }
        th { background: #007bff; color: white; }
        
        .status-present { color: #28a745; font-weight: bold; }
        .status-absent { color: #dc3545; font-weight: bold; }
        .no-data { text-align: center; padding: 20px; color: #666; font-style: italic; }
        .error { color: red; background: #fee; padding: 10px; margin-bottom: 10px; }
        .nav-link { display: block; margin-top: 20px; text-align: center; color: #666; text-decoration: none; }
    </style>
</head>
<body>

<div class="container">
    <h2>📋 View Attendance Sheet</h2>
    <?= $message ?>

    <!-- Filter Form -->
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

    <!-- Results Table -->
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
    
    <div style="text-align:center; margin-top:20px;">
        <a href="enter-attendance-daily.php" class="btn" style="text-decoration:none; background:#28a745; color:white; padding:10px 15px; border-radius:5px;">📝 Enter New Attendance</a>
    </div>
    
    <a href="admin-panel.php" class="nav-link">&laquo; Back to Dashboard</a>
</div>

</body>
</html>
