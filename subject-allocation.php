<?php
session_start();
// 1. Include the correct PDO database connection
include('db-config.php'); 

// Ensure only admins can access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$message = ""; // To store feedback messages
$message_type = "error"; // Default message type
$staff_members = [];
$available_subjects = [];

try {
    // --- Handle subject allocation on POST request ---
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $staff_id = filter_input(INPUT_POST, 'staff_id', FILTER_VALIDATE_INT);
        // Ensure subject_ids is treated as an array, even if only one is selected
        $subject_ids = isset($_POST['subject_ids']) ? (array)$_POST['subject_ids'] : []; 

        if (!$staff_id || empty($subject_ids)) {
            $message = "Please select a staff member and at least one subject.";
        } else {
            $conn->beginTransaction(); // Start transaction for atomic insertion

            // Prepare statement for checking existing allocation
            $check_sql = "SELECT COUNT(*) FROM subject_allocation WHERE staff_id = ? AND subject_id = ?";
            $check_stmt = $conn->prepare($check_sql);

            // Prepare statement for inserting new allocation
            $insert_sql = "INSERT INTO subject_allocation (staff_id, subject_id) VALUES (?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);

            $allocated_count = 0;
            $skipped_count = 0;

            foreach ($subject_ids as $subject_id_raw) {
                $subject_id = filter_var($subject_id_raw, FILTER_VALIDATE_INT);
                if ($subject_id === false) continue; // Skip invalid subject IDs

                // Check if this allocation already exists
                $check_stmt->execute([$staff_id, $subject_id]);
                if ($check_stmt->fetchColumn() == 0) {
                    // Allocation doesn't exist, insert it
                    if ($insert_stmt->execute([$staff_id, $subject_id])) {
                        $allocated_count++;
                    } else {
                        // If one insertion fails, roll back the entire transaction
                        throw new PDOException("Failed to allocate subject ID: $subject_id");
                    }
                } else {
                    $skipped_count++; // Allocation already exists
                }
            }

            $conn->commit(); // Commit transaction if all insertions were successful

            $message = "Successfully allocated $allocated_count subject(s).";
            if ($skipped_count > 0) {
                $message .= " Skipped $skipped_count already allocated subject(s).";
            }
            $message_type = "success";
        }
    }

    // --- Fetch staff members for the dropdown ---
    // Corrected Case for Enum values 'HOD' and 'principal'
    $staff_stmt = $conn->prepare("SELECT id, first_name, surname FROM users WHERE role IN ('staff', 'HOD', 'principal') ORDER BY first_name, surname"); 
    $staff_stmt->execute();
    $staff_members = $staff_stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- Fetch subjects for the multi-select ---
// Fetch only subjects that are NOT already allocated
$subject_stmt = $conn->prepare("
    SELECT s.id, s.name AS subject_name, s.subject_code, s.branch, s.semester
    FROM subjects s
    WHERE s.id NOT IN (SELECT subject_id FROM subject_allocation)
    ORDER BY s.name
");
$subject_stmt->execute();
$available_subjects = $subject_stmt->fetchAll(PDO::FETCH_ASSOC);


} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack(); // Roll back transaction on error
    }
    // Provide specific error info during development, generic in production
    $message = "Database Error: " . $e->getMessage() . " (Code: " . $e->getCode() . ")";
    // $message = "An error occurred during subject allocation. Please try again."; // Production message
    $message_type = "error";
    
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subject Allocation</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f9; color: #333; }
        .navbar { background-color: #007bff; padding: 1em; display: flex; justify-content: flex-end; gap: 1em; }
        .navbar a { color: #fff; text-decoration: none; padding: 0.5em 1em; border-radius: 5px; transition: background-color 0.3s ease; }
        .navbar a:hover { background-color: #0056b3; }
        .content { padding: 2em; margin: 1em auto; max-width: 800px; background-color: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h2 { text-align: center; margin-bottom: 1.5em; color: #444;}
        form { display: flex; flex-direction: column; gap: 1.2em; }
        form label { font-weight: bold; color: #555; }
        form select { padding: 0.75em; border: 1px solid #ddd; border-radius: 5px; font-size: 1em; width: 100%; box-sizing: border-box; }
        form select[multiple] { height: 200px; /* Make multiple select taller */ }
        form p { font-size: 0.9em; color: #777; margin-top: -0.5em; margin-bottom: 0.5em; }
        form button { background-color: #28a745; color: #fff; border: none; padding: 0.75em 1.5em; border-radius: 5px; cursor: pointer; font-size: 1em; font-weight: bold; transition: background-color 0.3s ease; align-self: center; /* Center button */ width: auto; /* Allow button to size */ }
        form button:hover { background-color: #218838; }
        .message { padding: 15px; margin-bottom: 20px; border-radius: 5px; width: 100%; box-sizing: border-box; text-align: center; font-size: 16px; border: 1px solid transparent;}
        .message.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
         .back-link { display: block; margin-top: 20px; text-align: center; color: #007bff; }
    </style>
</head>
<body>
    <div class="navbar">
        <a href="admin-panel.php">Back to Admin Dashboard</a>
        <a href="logout.php">Logout</a>
    </div>

    <div class="content">
        <h2>Allocate Subjects to Staff</h2>

        <?php 
        // Display feedback message if set
        if (!empty($message)) {
            echo "<div class='message " . htmlspecialchars($message_type) . "'>" . htmlspecialchars($message) . "</div>";
        } 
        ?>

        <form action="subject-allocation.php" method="POST">
            <label for="staff_id">Select Staff Member:</label>
            <select name="staff_id" id="staff_id" required>
                <option value="" disabled selected>-- Select Staff --</option>
                <?php foreach ($staff_members as $staff): ?>
                    <option value="<?= htmlspecialchars($staff['id']) ?>">
                        <?= htmlspecialchars($staff['first_name'] . ' ' . $staff['surname']) ?>
                    </option>
                <?php endforeach; ?>
                <?php if(empty($staff_members)): ?>
                     <option value="" disabled>No staff members found.</option>
                <?php endif; ?>
            </select>
            
            <label for="subject_ids">Select Subjects to Allocate:</label>
            <select name="subject_ids[]" id="subject_ids" multiple required>
                 <?php foreach ($available_subjects as $subject): ?>
                    <option value="<?= htmlspecialchars($subject['id']) ?>">
                        <?= htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name'] . ' (Sem ' . $subject['semester'] . ', ' . $subject['branch'] . ')') ?>
                    </option>
                <?php endforeach; ?>
                 <?php if(empty($available_subjects)): ?>
                     <option value="" disabled>No subjects available to allocate.</option>
                 <?php endif; ?>
            </select>
            <p>Hold Ctrl (or Cmd on Mac) to select multiple subjects.</p>

            <button type="submit">Allocate Selected Subjects</button>
        </form>
         <a href="admin-panel.php" class="back-link">Cancel</a>
    </div>
</body>
</html>



