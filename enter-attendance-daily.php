<?php
session_start();
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db.php'; // PDO Connection

// 1. AUTHORIZATION CHECK
$allowed_roles = ['admin', 'staff', 'hod', 'principal'];
$user_role = strtolower($_SESSION['role'] ?? '');

if (!isset($_SESSION['user_id']) || !in_array($user_role, $allowed_roles)) {
    header("Location: login.php");
    exit;
}

$staff_id = $_SESSION['user_id'];
$message = '';

// 2. SELF-HEALING: Ensure 'attendance' table has correct columns
try {
    // Check if table exists, if not create it
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS attendance (
            id SERIAL PRIMARY KEY,
            student_id INT NOT NULL,
            subject_id INT NOT NULL,
            attendance_date DATE NOT NULL,
            status VARCHAR(10) NOT NULL CHECK (status IN ('present', 'absent')),
            marked_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(student_id, subject_id, attendance_date)
        );
    ");
    
    // Add columns if they are missing (for migration from older schema)
    // This fixes "Undefined column" errors
    try { $pdo->exec("ALTER TABLE attendance ADD COLUMN IF NOT EXISTS subject_id INT"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE attendance ADD COLUMN IF NOT EXISTS attendance_date DATE"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE attendance ADD COLUMN IF NOT EXISTS marked_by INT"); } catch(Exception $e){}
    
} catch (PDOException $e) {
    die("Database Setup Error: " . $e->getMessage());
}

// 3. HANDLE ATTENDANCE SUBMISSION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $subject_id = $_POST['subject_id'];
    $date = $_POST['attendance_date'];
    $attendance_data = $_POST['status'] ?? []; // Array of student_id => status

    if ($subject_id && $date && !empty($attendance_data)) {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                INSERT INTO attendance (student_id, subject_id, attendance_date, status, marked_by)
                VALUES (:sid, :subid, :date, :status, :marker)
                ON CONFLICT (student_id, subject_id, attendance_date) 
                DO UPDATE SET status = EXCLUDED.status, marked_by = EXCLUDED.marked_by
            ");

            $count = 0;
            foreach ($attendance_data as $sid => $status) {
                $stmt->execute([
                    ':sid' => $sid,
                    ':subid' => $subject_id,
                    ':date' => $date,
                    ':status' => $status,
                    ':marker' => $staff_id
                ]);
                $count++;
            }

            $pdo->commit();
            $message = "<div class='message success'>✅ Attendance saved for $count students!</div>";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='message error'>Error saving data: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        $message = "<div class='message error'>⚠️ No students selected or data missing.</div>";
    }
}

// 4. FETCH DATA FOR DROPDOWNS
$semesters = $pdo->query("SELECT id, name FROM semesters ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Subjects grouped by Semester
$sub_stmt = $pdo->query("SELECT id, name, subject_code, COALESCE(semester, semester_id) as sem_id FROM subjects WHERE semester IS NOT NULL OR semester_id IS NOT NULL ORDER BY name");
$subjects_by_sem = [];
while ($row = $sub_stmt->fetch(PDO::FETCH_ASSOC)) {
    $subjects_by_sem[$row['sem_id']][] = $row;
}

// 5. HANDLE "LOAD STUDENTS"
$students_list = [];
$selected_sem = $_POST['semester_id'] ?? '';
$selected_sub = $_POST['subject_id'] ?? '';
$selected_date = $_POST['attendance_date'] ?? date('Y-m-d');

if (isset($_POST['load_students']) || isset($_POST['save_attendance'])) {
    if ($selected_sem) {
        // Fetch students in this semester
        $stu_sql = "SELECT id, student_name, usn FROM students WHERE semester = :sem ORDER BY student_name";
        $stmt = $pdo->prepare($stu_sql);
        $stmt->execute([':sem' => $selected_sem]);
        $students_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($students_list)) {
            $message = "<div class='message warning'>⚠️ No students found in Semester $selected_sem. <br>Admin needs to import students first.</div>";
        }
    }
}

