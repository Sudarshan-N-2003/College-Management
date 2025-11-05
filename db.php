<?php
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
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM db_migrations WHERE migration_id = ?");
        $stmt->execute([$migration_id]);

        if ($stmt->fetch() === false) {
            $pdo->exec($sql);
            $log_stmt = $pdo->prepare("INSERT INTO db_migrations (migration_id) VALUES (?)");
            $log_stmt->execute([$migration_id]);
        }
    } catch (PDOException $e) {
        if (in_array($e->getCode(), ['42P07', '42701'])) { // duplicate_table or duplicate_column
            $log_stmt = $pdo->prepare("INSERT INTO db_migrations (migration_id) VALUES (?) ON CONFLICT (migration_id) DO NOTHING");
            $log_stmt->execute([$migration_id]);
        } else {
            die("Migration failed ($migration_id): " . $e->getMessage());
        }
    }
}

try {
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Migrations table
    $pdo->exec("CREATE TABLE IF NOT EXISTS db_migrations (
        migration_id VARCHAR(255) PRIMARY KEY,
        run_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );");

    // Core tables
    $pdo->exec("CREATE TABLE IF NOT EXISTS students (
        id SERIAL PRIMARY KEY,
        student_id_text VARCHAR(20) UNIQUE
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        first_name VARCHAR(100),
        surname VARCHAR(100),
        email VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'student'
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS semesters (
        id SERIAL PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS classes (
        id SERIAL PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS subjects (
        id SERIAL PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        subject_code VARCHAR(20) UNIQUE NOT NULL
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS subject_allocation (
        id SERIAL PRIMARY KEY,
        staff_id INT NOT NULL,
        subject_id INT NOT NULL,
        UNIQUE(staff_id, subject_id)
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS question_papers (
        id SERIAL PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS test_allocation (
        id SERIAL PRIMARY KEY,
        class_id INT NOT NULL,
        qp_id INT NOT NULL,
        UNIQUE(class_id, qp_id)
    );");

    // --- Students Table Enhancements ---
    run_migration($pdo, 'add_students_usn', "ALTER TABLE students ADD COLUMN IF NOT EXISTS usn VARCHAR(20);");
    run_migration($pdo, 'add_students_student_name', "ALTER TABLE students ADD COLUMN IF NOT EXISTS student_name VARCHAR(255);");
    run_migration($pdo, 'add_students_dob', "ALTER TABLE students ADD COLUMN IF NOT EXISTS dob DATE;");
    run_migration($pdo, 'add_students_email', "ALTER TABLE students ADD COLUMN IF NOT EXISTS email VARCHAR(255);");
    run_migration($pdo, 'add_students_class_id', "ALTER TABLE students ADD COLUMN IF NOT EXISTS class_id INT;");
    run_migration($pdo, 'add_students_semester_id', "ALTER TABLE students ADD COLUMN IF NOT EXISTS semester_id INT;");
    run_migration($pdo, 'add_students_allotted_branch_kea', "ALTER TABLE students ADD COLUMN IF NOT EXISTS allotted_branch_kea VARCHAR(100);");
    run_migration($pdo, 'add_students_allotted_branch_mgmt', "ALTER TABLE students ADD COLUMN IF NOT EXISTS allotted_branch_management VARCHAR(100);");

    // --- Subjects Table Enhancements ---
    run_migration($pdo, 'add_subjects_semester_id', "ALTER TABLE subjects ADD COLUMN IF NOT EXISTS semester_id INT;");

    // --- Other Enhancements ---
    run_migration($pdo, 'add_classes_semester_id', "ALTER TABLE classes ADD COLUMN IF NOT EXISTS semester_id INT;");
    run_migration($pdo, 'add_qp_subject_id', "ALTER TABLE question_papers ADD COLUMN IF NOT EXISTS subject_id INT;");

    // --- Foreign Keys ---
    run_migration($pdo, 'fk_classes_semester', "ALTER TABLE classes ADD CONSTRAINT fk_classes_semester FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE SET NULL;");
    run_migration($pdo, 'fk_students_class', "ALTER TABLE students ADD CONSTRAINT fk_students_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL;");
    run_migration($pdo, 'fk_students_semester', "ALTER TABLE students ADD CONSTRAINT fk_students_semester FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE SET NULL;");
    run_migration($pdo, 'fk_subjects_semester', "ALTER TABLE subjects ADD CONSTRAINT fk_subjects_semester FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE SET NULL;");

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>