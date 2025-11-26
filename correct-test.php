<?php
require_once 'session_config.php';
session_start();
require_once 'db.php';

// Authorization
$allowed_roles = ['admin', 'staff', 'hod', 'principal'];
if (!isset($_SESSION['role']) || !in_array(strtolower($_SESSION['role']), $allowed_roles)) {
    die("Access Denied");
}

$student_id = filter_input(INPUT_GET, 'sid', FILTER_VALIDATE_INT);
$qp_id = filter_input(INPUT_GET, 'qid', FILTER_VALIDATE_INT);
$message = '';

// 1. Handle Marks Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_marks = $_POST['manual_marks'] ?? [];
    $total_score = 0;
    
    foreach ($new_marks as $mark) {
        $total_score += floatval($mark);
    }
    
    // Update the Total Score in Database
    try {
        $upd = $pdo->prepare("UPDATE ia_results SET marks = :marks WHERE student_id = :sid AND qp_id = :qid");
        $upd->execute(['marks' => round($total_score), 'sid' => $student_id, 'qid' => $qp_id]);
        $message = "<div class='success'>✅ Marks updated successfully! New Total: " . round($total_score) . "</div>";
    } catch (Exception $e) {
        $message = "<div class='error'>Error: " . $e->getMessage() . "</div>";
    }
}

// 2. Fetch Data
try {
    // Get Test Details & Student Answers
    $stmt = $pdo->prepare("
        SELECT ir.content as student_answers, ir.marks, qp.content as questions_json, qp.title, s.student_name 
        FROM ia_results ir 
        JOIN question_papers qp ON ir.qp_id = qp.id 
        JOIN students s ON ir.student_id = s.id
        WHERE ir.student_id = :sid AND ir.qp_id = :qid
    ");
    $stmt->execute(['sid' => $student_id, 'qid' => $qp_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) die("Submission not found.");

    $questions = json_decode($data['questions_json'] ?? '[]', true);
    $student_answers = json_decode($data['student_answers'] ?? '[]', true);
    
    // Handle case where student answers are object vs array
    if (!is_array($student_answers)) $student_answers = (array)$student_answers;

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Grading: <?= htmlspecialchars($data['student_name']) ?></title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #eee; padding: 20px; }
        .paper { max-width: 900px; margin: 0 auto; background: white; padding: 40px; border-radius: 5px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .q-box { border: 1px solid #ddd; padding: 20px; margin-bottom: 20px; border-radius: 5px; background: #fafafa; }
        .q-title { font-weight: bold; font-size: 1.1em; margin-bottom: 10px; display: block; }
        .ans-section { margin-top: 10px; display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .student-ans { background: #e3f2fd; padding: 10px; border-radius: 4px; border: 1px solid #90caf9; }
        .correct-ans { background: #e8f5e9; padding: 10px; border-radius: 4px; border: 1px solid #a5d6a7; color: #2e7d32; }
        .grading-box { margin-top: 15px; padding-top: 15px; border-top: 1px dashed #ccc; text-align: right; }
        input[type="number"] { width: 60px; padding: 5px; text-align: center; font-weight: bold; }
        .sticky-header { position: sticky; top: 0; background: white; padding: 10px 0; border-bottom: 2px solid #333; z-index: 100; display: flex; justify-content: space-between; align-items: center; }
        .save-btn { background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        .success { background: #d4edda; color: #155724; padding: 10px; margin-bottom: 15px; border-radius: 5px; }
        .long-text { white-space: pre-wrap; }
    </style>
</head>
<body>

<div class="paper">
    <?= $message ?>
    
    <form method="POST">
        <div class="sticky-header">
            <div>
                <h2 style="margin:0">Grading: <?= htmlspecialchars($data['student_name']) ?></h2>
                <small><?= htmlspecialchars($data['title']) ?></small>
            </div>
            <div>
                <span style="font-size: 1.2em; margin-right: 15px;">Total Score: <strong id="display-total"><?= $data['marks'] ?></strong></span>
                <button type="submit" class="save-btn">💾 Save Marks</button>
            </div>
        </div>
        <br>

        <?php if (is_array($questions)): ?>
            <?php foreach ($questions as $idx => $q): ?>
                <?php 
                    $s_ans = $student_answers[$idx] ?? '<i>No Answer</i>';
                    $max_m = $q['marks'] ?? 0;
                    // Auto-calculate score visually for initial load (optional logic)
                    $default_val = 0; 
                    // Simple check: if exact match, give full marks (just as a helper)
                    if (trim(strtolower($s_ans)) == trim(strtolower($q['answer']))) {
                        $default_val = $max_m;
                    }
                ?>
                <div class="q-box">
                    <span class="q-title">Q<?= $idx+1 ?>: <?= htmlspecialchars($q['question']) ?> <span style="float:right; color:#666; font-weight:normal">(Max: <?= $max_m ?>)</span></span>
                    
                    <div class="ans-section">
                        <div>
                            <strong>Student's Answer:</strong>
                            <div class="student-ans long-text"><?= htmlspecialchars($s_ans) ?></div>
                        </div>
                        <div>
                            <strong>Correct Answer / Keywords:</strong>
                            <div class="correct-ans long-text"><?= htmlspecialchars($q['answer']) ?></div>
                        </div>
                    </div>

                    <div class="grading-box">
                        <label>Marks Awarded: </label>
                        <input type="number" name="manual_marks[]" class="mark-input" 
                               value="<?= $default_val ?>" 
                               min="0" max="<?= $max_m ?>" step="0.5" 
                               oninput="calcTotal()"> 
                        / <?= $max_m ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>Questions data structure is invalid or legacy format.</p>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 30px;">
            <button type="submit" class="save-btn" style="width: 100%;">Update Final Score</button>
        </div>
    </form>
</div>

<script>
function calcTotal() {
    let total = 0;
    document.querySelectorAll('.mark-input').forEach(input => {
        total += parseFloat(input.value || 0);
    });
    document.getElementById('display-total').innerText = total;
}
// Run once on load
// Note: This script doesn't know the *saved* per-question marks because
// your database only stores the TOTAL score. 
// Staff must re-enter marks for review.
// To fix this permanently, you'd need to change database structure to store per-question marks.
</script>

</body>
</html>
