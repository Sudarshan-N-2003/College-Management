<?php
session_start();

require_once 'db.php';      // <-- THIS CREATES $pdo
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

/* ================= SECURITY ================= */
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','staff','hod','principal'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION['user_id'])) {
    die("User session expired");
}

/* ================= FETCH SUBJECTS ================= */
$subjects = $pdo->query("
    SELECT id, name 
    FROM subjects 
    ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

$message = "";
$type = "";

/* ================= FORM SUBMIT ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {
        if (empty($_POST['subject_id']) || empty($_POST['mode']) || empty($_POST['exam_time'])) {
            throw new Exception("All fields are required");
        }

        if (!isset($_FILES['excel']) || $_FILES['excel']['error'] !== 0) {
            throw new Exception("Excel upload failed");
        }

        $subject_id = (int)$_POST['subject_id'];
        $mode       = $_POST['mode']; // ia | mcq
        $exam_time  = (int)$_POST['exam_time'];
        $staff_id   = $_SESSION['user_id'];

        if (!in_array($mode, ['ia','mcq'])) {
            throw new Exception("Invalid mode selected");
        }

        /* ================= READ EXCEL ================= */
        $spreadsheet = IOFactory::load($_FILES['excel']['tmp_name']);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        if (count($rows) < 2) {
            throw new Exception("Excel contains no data");
        }

        $questions = [];
        $total = 0;

        /* ================= IA MODE ================= */
        if ($mode === 'ia') {
            // Unit | Question | Marks | CO | PO | RBT | Image_URL
            foreach ($rows as $i => $r) {
                if ($i === 0) continue;

                if (empty($r[1]) || empty($r[2])) continue;

                $questions[] = [
                    'unit'  => trim($r[0]),
                    'text'  => trim($r[1]),
                    'marks' => (int)$r[2],
                    'co'    => trim($r[3] ?? ''),
                    'po'    => trim($r[4] ?? ''),
                    'rbt'   => trim($r[5] ?? ''),
                    'image' => trim($r[6] ?? '')
                ];
                $total += (int)$r[2];
            }
        }

        /* ================= MCQ MODE ================= */
        if ($mode === 'mcq') {
            // Question | A | B | C | D | Correct | Marks
            foreach ($rows as $i => $r) {
                if ($i === 0) continue;

                if (empty($r[0]) || empty($r[5])) continue;

                $questions[] = [
                    'question' => trim($r[0]),
                    'options'  => [
                        'A' => trim($r[1]),
                        'B' => trim($r[2]),
                        'C' => trim($r[3]),
                        'D' => trim($r[4]),
                    ],
                    'correct' => strtoupper(trim($r[5])),
                    'marks'   => (int)$r[6]
                ];
                $total += (int)$r[6];
            }
        }

        if ($total < 50) {
            throw new Exception("Question bank has only $total marks. Minimum 50 required.");
        }

        shuffle($questions);

        /* ================= PICK QUESTIONS ================= */
        $selected = [];
        $sum = 0;

        foreach ($questions as $q) {
            if ($sum + $q['marks'] <= 50) {
                $selected[] = $q;
                $sum += $q['marks'];
            }
            if ($sum >= 45) break;
        }

        /* ================= SAVE QUESTION PAPER ================= */
        $stmt = $pdo->prepare("
            INSERT INTO question_papers 
            (staff_id, subject_id, title, content, exam_time, mode)
            VALUES (?,?,?,?,?,?)
        ");

        $stmt->execute([
            $staff_id,
            $subject_id,
            "QP-" . date("Ymd-His"),
            json_encode($selected),
            $exam_time,
            $mode
        ]);

        $message = "Question Paper Generated Successfully (Marks: $sum)";
        $type = "success";

    } catch (Exception $e) {
        $message = $e->getMessage();
        $type = "error";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Question Paper Generator</title>
<meta name="viewport" content="width=device-width,initial-scale=1">

<style>
body{font-family:Arial;background:#f4f6fa;margin:0}
.container{max-width:720px;margin:30px auto;background:#fff;padding:20px;border-radius:8px}
label{font-weight:bold;margin-top:12px;display:block}
select,input,button{width:100%;padding:10px;margin-top:6px}
button{background:#0b3c6f;color:#fff;border:none;font-size:16px}
.hidden{display:none}
.msg.success{background:#d4edda;color:#155724;padding:10px;margin-bottom:10px}
.msg.error{background:#f8d7da;color:#721c24;padding:10px;margin-bottom:10px}
.download a{display:block;margin-top:10px;text-decoration:none;font-weight:bold}
</style>

<script>
function toggleMode(){
    let m=document.getElementById("mode").value;
    document.getElementById("ia_sample").style.display = m==="ia"?"block":"none";
    document.getElementById("mcq_sample").style.display = m==="mcq"?"block":"none";
}
</script>
</head>

<body>
<div class="container">
<h2>Automatic Question Paper Generator</h2>

<?php if($message): ?>
<div class="msg <?= $type ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">

<label>Subject</label>
<select name="subject_id" required>
<option value="">Select</option>
<?php foreach($subjects as $s): ?>
<option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
<?php endforeach; ?>
</select>

<label>Exam Type</label>
<select name="mode" id="mode" onchange="toggleMode()" required>
<option value="">Select</option>
<option value="ia">IA (VTU Descriptive)</option>
<option value="mcq">MCQ (Online Test)</option>
</select>

<div id="ia_sample" class="download hidden">
<a href="VTU_IA_Question_Bank_Template.xlsx" download>⬇ Download IA Excel Sample</a>
</div>

<div id="mcq_sample" class="download hidden">
<a href="MCQ_Question_Bank_Template.xlsx" download>⬇ Download MCQ Excel Sample</a>
</div>

<label>Exam Time (minutes)</label>
<input type="number" name="exam_time" min="30" max="180" required>

<label>Upload Excel</label>
<input type="file" name="excel" accept=".xlsx,.xls" required>

<button type="submit">Generate Question Paper</button>

</form>
</div>
</body>
</html>
