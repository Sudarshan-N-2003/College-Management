<?php
// Get the database connection URL from Render's environment variables
$database_url = getenv('DATABASE_URL');

if ($database_url === false) {
    // Try falling back to Docker Compose environment variables if DATABASE_URL isn't set
    $db_host = getenv('DB_HOST') ?: 'db'; // Default to 'db' service name in Docker Compose
    $db_port = getenv('DB_PORT') ?: '5432';
    $db_name = getenv('DB_NAME') ?: 'admission_db';
    $db_user = getenv('DB_USER') ?: 'user';
    $db_pass = getenv('DB_PASSWORD') ?: 'password';
    $dsn = "pgsql:host=$db_host;port=$db_port;dbname=$db_name;sslmode=prefer"; // sslmode=prefer for local dev
} else {
    // Parse the connection URL provided by Render/Neon
    $db_parts = parse_url($database_url);
    $host = $db_parts['host'];
    $port = $db_parts['port'] ?? '5432'; // Default port if missing
    $dbname = ltrim($db_parts['path'], '/');
    $user = $db_parts['user'];
    $password = $db_parts['pass'];
    // Construct the DSN, adding the sslmode=require parameter which is essential for Neon
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
}


try {
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // --- Create students table ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS students (
        id SERIAL PRIMARY KEY,
        student_id_text VARCHAR(20) UNIQUE,
        usn VARCHAR(20) UNIQUE,
        student_name VARCHAR(255),
        dob DATE,
        father_name VARCHAR(255),
        mother_name VARCHAR(255),
        mobile_number VARCHAR(20),
        parent_mobile_number VARCHAR(20),
        email VARCHAR(255) UNIQUE,
        password VARCHAR(255),
        permanent_address TEXT,
        previous_college VARCHAR(255),
        previous_combination VARCHAR(50),
        category VARCHAR(50),
        sub_caste VARCHAR(100),
        admission_through VARCHAR(50),
        cet_number VARCHAR(100),
        seat_allotted VARCHAR(100),
        allotted_branch_kea VARCHAR(100),
        allotted_branch_management VARCHAR(100),
        cet_rank VARCHAR(50),
        photo_url TEXT,
        marks_card_url TEXT,
        aadhaar_front_url TEXT,
        aadhaar_back_url TEXT,
        caste_income_url TEXT,
        submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );");

    // Add columns/constraints if they don't exist (safe to run multiple times)
    $pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS password VARCHAR(255);");
    $pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS usn VARCHAR(20);");
    $pdo->exec("ALTER TABLE students ADD CONSTRAINT students_email_unique UNIQUE (email);");
    $pdo->exec("ALTER TABLE students ADD CONSTRAINT students_usn_unique UNIQUE (usn);");

    // --- NEW TABLES FOR TEST ALLOCATION ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS classes (
        id SERIAL PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE
    );");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS question_papers (
        id SERIAL PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS test_allocation (
        id SERIAL PRIMARY KEY,
        class_id INT NOT NULL REFERENCES classes(id) ON DELETE CASCADE,
        qp_id INT NOT NULL REFERENCES question_papers(id) ON DELETE CASCADE,
        UNIQUE(class_id, qp_id)
    );");
    
    // --- (You can add other tables like users, subjects, etc. here) ---


} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>