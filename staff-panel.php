<?php
session_start();
// 1. Include the correct PDO database connection
include('db-config.php');

// Ensure only staff members can access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit;
}

// Get the logged-in staff member's ID
if (!isset($_SESSION['user_id'])) {
    // If user_id isn't set, something is wrong with the login process
    header("Location: login.php?error=session_invalid");
    exit;
}
$staff_id = $_SESSION['user_id'];

$question_papers = []; // Initialize array for papers

try {
    // 2. Prepare the query to fetch question papers created BY THIS staff member
    // We join with the subjects table to get the subject name/code if needed later
    $sql = "SELECT qp.id, qp.title, s.subject_code, s.name as subject_name
            FROM question_papers qp
            JOIN subjects s ON qp.subject_id = s.id
            WHERE qp.staff_id = ?
            ORDER BY qp.created_at DESC";

    $stmt = $conn->prepare($sql);

    // 3. Execute the query with the staff_id
    $stmt->execute([$staff_id]);

    // 4. Fetch all results
    $question_papers = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Handle database errors gracefully
    die("Database error: Could not retrieve question papers. " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard</title>
    <style>
        /* Consistent Styling from other dashboards */
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f9; color: #333; }
        .navbar { background-color: #007bff; padding: 1em; display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 1em; } /* Added flex-wrap */
        .navbar a { color: #fff; text-decoration: none; padding: 0.5em 1em; border-radius: 5px; transition: background-color 0.3s ease; white-space: nowrap; } /* Added nowrap */
        .navbar a:hover { background-color: #0056b3; }
        .container { width: 90%; max-width: 1000px; margin: 20px auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h2, h3 { color: #444; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 1em; }
        th, td { border: 1px solid #ddd; padding: 0.75em; text-align: left; }
        th { background-color: #007bff; color: #fff; }
        .action-btn {
            text-decoration: none;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 14px;
            color: white;
            margin-right: 5px;
            display: inline-block;
            transition: opacity 0.3s;
        }
        .action-btn:hover { opacity: 0.8; }
        .edit-btn { background-color: #28a745; }
        .delete-btn { background-color: #dc3545; }
        .view-btn { background-color: #17a2b8; } /* Added view button style */
        .no-records { text-align: center; color: #777; padding: 15px; font-style: italic; }
         /* Responsive Table (Optional but recommended) */
        @media screen and (max-width: 600px) {
             table, thead, tbody, th, td, tr { display: block; }
             thead tr { position: absolute; top: -9999px; left: -9999px; }
             tr { border: 1px solid #ccc; margin-bottom: 5px; }
             td { border: none; border-bottom: 1px solid #eee; position: relative; padding-left: 50%; white-space: normal; text-align:right; }
             td:before { position: absolute; top: 6px; left: 6px; width: 45%; padding-right: 10px; white-space: nowrap; text-align:left; font-weight: bold; }
             /* Label the data */
             td:nth-of-type(1):before { content: "Title"; }
             td:nth-of-type(2):before { content: "Subject"; }
             td:nth-of-type(3):before { content: "Actions"; }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <a href="create-question.php">Create Question Paper</a>
        <a href="assign-test.php">Assign Test</a>
        <a href="correct-test.php">Correct Tests</a>
        <!-- Add links relevant for staff as features are built -->
        <!-- <a href="view-timetable.php">View Timetables</a> -->
        <a href="enter-attendance-daily.php">Enter Attendance</a>
       <!--  <a href="enter-results.php">Enter IA Marks</a> -->
        <a href="logout.php">Logout</a>
    </div>

    <div class="container">
        <h2>Staff Dashboard</h2>
        <p>Welcome, <?= htmlspecialchars($_SESSION['email'] ?? 'Staff Member') ?>! Manage your question papers below.</p>

        <h3>Your Created Question Papers</h3>
        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Subject</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($question_papers)): ?>
                    <tr>
                        <td colspan="3" class="no-records">You have not created any question papers yet. <a href="generate-paper.php">Create one now</a>.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($question_papers as $paper): ?>
                        <tr>
                            <td><?= htmlspecialchars($paper['title']) ?></td>
                            <td><?= htmlspecialchars($paper['subject_name'] . ' (' . $paper['subject_code'] . ')') ?></td>
                            <td>
                                <!-- TODO: Create these pages -->
                                <a href="view-paper.php?id=<?= $paper['id'] ?>" class="action-btn view-btn">View</a>
                                <a href="edit-paper.php?id=<?= $paper['id'] ?>" class="action-btn edit-btn">Edit</a>
                                <a href="delete-paper.php?id=<?= $paper['id'] ?>" class="action-btn delete-btn" onclick="return confirm('Are you sure you want to delete this paper?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</body>
</html>

