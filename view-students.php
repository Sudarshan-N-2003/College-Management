<?php
session_start();
require_once('db.php'); // Use our PDO connection ($pdo)

// ... (delete logic remains the same) ...
try {
    if (isset($_GET['delete_student'])) {
        $student_id = intval($_GET['delete_student']);
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM attendance WHERE student_id = ?")->execute([$student_id]);
        $pdo->prepare("DELETE FROM ia_results WHERE student_id = ?")->execute([$student_id]);
        $pdo->prepare("DELETE FROM daily_attendance WHERE student_id = ?")->execute([$student_id]);
        $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$student_id]);
        $pdo->commit();
        header("Location: view-students.php?status=deleted");
        exit;
    }

    // ... (pagination logic remains the same) ...
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;
    $records_per_page = 10;
    $start_from = ($page - 1) * $records_per_page;
    $selected_semester = isset($_GET['semester']) ? intval($_GET['semester']) : 0;
    $params = [];
    
    $count_sql = "SELECT COUNT(*) FROM students";
    if ($selected_semester > 0) {
        $count_sql .= " WHERE semester = :sem";
        $params[':sem'] = $selected_semester;
    }
    $total_stmt = $pdo->prepare($count_sql);
    $total_stmt->execute($params);
    $total_students = $total_stmt->fetchColumn();
    $total_pages = ceil($total_students / $records_per_page);

    // Fetch students - ADDED 'section'
    $fetch_sql = "SELECT id, usn, student_name, email, semester, section 
                  FROM students";
    if ($selected_semester > 0) {
        $fetch_sql .= " WHERE semester = :sem";
    }
    $fetch_sql .= " ORDER BY id ASC LIMIT :limit OFFSET :offset";
    
    $student_stmt = $pdo->prepare($fetch_sql);
    
    if ($selected_semester > 0) {
        $student_stmt->bindValue(':sem', $selected_semester, PDO::PARAM_INT);
    }
    $student_stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    $student_stmt->bindValue(':offset', $start_from, PDO::PARAM_INT);
    
    $student_stmt->execute();
    $students = $student_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}
$current_sl_no = $start_from + 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Students</title>
    <style>
        /* ... (styles remain the same) ... */
        :root { --space-cadet: #2b2d42; --cool-gray: #8d99ae; --antiflash-white: #edf2f4; --red-pantone: #ef233c; --fire-engine-red: #d90429; }
        body { font-family: 'Segoe UI', sans-serif; margin: 0; padding: 20px; background: var(--space-cadet); color: var(--antiflash-white); }
        .navbar { display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: 0 auto 20px auto; padding: 10px 20px; background: rgba(141, 153, 174, 0.1); border-radius: 10px; }
        .navbar h2 { margin: 0; }
        .navbar-links a { font-weight: bold; color: var(--antiflash-white); text-decoration: none; margin-left: 20px; }
        .navbar-links a:hover { text-decoration: underline; }
        .container { width: 90%; max-width: 1200px; margin: 20px auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); color: var(--space-cadet); }
        h2 { color: var(--space-cadet); border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 20px; }
        form.filter { margin-bottom: 15px; text-align: right; }
        select, button { padding: 8px 12px; font-size: 15px; border-radius: 5px; border: 1px solid var(--cool-gray); }
        select { background: #fff; color: var(--space-cadet); }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: var(--space-cadet); color: #fff; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .pagination { margin: 20px 0; text-align: center; }
        .pagination a { margin: 0 5px; padding: 8px 14px; border: 1px solid var(--space-cadet); border-radius: 4px; color: var(--space-cadet); text-decoration: none; transition: background 0.3s; }
        .pagination a.active { background-color: var(--space-cadet); color: #fff; }
        .pagination a:hover:not(.active) { background-color: #f0f0f0; }
        .action-btn { padding: 5px 10px; border-radius: 4px; text-decoration: none; color: #fff; margin-right: 5px; font-size: 0.9em; display: inline-block; }
        .edit-btn { background-color: #007bff; }
        .remove-btn { background-color: #dc3545; }
        .edit-btn:hover { background-color: #0056b3; }
        .remove-btn:hover { background-color: #b52d3b; }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>All Registered Students</h2>
        <div class="navbar-links">
            <a href="/admin">Back to Dashboard</a>
            <a href="/logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <!-- Semester Filter -->
        <form class="filter" method="get" action="">
            <label for="semester">Filter by Semester:</label>
            <select name="semester" id="semester" onchange="this.form.submit()">
                <option value="0">All Semesters</option>
                <?php for ($i = 1; $i <= 8; $i++): ?>
                    <option value="<?= $i ?>" <?= ($selected_semester == $i) ? 'selected' : '' ?>>Semester <?= $i ?></option>
                <?php endfor; ?>
            </select>
        </form>

        <table>
            <thead>
                <tr>
                    <th>Sl. No.</th>
                    <th>USN</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Semester</th>
                    <th>Section</th> <!-- <-- NEW -->
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center;">No students found.</td> <!-- <-- Colspan changed to 7 -->
                    </tr>
                <?php else: ?>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?= $current_sl_no ?></td>
                            <td><?= htmlspecialchars($student['usn'] ?? '') ?></td>
                            <td><?= htmlspecialchars($student['student_name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($student['email'] ?? '') ?></td>
                            <td><?= htmlspecialchars((string)($student['semester'] ?? '-')) ?></td>
                            <td><?= htmlspecialchars((string)($student['section'] ?? '-')) ?></td> <!-- <-- NEW -->
                            <td>
                                <a href="edit-student.php?id=<?= $student['id'] ?>" class="action-btn edit-btn">Edit</a>
                                <a href="?delete_student=<?= $student['id'] ?>" class="action-btn remove-btn" onclick="return confirm('Are you sure you want to delete this student?')">Remove</a>
                            </td>
                        </tr>
                        <?php $current_sl_no++; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <div class="pagination">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?= $i ?>&semester=<?= $selected_semester ?>" <?= ($i === $page) ? 'class="active"' : '' ?>><?= $i ?></a>
            <?php endfor; ?>
        </div>
    </div>
</body>
</html>
