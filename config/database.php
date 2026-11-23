<?php
/**
 * Database Connection Manager (PDO)
 * Supports MySQL / MariaDB (LAMP) with seamless SQLite fallback if MySQL is offline.
 */

require_once __DIR__ . '/app.php';

class Database {
    private static ?PDO $instance = null;
    private static string $driver = 'mysql';

    public static function getConnection(): PDO {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $mysqlHost = '127.0.0.1';
        $mysqlPort = '3306';
        $mysqlUser = 'root';
        $mysqlPass = '';
        $dbName    = 'intern_management_db';

        // Attempt MySQL connection
        try {
            // First connect without db to check/create database
            $pdoRoot = new PDO("mysql:host={$mysqlHost};port={$mysqlPort};charset=utf8mb4", $mysqlUser, $mysqlPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 2
            ]);

            $pdoRoot->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // Connect to the specific database
            $pdo = new PDO("mysql:host={$mysqlHost};port={$mysqlPort};dbname={$dbName};charset=utf8mb4", $mysqlUser, $mysqlPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);

            self::$driver = 'mysql';
            self::$instance = $pdo;
            self::checkAndMigrateMySQL($pdo);
            return self::$instance;

        } catch (PDOException $e) {
            // MySQL is not reachable or not started, gracefully fallback to SQLite so app is 100% usable!
            $sqlitePath = dirname(__DIR__) . '/data/intern_management.sqlite';
            $isNewDb = !file_exists($sqlitePath) || filesize($sqlitePath) === 0;

            $pdo = new PDO("sqlite:" . $sqlitePath, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);

            // Enable foreign keys in SQLite
            $pdo->exec("PRAGMA foreign_keys = ON;");

            self::$driver = 'sqlite';
            self::$instance = $pdo;

            if ($isNewDb) {
                self::migrateSQLite($pdo);
            }
            return self::$instance;
        }
    }

    public static function getDriver(): string {
        return self::$driver;
    }

    private static function checkAndMigrateMySQL(PDO $pdo): void {
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        if ($stmt->rowCount() === 0) {
            $schemaFile = dirname(__DIR__) . '/sql/schema.sql';
            $seedFile   = dirname(__DIR__) . '/sql/seed.sql';

            if (file_exists($schemaFile)) {
                $sql = file_get_contents($schemaFile);
                $pdo->exec($sql);
            }
            if (file_exists($seedFile)) {
                $sql = file_get_contents($seedFile);
                $pdo->exec($sql);
            }
        }
    }

    private static function migrateSQLite(PDO $pdo): void {
        // SQLite compatible schema definition
        $sqliteSchema = "
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            intern_id TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            plain_temp_password TEXT NULL,
            role TEXT NOT NULL DEFAULT 'intern',
            department TEXT DEFAULT 'Software Engineering',
            country TEXT DEFAULT 'United States',
            timezone TEXT NOT NULL DEFAULT 'UTC',
            status TEXT NOT NULL DEFAULT 'active',
            last_activity DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS attendance_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            login_time DATETIME NOT NULL,
            logout_time DATETIME NULL,
            session_minutes INTEGER DEFAULT 0,
            ip_address TEXT NULL,
            user_agent TEXT NULL,
            status TEXT DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS tasks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT NULL,
            assigned_to INTEGER NOT NULL,
            created_by INTEGER NOT NULL,
            priority TEXT DEFAULT 'medium',
            status TEXT DEFAULT 'pending',
            progress_percent INTEGER DEFAULT 0,
            due_date DATE NULL,
            notes TEXT NULL,
            completed_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS difficulties (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            task_id INTEGER NULL,
            title TEXT NOT NULL,
            description TEXT NOT NULL,
            severity TEXT DEFAULT 'medium',
            status TEXT DEFAULT 'open',
            hr_response TEXT NULL,
            resolved_by INTEGER NULL,
            resolved_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL,
            FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
        );

        CREATE TABLE IF NOT EXISTS intern_availability (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            day_of_week INTEGER NOT NULL,
            start_time TEXT NOT NULL,
            end_time TEXT NOT NULL,
            timezone TEXT NOT NULL,
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS meetings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT NULL,
            meeting_date DATE NOT NULL,
            start_time_hr TEXT NOT NULL,
            end_time_hr TEXT NOT NULL,
            start_utc DATETIME NOT NULL,
            end_utc DATETIME NOT NULL,
            meeting_link TEXT NULL,
            created_by INTEGER NOT NULL,
            status TEXT DEFAULT 'scheduled',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS meeting_attendees (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            meeting_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            local_start_time TEXT NULL,
            attendance_status TEXT DEFAULT 'pending',
            FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
        ";

        $pdo->exec($sqliteSchema);

        // Seed default users in SQLite
        $seedSql = "
        INSERT OR IGNORE INTO users (id, intern_id, name, email, password, plain_temp_password, role, department, country, timezone, status) VALUES
        (1, 'HR-ADMIN', 'Sarah Jenkins (HR Lead)', 'hr@internhub.com', '$2y$10$FrECgeTMbNsWM6feLI7RruBLhvv.werkCDi2UGcbtud9ZJ/6V1ZG2', 'Password123!', 'hr', 'Talent Operations', 'United States', 'America/New_York', 'active'),
        (2, 'INT-2026-001', 'Alex Rivera', 'alex@internhub.com', '$2y$10$FrECgeTMbNsWM6feLI7RruBLhvv.werkCDi2UGcbtud9ZJ/6V1ZG2', 'Password123!', 'intern', 'Frontend Engineering', 'United States', 'America/New_York', 'active'),
        (3, 'INT-2026-002', 'Sofia Bennett', 'sofia@internhub.com', '$2y$10$FrECgeTMbNsWM6feLI7RruBLhvv.werkCDi2UGcbtud9ZJ/6V1ZG2', 'Password123!', 'intern', 'Backend Engineering', 'United Kingdom', 'Europe/London', 'active'),
        (4, 'INT-2026-003', 'Aarav Patel', 'aarav@internhub.com', '$2y$10$FrECgeTMbNsWM6feLI7RruBLhvv.werkCDi2UGcbtud9ZJ/6V1ZG2', 'Password123!', 'intern', 'Cloud & DevOps', 'India', 'Asia/Kolkata', 'active'),
        (5, 'INT-2026-004', 'Kenji Sato', 'kenji@internhub.com', '$2y$10$FrECgeTMbNsWM6feLI7RruBLhvv.werkCDi2UGcbtud9ZJ/6V1ZG2', 'Password123!', 'intern', 'Data & AI', 'Japan', 'Asia/Tokyo', 'active');

        -- Seed Tasks
        INSERT OR IGNORE INTO tasks (id, title, description, assigned_to, created_by, priority, status, progress_percent, due_date, notes) VALUES
        (1, 'Implement OAuth2 Login Screen', 'Create a responsive login UI with Tailwind CSS and validate credentials against LDAP/Database.', 2, 1, 'high', 'in_progress', 65, date('now', '+3 days'), 'Mockups approved, working on form validation.'),
        (2, 'Optimize MySQL Query Indexing', 'Analyze slow query logs on the reporting tables and add composite indexes where necessary.', 3, 1, 'urgent', 'in_progress', 40, date('now', '+2 days'), 'Execution plan shows full table scan on analytics table.'),
        (3, 'Setup Docker Compose for Local Dev', 'Write docker-compose.yml including Apache, PHP 8.2, MySQL 8.0, and phpMyAdmin containers.', 4, 1, 'medium', 'completed', 100, date('now', '-1 day'), 'Docker container configuration verified and pushed to repo.'),
        (4, 'Build Intern Heatmap Visualization', 'Generate dynamic SVG/Canvas matrix showing availability overlaps across timezones.', 2, 1, 'medium', 'pending', 0, date('now', '+5 days'), 'Waiting for timezone API contract specification.'),
        (5, 'Train Text Summarization Model', 'Fine-tune small BERT/LLM model for automatic ticket summarization in HR portal.', 5, 1, 'high', 'in_progress', 50, date('now', '+4 days'), 'Dataset tokenized; training epoch 2/5 in progress.');

        -- Seed Difficulties
        INSERT OR IGNORE INTO difficulties (id, user_id, task_id, title, description, severity, status, hr_response, resolved_by) VALUES
        (1, 3, 2, 'Blocked by database production replica permissions', 'I do not have read access to the production replica query logs needed to test the explain plan.', 'high', 'in_review', 'Requested temporary staging replica access from DevOps. Will ping you once ready.', 1),
        (2, 2, 4, 'Timezone ambiguity during Daylight Savings transition', 'Need clarification on whether to store user windows as standard UTC offsets or IANA timezone strings like America/New_York.', 'medium', 'resolved', 'Always store IANA timezone strings to automatically handle DST changes safely!', 1);

        -- Seed Availability Windows (Mon-Fri)
        -- Alex (New York)
        INSERT OR IGNORE INTO intern_availability (user_id, day_of_week, start_time, end_time, timezone) VALUES
        (2, 1, '09:00', '13:00', 'America/New_York'), (2, 1, '14:00', '17:00', 'America/New_York'),
        (2, 2, '09:00', '13:00', 'America/New_York'), (2, 2, '14:00', '17:00', 'America/New_York'),
        (2, 3, '09:00', '13:00', 'America/New_York'), (2, 3, '14:00', '17:00', 'America/New_York'),
        (2, 4, '09:00', '13:00', 'America/New_York'), (2, 4, '14:00', '17:00', 'America/New_York'),
        (2, 5, '09:00', '13:00', 'America/New_York'), (2, 5, '14:00', '17:00', 'America/New_York');

        -- Sofia (London)
        INSERT OR IGNORE INTO intern_availability (user_id, day_of_week, start_time, end_time, timezone) VALUES
        (3, 1, '10:00', '17:00', 'Europe/London'), (3, 2, '10:00', '17:00', 'Europe/London'),
        (3, 3, '10:00', '17:00', 'Europe/London'), (3, 4, '10:00', '17:00', 'Europe/London'),
        (3, 5, '10:00', '17:00', 'Europe/London');

        -- Aarav (India)
        INSERT OR IGNORE INTO intern_availability (user_id, day_of_week, start_time, end_time, timezone) VALUES
        (4, 1, '13:00', '21:00', 'Asia/Kolkata'), (4, 2, '13:00', '21:00', 'Asia/Kolkata'),
        (4, 3, '13:00', '21:00', 'Asia/Kolkata'), (4, 4, '13:00', '21:00', 'Asia/Kolkata'),
        (4, 5, '13:00', '21:00', 'Asia/Kolkata');

        -- Kenji (Japan)
        INSERT OR IGNORE INTO intern_availability (user_id, day_of_week, start_time, end_time, timezone) VALUES
        (5, 1, '18:00', '23:30', 'Asia/Tokyo'), (5, 2, '18:00', '23:30', 'Asia/Tokyo'),
        (5, 3, '18:00', '23:30', 'Asia/Tokyo'), (5, 4, '18:00', '23:30', 'Asia/Tokyo'),
        (5, 5, '18:00', '23:30', 'Asia/Tokyo');
        ";

        $pdo->exec($seedSql);
    }
}

function getDB(): PDO {
    return Database::getConnection();
}
