<?php


// ... (existing connection code) ...
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
    // ... (existing migration function) ...
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

    // --- 1. CREATE THE MIGRATIONS TABLE (outside a transaction) ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS db_migrations (
        migration_id VARCHAR(255) PRIMARY KEY,
        run_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );");

    // --- 2. RUN ALL MIGRATIONS ---
    // ... (all your existing create table migrations) ...
    run_migration($pdo, 'create_table_students', "CREATE TABLE IF NOT EXISTS students (id SERIAL PRIMARY KEY, student_id_text VARCHAR(20) UNIQUE);");
    run_migration($pdo, 'create_table_users', "CREATE TABLE IF NOT EXISTS users (id SERIAL PRIMARY KEY, first_name VARCHAR(100), surname VARCHAR(100), email VARCHAR(255) UNIQUE NOT NULL, password VARCHAR(255) NOT NULL, role VARCHAR(20) NOT NULL DEFAULT 'student');");
    run_migration($pdo, 'create_table_semesters', "CREATE TABLE IF NOT EXISTS semesters (id SERIAL PRIMARY KEY, name VARCHAR(100) NOT NULL UNIQUE);");
    run_migration($pdo, 'create_table_classes', "CREATE TABLE IF NOT EXISTS classes (id SERIAL PRIMARY KEY, name VARCHAR(100) NOT NULL UNIQUE, semester_id INT);");
    run_migration($pdo, 'create_table_subjects', "CREATE TABLE IF NOT EXISTS subjects (id SERIAL PRIMARY KEY, name VARCHAR(100) NOT NULL, subject_code VARCHAR(20) NOT NULL);");
    run_migration($pdo, 'create_table_subject_allocation', "CREATE TABLE IF NOT EXISTS subject_allocation (id SERIAL PRIMARY KEY, staff_id INT NOT NULL, subject_id INT NOT NULL);"); // Removed old UNIQUE constraint
    run_migration($pdo, 'create_table_question_papers', "CREATE TABLE IF NOT EXISTS question_papers (id SERIAL PRIMARY KEY, title VARCHAR(255) NOT NULL, content TEXT);");
    run_migration($pdo, 'create_table_test_allocation', "CREATE TABLE IF NOT EXISTS test_allocation (id SERIAL PRIMARY KEY, class_id INT NOT NULL, qp_id INT NOT NULL, UNIQUE(class_id, qp_id));");
    run_migration($pdo, 'create_table_attendance', "CREATE TABLE IF NOT EXISTS attendance (id SERIAL PRIMARY KEY, student_id INT, status VARCHAR(20));");
    run_migration($pdo, 'create_table_ia_results', "CREATE TABLE IF NOT EXISTS ia_results (id SERIAL PRIMARY KEY, student_id INT, marks INT);");
    run_migration($pdo, 'create_table_daily_attendance', "CREATE TABLE IF NOT EXISTS daily_attendance (id SERIAL PRIMARY KEY, student_id INT, date DATE);");
    run_migration($pdo, 'create_table_student_subject_allocation', "CREATE TABLE IF NOT EXISTS student_subject_allocation (id SERIAL PRIMARY KEY, student_id INT NOT NULL REFERENCES students(id) ON DELETE CASCADE, subject_id INT NOT NULL REFERENCES subjects(id) ON DELETE CASCADE, UNIQUE(student_id, subject_id));");

    // ... (all your existing ALTER TABLE... ADD COLUMN migrations) ...
    run_migration($pdo, 'add_students_columns_batch_1', "ALTER TABLE students ADD COLUMN IF NOT EXISTS usn VARCHAR(20) UNIQUE, ADD COLUMN IF NOT EXISTS student_name VARCHAR(255), ADD COLUMN IF NOT EXISTS dob DATE, ADD COLUMN IF NOT EXISTS father_name VARCHAR(255), ADD COLUMN IF NOT EXISTS mother_name VARCHAR(255), ADD COLUMN IF NOT EXISTS mobile_number VARCHAR(20), ADD COLUMN IF NOT EXISTS parent_mobile_number VARCHAR(20), ADD COLUMN IF NOT EXISTS email VARCHAR(255) UNIQUE, ADD COLUMN IF NOT EXISTS password VARCHAR(255), ADD COLUMN IF NOT EXISTS permanent_address TEXT, ADD COLUMN IF NOT EXISTS previous_college VARCHAR(255), ADD COLUMN IF NOT EXISTS previous_combination VARCHAR(50), ADD COLUMN IF NOT EXISTS category VARCHAR(50), ADD COLUMN IF NOT EXISTS sub_caste VARCHAR(100), ADD COLUMN IF NOT EXISTS admission_through VARCHAR(50), ADD COLUMN IF NOT EXISTS cet_number VARCHAR(100), ADD COLUMN IF NOT EXISTS seat_allotted VARCHAR(100), ADD COLUMN IF NOT EXISTS allotted_branch_kea VARCHAR(100), ADD COLUMN IF NOT EXISTS allotted_branch_management VARCHAR(100), ADD COLUMN IF NOT EXISTS cet_rank VARCHAR(50), ADD COLUMN IF NOT EXISTS photo_url TEXT, ADD COLUMN IF NOT EXISTS marks_card_url TEXT, ADD COLUMN IF NOT EXISTS aadhaar_front_url TEXT, ADD COLUMN IF NOT EXISTS aadhaar_back_url TEXT, ADD COLUMN IF NOT EXISTS caste_income_url TEXT, ADD COLUMN IF NOT EXISTS submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP, ADD COLUMN IF NOT EXISTS class_id INT, ADD COLUMN IF NOT EXISTS semester INT, ADD COLUMN IF NOT EXISTS section VARCHAR(10);");
    run_migration($pdo, 'add_classes_semester_id', "ALTER TABLE classes ADD COLUMN IF NOT EXISTS semester_id INT;");
    run_migration($pdo, 'add_qp_subject_id', "ALTER TABLE question_papers ADD COLUMN IF NOT EXISTS subject_id INT;");
    run_migration($pdo, 'add_subjects_semester_id', "ALTER TABLE subjects ADD COLUMN IF NOT EXISTS semester_id INT;"); 
    run_migration($pdo, 'add_subjects_branch', "ALTER TABLE subjects ADD COLUMN IF NOT EXISTS branch VARCHAR(100);");
    run_migration($pdo, 'add_subjects_year', "ALTER TABLE subjects ADD COLUMN IF NOT EXISTS year INT;");

    // --- NEW MIGRATIONS FOR SUBJECT ALLOCATION ---
    // 1. Drop the old, simple UNIQUE constraint if it exists
    run_migration($pdo, 'drop_old_subject_allocation_constraint', "
        ALTER TABLE subject_allocation DROP CONSTRAINT IF EXISTS subject_allocation_staff_id_subject_id_key;
    ");
    // 2. Add the new class_id column
    run_migration($pdo, 'add_class_id_to_subject_allocation', "
        ALTER TABLE subject_allocation ADD COLUMN IF NOT EXISTS class_id INT;
    ");
    // 3. Add the new, more complex UNIQUE constraint
    run_migration($pdo, 'add_new_subject_allocation_constraint', "
        ALTER TABLE subject_allocation ADD CONSTRAINT subject_allocation_staff_subject_class_key UNIQUE (staff_id, subject_id, class_id);
    ");
    // --- END NEW MIGRATIONS ---

    // ... (all your other constraint and seeding migrations) ...
    run_migration($pdo, 'seed_semesters', "INSERT INTO semesters (name) VALUES ('Semester 1'), ('Semester 2'), ('Semester 3'), ('Semester 4'), ('Semester 5'), ('Semester 6'), ('Semester 7'), ('Semester 8') ON CONFLICT (name) DO NOTHING;");
    run_migration($pdo, 'add_constraint_students_email_unique', "ALTER TABLE students ADD CONSTRAINT students_email_unique UNIQUE (email);");
    run_migration($pdo, 'add_constraint_students_usn_unique', "ALTER TABLE students ADD CONSTRAINT students_usn_unique UNIQUE (usn);");
    run_migration($pdo, 'add_constraint_subjects_subject_code_unique', "ALTER TABLE subjects ADD CONSTRAINT subjects_subject_code_key UNIQUE (subject_code);");
    
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
