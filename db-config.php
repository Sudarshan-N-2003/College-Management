<?php
// db-config.php (Updated for PostgreSQL on Render/Neon)

// Fetch database credentials from environment variables
$host = getenv('DB_HOST');
$port = getenv('DB_PORT');
$database = getenv('DB_NAME');
$username = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$sslmode = "require"; // Always required for Neon or Render

// --- Validate required variables ---
if (empty($host) || empty($port) || empty($database) || empty($username) || empty($password)) {
    die("❌ Database connection error: Missing environment variables.");
}

// --- Create DSN (Data Source Name) ---
$dsn = "pgsql:host=$host;port=$port;dbname=$database;sslmode=$sslmode";

try {
    // --- Initialize PDO Connection ---
    $conn = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Return associative arrays
        PDO::ATTR_EMULATE_PREPARES => false, // Use native prepared statements
    ]);

    // --- Auto rollback if previous transaction failed ---
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
} catch (PDOException $e) {
    // --- Log the error to a file for debugging ---
    $error_message = "[" . date("Y-m-d H:i:s") . "] Database Connection Error: " . $e->getMessage() . "\n";
    file_put_contents(__DIR__ . '/error_log.txt', $error_message, FILE_APPEND);

    // --- Display generic message to user ---
    die("Connection failed: " . htmlspecialchars($e->getMessage()));
}

// ✅ $conn is now your working database connection
?>