$json_subjects = json_encode($subjects_by_sem);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enter Daily Attendance</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7f6; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        
        .filters { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; background: #eef2f3; padding: 20px; border-radius: 8px; }
        label { font-weight: bold; display: block; margin-bottom: 5px; }
        select, input { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; }
        
        .btn { padding: 10px 20px; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; color: white; }
        .btn-load { background: #17a2b8; width: 100%; margin-top: 24px; }
        .btn-save { background: #28a745; width: 100%; margin-top: 20px; font-size: 1.1em; }
        .btn:hover { opacity: 0.9; }

        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border-bottom: 1px solid #ddd; text-align: left; }
        th { background: #007bff; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }

        .radio-group { display: flex; gap: 15px; }
        .radio-label { cursor: pointer; display: flex; align-items: center; gap: 5px; }
        .present { color: #28a745; font-weight: bold; }
        .absent { color: #dc3545; font-weight: bold; }

        .message { padding: 15px; text-align: center; margin-bottom: 20px; border-radius: 5px; font-weight: bold; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        .warning { background: #fff3cd; color: #856404; }
        
        .back-link { display: block; margin-top: 20px; text-align: center; text-decoration: none; color: #666; }
    </style>
</head>
<body>

<div class="container">
    <h2>📅 Enter Daily Attendance</h2>
    <?= $message ?>

    <form method="POST">
        <div class="filters">
            <div>
                <label>1. Select Semester:</label>
                <select name="semester_id" id="semester_id" required>
                    <option value="">-- Select --</option>
                    <?php foreach ($semesters as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $selected_sem == $s['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>2. Select Subject:</label>
                <select name="subject_id" id="subject_id" required>
                    <option value="">-- Select Semester First --</option>
                </select>
            </div>

            <div>
                <label>3. Date:</label>
                <input type="date" name="attendance_date" value="<?= htmlspecialchars($selected_date) ?>" required>
            </div>

            <div>
                <button type="submit" name="load_students" class="btn btn-load">🔄 Load List</button>
            </div>
        </div>

        <?php if (!empty($students_list)): ?>
            <div style="background: #e8f5e9; padding: 10px; text-align: center; border: 1px solid #c8e6c9; border-radius: 5px;">
                <strong><?= count($students_list) ?></strong> students found. Mark attendance below.
            </div>

            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student Name</th>
                        <th>USN</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students_list as $index => $stu): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= htmlspecialchars($stu['student_name']) ?></td>
                        <td><?= htmlspecialchars($stu['usn']) ?></td>
                        <td>
                            <div class="radio-group">
                                <label class="radio-label present">
                                    <input type="radio" name="status[<?= $stu['id'] ?>]" value="present" checked> Present
                                </label>
                                <label class="radio-label absent">
                                    <input type="radio" name="status[<?= $stu['id'] ?>]" value="absent"> Absent
                                </label>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <button type="submit" name="save_attendance" class="btn btn-save">💾 Save Attendance</button>
        <?php endif; ?>
    </form>

    <a href="staff-panel.php" class="back-link">&laquo; Back to Dashboard</a>
</div>

<script>
const subjectsData = <?= $json_subjects ?: '{}' ?>;
const semSelect = document.getElementById('semester_id');
const subSelect = document.getElementById('subject_id');
const oldSubject = "<?= $selected_sub ?>";

function updateSubjects() {
    const semId = semSelect.value;
    subSelect.innerHTML = '<option value="">-- Select Subject --</option>';
    
    if (semId && subjectsData[semId]) {
        subjectsData[semId].forEach(s => {
            let isSelected = (s.id == oldSubject) ? 'selected' : '';
            let opt = `<option value="${s.id}" ${isSelected}>${s.name} (${s.subject_code})</option>`;
            subSelect.innerHTML += opt;
        });
    }
}

semSelect.addEventListener('change', updateSubjects);
// Run on load to restore selection
if(semSelect.value) updateSubjects();
</script>

</body>
</html>
