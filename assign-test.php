<?php
// session_start(); // Enable if using sessions
require_once('db.php'); // Use your PDO connection

// --- Security Check (Placeholder) ---
/*
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') { // Assuming staff or admin
    header("Location: login.php");
    exit;
}
*/

$message = ''; // For success/error messages

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $class_id = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);
    $qp_id = filter_input(INPUT_POST, 'qp_id', FILTER_VALIDATE_INT);
    $semester_id = filter_input(INPUT_POST, 'semester_id', FILTER_VALIDATE_INT); // Get semester from form

    if ($class_id && $qp_id && $semester_id) {
        try {
            // PostgreSQL-compatible query to insert if not already exists
            $sql = "INSERT INTO test_allocation (class_id, qp_id) 
                    VALUES (:class_id, :qp_id) 
                    ON CONFLICT (class_id, qp_id) DO NOTHING";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':class_id' => $class_id, ':qp_id' => $qp_id]);

            if ($stmt->rowCount() > 0) {
                $message = "<p class='message success'>Test allocated successfully!</p>";
            } else {
                $message = "<p class='message error'>This test is already allocated to this class.</p>";
            }
        } catch (PDOException $e) {
            $message = "<p class='message error'>Database error: " . $e->getMessage() . "</p>";
        }
    } else {
        $message = "<p class='message error'>Invalid input. Please select a semester, class, and question paper.</p>";
    }
}

// Fetch data for the dropdowns
try {
    // Fetch all semesters
    $sem_stmt = $pdo->query("SELECT id, name FROM semesters ORDER BY name");
    $semesters = $sem_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all classes and group them by semester_id
    $class_stmt = $pdo->query("SELECT id, name, semester_id FROM classes ORDER BY name");
    $classes_by_semester = [];
    while ($class = $class_stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!isset($classes_by_semester[$class['semester_id']])) {
            $classes_by_semester[$class['semester_id']] = [];
        }
        $classes_by_semester[$class['semester_id']][] = $class;
    }

    // Fetch all question papers
    $qp_stmt = $pdo->query("SELECT id, title FROM question_papers ORDER BY title");
    $question_papers = $qp_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error fetching data: " . $e->getMessage());
}

// Pass the classes data to JavaScript
$classes_json = json_encode($classes_by_semester);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Assign Test to Class</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Reusing styles from admin.php */
        :root { --space-cadet: #2b2d42; --cool-gray: #8d99ae; --antiflash-white: #edf2f4; --red-pantone: #ef233c; --fire-engine-red: #d90429; }
        body { font-family: 'Segoe UI', sans-serif; margin: 0; padding: 20px; background: var(--space-cadet); color: var(--antiflash-white); }
        .back-link { display: block; max-width: 860px; margin: 0 auto 20px auto; text-align: right; font-weight: bold; color: var(--antiflash-white); text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
        .container { max-width: 600px; margin: 20px auto; padding: 30px; background: rgba(141, 153, 174, 0.1); border-radius: 15px; border: 1px solid rgba(141, 153, 174, 0.2); }
        h2 { text-align: center; margin-bottom: 20px; }
        form { display: flex; flex-direction: column; gap: 10px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; }
        select { width: 100%; padding: 10px; margin-bottom: 10px; border-radius: 5px; border: 1px solid var(--cool-gray); background: rgba(43, 45, 66, 0.5); color: var(--antiflash-white); box-sizing: border-box; }
        select:disabled { background: rgba(43, 45, 66, 0.2); color: var(--cool-gray); }
        button { padding: 12px 20px; border: none; border-radius: 5px; background-color: var(--fire-engine-red); color: var(--antiflash-white); font-weight: bold; cursor: pointer; width: 100%; font-size: 1.1em; margin-top: 10px; }
        button:hover { background-color: var(--red-pantone); }
        .message { padding: 10px; border-radius: 5px; margin-bottom: 1em; text-align: center; font-weight: bold; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <a href="/admin" class="back-link">&laquo; Back to Admin Dashboard</a>
    <div class="container">
        <h2>Assign Test to Class</h2>
        <?php if (!empty($message)) echo $message; ?>

        <form action="assign-test.php" method="POST">
            
            <label for="semester_id">Select Semester:</label>
            <select name="semester_id" id="semester_id" required>
                <option value="">-- Select a Semester --</option>
                <?php foreach ($semesters as $semester): ?>
                    <option value="<?= htmlspecialchars($semester['id']) ?>">
                        <?= htmlspecialchars($semester['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="class_id">Select Class:</label>
            <select name="class_id" id="class_id" required disabled>
                <option value="">-- First Select a Semester --</option>
                <!-- This will be populated by JavaScript -->
            </select>

            <label for="qp_id">Select Question Paper:</label>
            <select name="qp_id" id="qp_id" required>
                <option value="">-- Select a Question Paper --</option>
                <?php foreach ($question_papers as $qp): ?>
                    <option value="<?= htmlspecialchars($qp['id']) ?>">
                        <?= htmlspecialchars($qp['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit">Assign Test</button>
        </form>
    </div>

    <script>
        // This JavaScript will filter the classes based on the selected semester
        
        // 1. Get the class data from PHP
        const classesBySemester = <?= $classes_json ?>;

        // 2. Get the dropdown elements
        const semesterSelect = document.getElementById('semester_id');
        const classSelect = document.getElementById('class_id');

        // 3. Add an event listener to the semester dropdown
        semesterSelect.addEventListener('change', function() {
            const selectedSemesterId = this.value;
            
            // Clear the class dropdown
            classSelect.innerHTML = '<option value="">-- Select a Class --</option>';
            
            // Check if a valid semester was chosen
            if (selectedSemesterId && classesBySemester[selectedSemesterId]) {
                // Enable the class dropdown
                classSelect.disabled = false;
                
                // Populate the class dropdown with the correct classes
                classesBySemester[selectedSemesterId].forEach(function(classItem) {
                    const option = document.createElement('option');
                    option.value = classItem.id;
                    option.textContent = classItem.name;
                    classSelect.appendChild(option);
                });
            } else {
                // Disable the class dropdown if no semester is selected
                classSelect.innerHTML = '<option value="">-- First Select a Semester --</option>';
                classSelect.disabled = true;
            }
        });
    </script>
</body>
</html>