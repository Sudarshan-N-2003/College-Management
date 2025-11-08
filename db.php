<?php
/**
 * db.php
 * Database connection + automatic migrations
 * This version adds a default admin user.
 */

// ... (all existing connection code at the top) ...
$database_url = getenv('DATABASE_URL');
if ($database_url === false) {
    $host = getenv('DB_HOST') ?: 'db';
    $port = getenv('DB_PORT') ?: '5432';
    $dbname = getenv('DB_NAME') ?: 'admission_db';
    $user = getenv('DB_USER') ?: 'user';
    $password = getenv('DB_PASSWORD') ?: 'password';
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=prefer";
} else {
    $db_parts = parse_url($database_url);
    $host = $db_parts['host'];
    $port = $db_parts['port'] ?? '5432';
    $dbname = ltrim($db_parts['path'], '/');
    $user = $db_parts['user'];
    $password = $db_parts['pass'];
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
}

function run_migration(PDO $pdo, string $migration_id, string $sql) {
    // ... (existing run_migration function) ...
    $stmt = $pdo->prepare("SELECT 1 FROM db_migrations WHERE migration_id = ?");
    $stmt->execute([$migration_id]);
    if ($stmt->fetch() !== false) { return; }
    try {
        $pdo->beginTransaction();
        $pdo->exec($sql);
        $log_stmt = $pdo->prepare("INSERT INTO db_migrations (migration_id) VALUES (?)");
        $log_stmt->execute([$migration_id]);
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $safe_error_codes = ['42P07', '42701', '23505', '42710'];
        if (in_array($e->getCode(), $safe_error_codes)) {
            $log_stmt = $pdo->prepare("INSERT INTO db_migrations (migration_id) VALUES (?) ON CONFLICT (migration_id) DO NOTHING");
            $log_stmt->execute([$migration_id]);
        } else {
            throw $e;
        }
    }
}

try {
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // --- 1. CREATE THE MIGRATIONS TABLE ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS db_migrations (migration_id VARCHAR(255) PRIMARY KEY, run_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);");

    // --- 2. RUN ALL MIGRATIONS ---
    // ... (all your existing run_migration calls for tables and columns) ...
    run_migration($pdo, 'create_table_students', "CREATE TABLE IF NOT EXISTS students (id SERIAL PRIMARY KEY, student_id_text VARCHAR(20) UNIQUE);");
    run_migration($pdo, 'create_table_users', "CREATE TABLE IF NOT EXISTS users (id SERIAL PRIMARY KEY, first_name VARCHAR(100), surname VARCHAR(100), email VARCHAR(255) UNIQUE NOT NULL, password VARCHAR(255) NOT NULL, role VARCHAR(20) NOT NULL DEFAULT 'student');");
    // ... (all other tables) ...
    run_migration($pdo, 'add_students_columns_batch_1', "ALTER TABLE students ADD COLUMN IF NOT EXISTS usn VARCHAR(20) UNIQUE, ADD COLUMN IF NOT EXISTS student_name VARCHAR(255), ADD COLUMN IF NOT EXISTS dob DATE, ADD COLUMN IF NOT EXISTS father_name VARCHAR(255), ADD COLUMN IF NOT EXISTS mother_name VARCHAR(255), ADD COLUMN IF NOT EXISTS mobile_number VARCHAR(20), ADD COLUMN IF NOT EXISTS parent_mobile_number VARCHAR(20), ADD COLUMN IF NOT EXISTS email VARCHAR(255) UNIQUE, ADD COLUMN IF NOT EXISTS password VARCHAR(255), ADD COLUMN IF NOT EXISTS permanent_address TEXT, ADD COLUMN IF NOT EXISTS previous_college VARCHAR(255), ADD COLUMN IF NOT EXISTS previous_combination VARCHAR(50), ADD COLUMN IF NOT EXISTS category VARCHAR(50), ADD COLUMN IF NOT EXISTS sub_caste VARCHAR(100), ADD COLUMN IF NOT EXISTS admission_through VARCHAR(50), ADD COLUMN IF NOT EXISTS cet_number VARCHAR(100), ADD COLUMN IF NOT EXISTS seat_allotted VARCHAR(100), ADD COLUMN IF NOT EXISTS allotted_branch_kea VARCHAR(100), ADD COLUMN IF NOT EXISTS allotted_branch_management VARCHAR(100), ADD COLUMN IF NOT EXISTS cet_rank VARCHAR(50), ADD COLUMN IF NOT EXISTS photo_url TEXT, ADD COLUMN IF NOT EXISTS marks_card_url TEXT, ADD COLUMN IF NOT EXISTS aadhaar_front_url TEXT, ADD COLUMN IF NOT EXISTS aadhaar_back_url TEXT, ADD COLUMN IF NOT EXISTS caste_income_url TEXT, ADD COLUMN IF NOT EXISTS submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP, ADD COLUMN IF NOT EXISTS class_id INT, ADD COLUMN IF NOT EXISTS semester INT, ADD COLUMN IF NOT EXISTS section VARCHAR(10);");
    // ... (all other ALTER TABLE migrations) ...
    run_migration($pdo, 'add_constraint_subjects_subject_code_unique', "ALTER TABLE subjects ADD CONSTRAINT subjects_subject_code_key UNIQUE (subject_code);");

    // --- NEW MIGRATION: Add a default admin user ---
    $admin_email = 'admin@vvit.ac.in';
    $admin_pass = password_hash('admin123', PASSWORD_DEFAULT);
    $admin_sql = "
        INSERT INTO users (first_name, surname, email, password, role) 
        VALUES ('Admin', 'User', '$admin_email', '$admin_pass', 'admin')
        ON CONFLICT (email) DO NOTHING;
    ";
    run_migration($pdo, 'seed_default_admin', $admin_sql);
    // --- END NEW MIGRATION ---
    
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>