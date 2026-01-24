<?php
session_start();
require_once 'db.php';
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

/* ========= SECURITY ========= */
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','staff','hod','principal'])) {
    header("Location: login.php");
    exit;
}

$staff_id = $_SESSION['user_id'];

/* ========= FETCH SUBJECTS ========= */
$subjects = $pdo->query("SELECT id, name FROM subjects ORDER BY name")->fetchAll();

/* ========= IMAGE UPLOAD (AJAX) ========= */
if (isset($_POST['upload_image'])) {
    $unit = (int)$_POST['unit'];
    $qno  = (int)$_POST['qno'];
    $sid  = (int)$_POST['subject_id'];

    if (!isset($_FILES['image'])) {
        echo json_encode(['status'=>'error','msg'=>'No image']);
        exit;
    }

    $dir = "uploads/questions/";
    if (!is_dir($dir)) mkdir($dir,0777,true);

    $name = uniqid()."_".$_FILES['image']['name'];
    $path = $dir.$name;

    move_uploaded_file($_FILES['image']['tmp_name'], $path);

    $stmt = $pdo->prepare("
        INSERT INTO question_images
        (staff_id, subject_id, unit, question_no, image_url)
        VALUES (?,?,?,?,?)
    ");
    $stmt->execute([$staff_id,$sid,$unit,$qno,$path]);

    echo json_encode(['status'=>'ok','url'=>$path]);
    exit;
}

/* ========= MAIN FORM ========= */
$msg=""; $type="";

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['mode'])) {

    try {
        $mode = $_POST['mode'];
        $subject_id = (int)$_POST['subject_id'];
        $exam_time = (int)$_POST['exam_time'];

        $sheet = IOFactory::load($_FILES['excel']['tmp_name'])->getActiveSheet();
        $rows = $sheet->toArray();

        $questions=[];
        $total=0;

        if ($mode==='ia') {
            foreach($rows as $i=>$r){
                if($i==0||empty($r[1])) continue;
                $questions[]=[
                    'unit'=>$r[0],
                    'question'=>$r[1],
                    'marks'=>(int)$r[2],
                    'co'=>$r[3],
                    'po'=>$r[4],
                    'rbt'=>$r[5]
                ];
                $total+=(int)$r[2];
            }
        }

        if ($mode==='mcq') {
            foreach($rows as $i=>$r){
                if($i==0||empty($r[0])) continue;
                $questions[]=[
                    'q'=>$r[0],
                    'opt'=>['A'=>$r[1],'B'=>$r[2],'C'=>$r[3],'D'=>$r[4]],
                    'ans'=>$r[5],
                    'marks'=>(int)$r[6]
                ];
                $total+=(int)$r[6];
            }
        }

        shuffle($questions);
        $picked=[]; $sum=0;
        foreach($questions as $q){
            if($sum+$q['marks']<=50){
                $picked[]=$q;
                $sum+=$q['marks'];
            }
            if($sum>=45) break;
        }

        $pdo->prepare("
            INSERT INTO question_papers
            (staff_id,subject_id,title,content,exam_time,mode)
            VALUES (?,?,?,?,?,?)
        ")->execute([
            $staff_id,$subject_id,
            "QP-".date('YmdHis'),
            json_encode($picked),
            $exam_time,$mode
        ]);

        $msg="Question Paper Generated ($sum Marks)";
        $type="success";

    } catch(Exception $e){
        $msg=$e->getMessage();
        $type="error";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>QP Generator</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
body{font-family:Arial;background:#f4f6fa}
.box{max-width:750px;margin:20px auto;background:#fff;padding:20px;border-radius:8px}
label{font-weight:bold;margin-top:12px;display:block}
select,input,button{width:100%;padding:10px;margin-top:5px}
button{background:#0b3c6f;color:#fff;border:none}
.hidden{display:none}
.msg.success{background:#d4edda;padding:10px}
.msg.error{background:#f8d7da;padding:10px}
.popup{position:fixed;top:0;left:0;right:0;bottom:0;background:#0008;display:none}
.popup .card{background:#fff;padding:20px;margin:80px auto;width:90%;max-width:400px}
</style>

<script>
function modeChange(){
 let m=document.getElementById('mode').value;
 document.getElementById('iaBlock').style.display=m==='ia'?'block':'none';
 document.getElementById('mcqBlock').style.display=m==='mcq'?'block':'none';
}
function openImg(){document.getElementById('popup').style.display='block'}
function closeImg(){document.getElementById('popup').style.display='none'}

function uploadImg(){
 let f=new FormData(document.getElementById('imgForm'));
 fetch('create-question.php',{method:'POST',body:f})
 .then(r=>r.json()).then(d=>{
   alert("Uploaded: "+d.url);
 });
}
</script>
</head>

<body>
<div class="box">
<h2>Question Paper Generator</h2>

<?php if($msg): ?>
<div class="msg <?= $type ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
<label>Subject</label>
<select name="subject_id" required>
<option value="">Select</option>
<?php foreach($subjects as $s): ?>
<option value="<?= $s['id'] ?>"><?= $s['name'] ?></option>
<?php endforeach; ?>
</select>

<label>Mode</label>
<select name="mode" id="mode" onchange="modeChange()" required>
<option value="">Select</option>
<option value="ia">IA (VTU)</option>
<option value="mcq">MCQ</option>
</select>

<div id="iaBlock" class="hidden">
<button type="button" onclick="openImg()">Upload Question Images</button>
<a href="VTU_IA_Question_Bank_Template.xlsx">Download IA Excel</a>
</div>

<div id="mcqBlock" class="hidden">
<a href="MCQ_Question_Bank_Template.xlsx">Download MCQ Excel</a>
</div>

<label>Exam Time</label>
<input type="number" name="exam_time" min="30" required>

<label>Upload Excel</label>
<input type="file" name="excel" required>

<button type="submit">Generate Paper</button>
</form>
</div>

<!-- IMAGE POPUP -->
<div class="popup" id="popup">
<div class="card">
<h3>Upload Image</h3>
<form id="imgForm" enctype="multipart/form-data">
<input type="hidden" name="upload_image" value="1">
<input type="hidden" name="subject_id" value="<?= $subjects[0]['id'] ?? 0 ?>">
<label>Unit</label><input name="unit" type="number" required>
<label>Question No</label><input name="qno" type="number" required>
<label>Image</label><input type="file" name="image" required>
<button type="button" onclick="uploadImg()">Upload</button>
<button type="button" onclick="closeImg()">Close</button>
</form>
</div>
</div>
</body>
</html>
