<?php
// 1. SESSION CONFIG
require_once 'session_config.php'; 

session_start();
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db.php'; // PDO Connection

// -------------------------------------------------------------------------
// 2. SMART AUTHORIZATION (Handles BOTH Login Types)
// -------------------------------------------------------------------------
$student_id = null;

// CASE A: Logged in via New System (user_id + role)
if (isset($_SESSION['user_id'])) {
    $role = strtolower($_SESSION['role'] ?? '');
    if ($role !== 'student') {
        die("Access Denied: Students only. <a href='logout.php'>Logout</a>");
    }
    
    // Resolve User ID -> Student ID
    try {
        $stmt = $pdo->prepare("SELECT email FROM users WHERE id = :id");
        $stmt->execute(['id' => $_SESSION['user_id']]);
        $email = $stmt->fetchColumn();
        
        if ($email) {
            $stmt = $pdo->prepare("SELECT id FROM students WHERE email = :email");
            $stmt->execute(['email' => $email]);
            $student_id = $stmt->fetchColumn();
        }
    } catch (Exception $e) { /* Ignore */ }
} 
// CASE B: Logged in via Old System (student_id directly)
elseif (isset($_SESSION['student_id'])) {
    $student_id = $_SESSION['student_id'];
} 
// CASE C: Not Logged In
else {
    header("Location: student-login.php");
    exit;
}

// Final Check
if (empty($student_id)) {
    die("<div style='padding:20px; color:red; text-align:center;'>
            <h2>❌ Student ID Not Found</h2>
            <p>We verified your login, but could not find your academic record.</p>
            <a href='logout.php'>Logout and Try Again</a>
         </div>");
}

// -------------------------------------------------------------------------
// 3. FETCH RESULTS
// -------------------------------------------------------------------------
$results = [];
try {
    $sql = "
        SELECT 
            COALESCE(s.name, 'General') AS subject_name,
            qp.title AS test_name,
            ir.marks,
            ir.max_marks,
            ir.created_at
        FROM ia_results ir
        JOIN question_papers qp ON ir.qp_id = qp.id
        LEFT JOIN subjects s ON qp.subject_id = s.id
        WHERE ir.student_id = :sid
        ORDER BY ir.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['sid' => $student_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>IA Results</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; margin: 0; padding: 40px 20px; color: #334155; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        h2 { text-align: center; margin-bottom: 30px; color: #0f172a; border-bottom: 3px solid #3b82f6; padding-bottom: 10px; display: inline-block; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #f8fafc; color: #64748b; padding: 15px; text-align: left; font-weight: 600; border-bottom: 2px solid #e2e8f0; }
        td { padding: 15px; border-bottom: 1px solid #e2e8f0; font-size: 0.95rem; }
        
        .score-badge { background: #dcfce7; color: #166534; padding: 5px 10px; border-radius: 20px; font-weight: 700; font-size: 0.9rem; }
        .back-link { display: inline-block; margin-top: 30px; text-decoration: none; color: #64748b; font-weight: 500; }
        .back-link:hover { color: #3b82f6; }
        .no-records { text-align: center; padding: 40px; color: #94a3b8; font-style: italic; background: #f8fafc; border-radius: 8px; border: 1px dashed #cbd5e1; }
    </style>
</head>
<body>

    <div class="container">
        <div style="text-align:center"><h2>🏆 Internal Assessment Results</h2></div>

        <?php if (empty($results)): ?>
            <div class="no-records">You haven't completed any tests yet.</div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Test Name</th>
                        <th>Score</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $row): ?>
                        <tr>
                            <td style="font-weight:500; color:#0f172a;"><?= htmlspecialchars($row['subject_name']) ?></td>
                            <td><?= htmlspecialchars($row['test_name']) ?></td>
                            <td>
                                <span class="score-badge"><?= htmlspecialchars($row['marks']) ?> / <?= htmlspecialchars($row['max_marks']) ?></span>
                            </td>
                            <td><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div style="text-align:center;">
            <a href="student-dashboard.php" class="back-link">&laquo; Back to Dashboard</a>
        </div>
    </div>

</body>
</html>
