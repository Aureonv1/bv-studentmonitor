<?php
// Database configuration
$host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'student_marks_portal';

if (!function_exists('create_pdo_connection')) {
    function create_pdo_connection(string $dsn, string $user, string $pass): PDO
    {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        return $pdo;
    }
}

if (!function_exists('ensure_schema')) {
    function ensure_schema(PDO $pdo): void
    {
        static $schemaEnsured = false;
        if ($schemaEnsured) {
            return;
        }

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS admins (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL UNIQUE,
                full_name VARCHAR(100) NULL,
                password_hash VARCHAR(255) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                can_manage_students TINYINT(1) NOT NULL DEFAULT 1,
                can_manage_marks TINYINT(1) NOT NULL DEFAULT 1,
                can_import_csv TINYINT(1) NOT NULL DEFAULT 1,
                can_backup_db TINYINT(1) NOT NULL DEFAULT 1,
                can_maintenance_mode TINYINT(1) NOT NULL DEFAULT 1,
                can_manage_admins TINYINT(1) NOT NULL DEFAULT 1,
                can_manage_site_settings TINYINT(1) NOT NULL DEFAULT 1,
                can_view_analytics TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS site_settings (
                id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
                footer_text VARCHAR(255) NOT NULL DEFAULT 'ONYX',
                footer_url VARCHAR(255) NOT NULL DEFAULT 'https://onyxrns.com',
                footer_brand_name VARCHAR(140) NOT NULL DEFAULT 'BV-BrightVision',
                footer_copyright_owner VARCHAR(140) NOT NULL DEFAULT 'BV-BrightVision',
                footer_rights_text VARCHAR(160) NOT NULL DEFAULT 'All rights reserved.',
                footer_collab_text VARCHAR(255) NOT NULL DEFAULT 'Collaborated with apexinventives',
                footer_link_1_label VARCHAR(50) NOT NULL DEFAULT 'Privacy',
                footer_link_1_url VARCHAR(255) NOT NULL DEFAULT '#',
                footer_link_2_label VARCHAR(50) NOT NULL DEFAULT 'Terms',
                footer_link_2_url VARCHAR(255) NOT NULL DEFAULT '#',
                footer_link_3_label VARCHAR(50) NOT NULL DEFAULT 'Contact',
                footer_link_3_url VARCHAR(255) NOT NULL DEFAULT '#',
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS academic_years (
                id INT AUTO_INCREMENT PRIMARY KEY,
                year_name VARCHAR(20) NOT NULL UNIQUE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS classes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                class_name VARCHAR(50) NOT NULL UNIQUE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS exams (
                id INT AUTO_INCREMENT PRIMARY KEY,
                exam_name VARCHAR(100) NOT NULL,
                academic_year_id INT NULL,
                class_id INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_exams_class_year (class_id, academic_year_id),
                UNIQUE KEY uq_exam_scope (exam_name, academic_year_id, class_id),
                CONSTRAINT fk_exams_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE SET NULL,
                CONSTRAINT fk_exams_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS subjects (
                id INT AUTO_INCREMENT PRIMARY KEY,
                subject_name VARCHAR(100) NOT NULL UNIQUE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS students (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_code VARCHAR(24) NULL UNIQUE,
                name VARCHAR(100) NOT NULL,
                class_id INT NULL,
                academic_year_id INT NULL,
                UNIQUE KEY uq_student_identity (name, class_id, academic_year_id),
                KEY idx_students_class_year (class_id, academic_year_id),
                KEY idx_students_class_year_name (class_id, academic_year_id, name),
                KEY idx_students_year_class_name (academic_year_id, class_id, name),
                KEY idx_students_name (name),
                CONSTRAINT fk_students_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
                CONSTRAINT fk_students_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS student_code_counter (
                id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
                next_number BIGINT UNSIGNED NOT NULL DEFAULT 1,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS imports_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                admin_id INT NULL,
                file_name VARCHAR(255) NOT NULL,
                imported_rows INT NOT NULL DEFAULT 0,
                created_students INT NOT NULL DEFAULT 0,
                updated_marks INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_imports_log_created_at (created_at),
                KEY idx_imports_log_admin_id (admin_id),
                CONSTRAINT fk_imports_log_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS database_backups (
                id INT AUTO_INCREMENT PRIMARY KEY,
                file_name VARCHAR(255) NOT NULL,
                created_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_database_backups_created_at (created_at),
                KEY idx_database_backups_created_by (created_by),
                CONSTRAINT fk_database_backups_admin FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS student_accounts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT NOT NULL,
                username VARCHAR(64) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                password_plain VARCHAR(120) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                last_login_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_student_accounts_student (student_id),
                KEY idx_student_accounts_username_active (username, is_active),
                CONSTRAINT fk_student_accounts_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS marks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT NULL,
                exam_name VARCHAR(100) NOT NULL DEFAULT 'Term Exam',
                subject_name VARCHAR(100) NOT NULL,
                marks_obtained DECIMAL(7,2) DEFAULT 0.00,
                max_marks DECIMAL(7,2) NOT NULL DEFAULT 100.00,
                UNIQUE KEY uq_student_exam_subject (student_id, exam_name, subject_name),
                KEY idx_marks_exam (exam_name),
                KEY idx_marks_subject (subject_name),
                KEY idx_marks_student_exam (student_id, exam_name),
                CONSTRAINT fk_marks_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $migrations = [
            "ALTER TABLE admins ADD COLUMN full_name VARCHAR(100) NULL AFTER username",
            "ALTER TABLE admins ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER password_hash",
            "ALTER TABLE admins ADD COLUMN can_manage_students TINYINT(1) NOT NULL DEFAULT 1 AFTER is_active",
            "ALTER TABLE admins ADD COLUMN can_manage_marks TINYINT(1) NOT NULL DEFAULT 1 AFTER can_manage_students",
            "ALTER TABLE admins ADD COLUMN can_import_csv TINYINT(1) NOT NULL DEFAULT 1 AFTER can_manage_marks",
            "ALTER TABLE admins ADD COLUMN can_backup_db TINYINT(1) NOT NULL DEFAULT 1 AFTER can_import_csv",
            "ALTER TABLE admins ADD COLUMN can_maintenance_mode TINYINT(1) NOT NULL DEFAULT 1 AFTER can_backup_db",
            "ALTER TABLE admins ADD COLUMN can_manage_admins TINYINT(1) NOT NULL DEFAULT 1 AFTER can_maintenance_mode",
            "ALTER TABLE admins ADD COLUMN can_manage_site_settings TINYINT(1) NOT NULL DEFAULT 1 AFTER can_manage_admins",
            "ALTER TABLE admins ADD COLUMN can_view_analytics TINYINT(1) NOT NULL DEFAULT 1 AFTER can_manage_site_settings",
            "ALTER TABLE marks ADD COLUMN exam_name VARCHAR(100) NOT NULL DEFAULT 'Term Exam' AFTER student_id",
            "ALTER TABLE marks MODIFY marks_obtained DECIMAL(7,2) DEFAULT 0.00",
            "ALTER TABLE marks ADD COLUMN max_marks DECIMAL(7,2) NOT NULL DEFAULT 100.00 AFTER marks_obtained",
            "ALTER TABLE students ADD INDEX idx_students_class_year_name (class_id, academic_year_id, name)",
            "ALTER TABLE students ADD INDEX idx_students_year_class_name (academic_year_id, class_id, name)",
            "ALTER TABLE students ADD INDEX idx_students_name (name)",
            "ALTER TABLE marks ADD INDEX idx_marks_student_exam (student_id, exam_name)",
            "ALTER TABLE students ADD COLUMN student_code VARCHAR(24) NULL AFTER id",
            "ALTER TABLE students ADD UNIQUE KEY uq_students_code (student_code)",
            "UPDATE students SET student_code = CONCAT('STU', LPAD(id, 6, '0')) WHERE student_code IS NULL OR student_code = ''",
            "CREATE TABLE IF NOT EXISTS student_code_counter (
                id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
                next_number BIGINT UNSIGNED NOT NULL DEFAULT 1,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
            "CREATE TABLE IF NOT EXISTS student_accounts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT NOT NULL,
                username VARCHAR(64) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                password_plain VARCHAR(120) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                last_login_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_student_accounts_student (student_id),
                KEY idx_student_accounts_username_active (username, is_active),
                CONSTRAINT fk_student_accounts_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
            "ALTER TABLE student_accounts ADD COLUMN password_plain VARCHAR(120) NOT NULL DEFAULT '' AFTER password_hash",
            "ALTER TABLE student_accounts ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER password_plain",
            "ALTER TABLE student_accounts ADD COLUMN last_login_at DATETIME NULL AFTER is_active",
            "ALTER TABLE student_accounts ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
            "ALTER TABLE student_accounts ADD INDEX idx_student_accounts_username_active (username, is_active)",
            "CREATE TABLE IF NOT EXISTS site_settings (
                id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
                footer_text VARCHAR(255) NOT NULL DEFAULT 'ONYX',
                footer_url VARCHAR(255) NOT NULL DEFAULT 'https://onyxrns.com',
                footer_brand_name VARCHAR(140) NOT NULL DEFAULT 'BV-BrightVision',
                footer_copyright_owner VARCHAR(140) NOT NULL DEFAULT 'BV-BrightVision',
                footer_rights_text VARCHAR(160) NOT NULL DEFAULT 'All rights reserved.',
                footer_collab_text VARCHAR(255) NOT NULL DEFAULT 'Collaborated with apexinventives',
                footer_link_1_label VARCHAR(50) NOT NULL DEFAULT 'Privacy',
                footer_link_1_url VARCHAR(255) NOT NULL DEFAULT '#',
                footer_link_2_label VARCHAR(50) NOT NULL DEFAULT 'Terms',
                footer_link_2_url VARCHAR(255) NOT NULL DEFAULT '#',
                footer_link_3_label VARCHAR(50) NOT NULL DEFAULT 'Contact',
                footer_link_3_url VARCHAR(255) NOT NULL DEFAULT '#',
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
            "ALTER TABLE site_settings ADD COLUMN footer_text VARCHAR(255) NOT NULL DEFAULT 'ONYX'",
            "ALTER TABLE site_settings ADD COLUMN footer_url VARCHAR(255) NOT NULL DEFAULT 'https://onyxrns.com'",
            "ALTER TABLE site_settings ADD COLUMN footer_brand_name VARCHAR(140) NOT NULL DEFAULT 'BV-BrightVision'",
            "ALTER TABLE site_settings ADD COLUMN footer_copyright_owner VARCHAR(140) NOT NULL DEFAULT 'BV-BrightVision'",
            "ALTER TABLE site_settings ADD COLUMN footer_rights_text VARCHAR(160) NOT NULL DEFAULT 'All rights reserved.'",
            "ALTER TABLE site_settings ADD COLUMN footer_collab_text VARCHAR(255) NOT NULL DEFAULT 'Collaborated with apexinventives'",
            "ALTER TABLE site_settings ADD COLUMN footer_link_1_label VARCHAR(50) NOT NULL DEFAULT 'Privacy'",
            "ALTER TABLE site_settings ADD COLUMN footer_link_1_url VARCHAR(255) NOT NULL DEFAULT '#'",
            "ALTER TABLE site_settings ADD COLUMN footer_link_2_label VARCHAR(50) NOT NULL DEFAULT 'Terms'",
            "ALTER TABLE site_settings ADD COLUMN footer_link_2_url VARCHAR(255) NOT NULL DEFAULT '#'",
            "ALTER TABLE site_settings ADD COLUMN footer_link_3_label VARCHAR(50) NOT NULL DEFAULT 'Contact'",
            "ALTER TABLE site_settings ADD COLUMN footer_link_3_url VARCHAR(255) NOT NULL DEFAULT '#'",
            "ALTER TABLE site_settings ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
        ];

        foreach ($migrations as $sql) {
            try {
                $pdo->exec($sql);
            } catch (PDOException $e) {
                // Ignore migration errors for already-applied schema changes.
            }
        }

        $adminCount = (int) $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
        if ($adminCount === 0) {
            $insertAdmin = $pdo->prepare("
                INSERT INTO admins (
                    username, full_name, password_hash, is_active,
                    can_manage_students, can_manage_marks, can_import_csv,
                    can_backup_db, can_maintenance_mode, can_manage_admins, can_manage_site_settings, can_view_analytics
                ) VALUES (
                    ?, ?, ?, 1, 1, 1, 1, 1, 1, 1, 1, 1
                )
            ");
            $insertAdmin->execute([
                'rnsdev',
                'System Administrator',
                '$2y$10$i4NwEXIhertNmP5W2uV7vOpRgPDUCa5VRulEUeqn6fi9WYQGfUKU.'
            ]);
        }

        // Promote the original restored admin account into the protected super admin.
        try {
            $superAdminCount = (int) $pdo->query("SELECT COUNT(*) FROM admins WHERE username = 'rnsdev'")->fetchColumn();
            if ($superAdminCount === 0) {
                $pdo->exec("UPDATE admins SET username = 'rnsdev' WHERE username = 'admin' LIMIT 1");
            }
        } catch (Throwable $e) {
            // ignore
        }

        // Ensure the protected super admin account keeps full access after migration.
        $pdo->exec("
            UPDATE admins
            SET
                full_name = COALESCE(NULLIF(full_name, ''), 'System Administrator'),
                is_active = 1,
                can_manage_students = 1,
                can_manage_marks = 1,
                can_import_csv = 1,
                can_backup_db = 1,
                can_maintenance_mode = 1,
                can_manage_admins = 1,
                can_manage_site_settings = 1,
                can_view_analytics = 1
            WHERE username = 'rnsdev'
        ");

        // Backfill student codes for legacy rows.
        try {
            $pdo->exec("UPDATE students SET student_code = CONCAT('STU', LPAD(id, 6, '0')) WHERE student_code IS NULL OR student_code = ''");
        } catch (Throwable $e) {
            // ignore
        }

        // Initialize and advance global student code counter (never reuse IDs).
        try {
            $pdo->exec("
                INSERT INTO student_code_counter (id, next_number)
                VALUES (1, 1)
                ON DUPLICATE KEY UPDATE next_number = next_number
            ");

            $maxCodeNumber = (int) $pdo->query("
                SELECT COALESCE(MAX(CAST(SUBSTRING(student_code, 4) AS UNSIGNED)), 0)
                FROM students
                WHERE student_code REGEXP '^STU[0-9]+$'
            ")->fetchColumn();

            $bumpTo = max(1, $maxCodeNumber + 1);
            $bumpStmt = $pdo->prepare("UPDATE student_code_counter SET next_number = GREATEST(next_number, ?) WHERE id = 1");
            $bumpStmt->execute([$bumpTo]);
        } catch (Throwable $e) {
            // ignore
        }

        // Always keep at least one active admin with admin-management permission.
        $managerCount = (int) $pdo->query("
            SELECT COUNT(*)
            FROM admins
            WHERE is_active = 1 AND can_manage_admins = 1
        ")->fetchColumn();

        if ($managerCount === 0) {
            $fallbackAdminId = (int) $pdo->query("SELECT id FROM admins ORDER BY id ASC LIMIT 1")->fetchColumn();
            if ($fallbackAdminId > 0) {
                $stmt = $pdo->prepare("
                    UPDATE admins
                    SET is_active = 1, can_manage_admins = 1
                    WHERE id = ?
                ");
                $stmt->execute([$fallbackAdminId]);
            }
        }

        $footerSeedStmt = $pdo->prepare("
            INSERT INTO site_settings (
                id,
                footer_text,
                footer_url,
                footer_brand_name,
                footer_copyright_owner,
                footer_rights_text,
                footer_collab_text,
                footer_link_1_label,
                footer_link_1_url,
                footer_link_2_label,
                footer_link_2_url,
                footer_link_3_label,
                footer_link_3_url
            )
            VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                footer_text = COALESCE(NULLIF(footer_text, ''), VALUES(footer_text)),
                footer_url = COALESCE(NULLIF(footer_url, ''), VALUES(footer_url)),
                footer_brand_name = COALESCE(NULLIF(footer_brand_name, ''), VALUES(footer_brand_name)),
                footer_copyright_owner = COALESCE(NULLIF(footer_copyright_owner, ''), VALUES(footer_copyright_owner)),
                footer_rights_text = COALESCE(NULLIF(footer_rights_text, ''), VALUES(footer_rights_text)),
                footer_collab_text = COALESCE(NULLIF(footer_collab_text, ''), VALUES(footer_collab_text)),
                footer_link_1_label = COALESCE(NULLIF(footer_link_1_label, ''), VALUES(footer_link_1_label)),
                footer_link_1_url = COALESCE(NULLIF(footer_link_1_url, ''), VALUES(footer_link_1_url)),
                footer_link_2_label = COALESCE(NULLIF(footer_link_2_label, ''), VALUES(footer_link_2_label)),
                footer_link_2_url = COALESCE(NULLIF(footer_link_2_url, ''), VALUES(footer_link_2_url)),
                footer_link_3_label = COALESCE(NULLIF(footer_link_3_label, ''), VALUES(footer_link_3_label)),
                footer_link_3_url = COALESCE(NULLIF(footer_link_3_url, ''), VALUES(footer_link_3_url))
        ");
        $defaults = default_footer_settings();
        $footerSeedStmt->execute([
            $defaults['developer_name'],
            $defaults['developer_url'],
            $defaults['brand_name'],
            $defaults['copyright_owner'],
            $defaults['rights_text'],
            $defaults['collaboration_text'],
            $defaults['link_1_label'],
            $defaults['link_1_url'],
            $defaults['link_2_label'],
            $defaults['link_2_url'],
            $defaults['link_3_label'],
            $defaults['link_3_url']
        ]);

        $schemaEnsured = true;
    }
}

if (!function_exists('default_footer_settings')) {
    function default_footer_settings(): array
    {
        return [
            'brand_name' => 'BV-BrightVision',
            'copyright_owner' => 'BV-BrightVision',
            'rights_text' => 'All rights reserved.',
            'developer_name' => 'ONYX',
            'developer_url' => 'https://onyxrns.com',
            'collaboration_text' => 'Collaborated with apexinventives',
            'link_1_label' => 'Privacy',
            'link_1_url' => '#',
            'link_2_label' => 'Terms',
            'link_2_url' => '#',
            'link_3_label' => 'Contact',
            'link_3_url' => '#',
            // Legacy aliases retained for compatibility.
            'text' => 'ONYX',
            'url' => 'https://onyxrns.com'
        ];
    }
}

if (!function_exists('get_site_footer_settings')) {
    function get_site_footer_settings(PDO $pdo): array
    {
        $defaults = default_footer_settings();
        try {
            $stmt = $pdo->query("
                SELECT
                    footer_text,
                    footer_url,
                    footer_brand_name,
                    footer_copyright_owner,
                    footer_rights_text,
                    footer_collab_text,
                    footer_link_1_label,
                    footer_link_1_url,
                    footer_link_2_label,
                    footer_link_2_url,
                    footer_link_3_label,
                    footer_link_3_url
                FROM site_settings
                WHERE id = 1
                LIMIT 1
            ");
            $row = $stmt->fetch();
            if (!$row) {
                return $defaults;
            }
            $developerName = trim((string) ($row['footer_text'] ?? ''));
            $developerName = preg_replace('/^designed\s*(and|&)\s*developed\s*by\s*/i', '', $developerName);
            $developerName = trim((string) $developerName);
            $developerUrl = trim((string) ($row['footer_url'] ?? ''));

            $brandName = trim((string) ($row['footer_brand_name'] ?? ''));
            $copyrightOwner = trim((string) ($row['footer_copyright_owner'] ?? ''));
            $rightsText = trim((string) ($row['footer_rights_text'] ?? ''));
            $collabText = trim((string) ($row['footer_collab_text'] ?? ''));

            $link1Label = trim((string) ($row['footer_link_1_label'] ?? ''));
            $link1Url = trim((string) ($row['footer_link_1_url'] ?? ''));
            $link2Label = trim((string) ($row['footer_link_2_label'] ?? ''));
            $link2Url = trim((string) ($row['footer_link_2_url'] ?? ''));
            $link3Label = trim((string) ($row['footer_link_3_label'] ?? ''));
            $link3Url = trim((string) ($row['footer_link_3_url'] ?? ''));

            $footerLinks = [];
            if ($link1Label !== '') {
                $footerLinks[] = ['label' => $link1Label, 'url' => ($link1Url !== '' ? $link1Url : '#')];
            }
            if ($link2Label !== '') {
                $footerLinks[] = ['label' => $link2Label, 'url' => ($link2Url !== '' ? $link2Url : '#')];
            }
            if ($link3Label !== '') {
                $footerLinks[] = ['label' => $link3Label, 'url' => ($link3Url !== '' ? $link3Url : '#')];
            }

            return [
                'brand_name' => $brandName !== '' ? $brandName : $defaults['brand_name'],
                'copyright_owner' => $copyrightOwner !== '' ? $copyrightOwner : $defaults['copyright_owner'],
                'rights_text' => $rightsText !== '' ? $rightsText : $defaults['rights_text'],
                'developer_name' => $developerName !== '' ? $developerName : $defaults['developer_name'],
                'developer_url' => $developerUrl !== '' ? $developerUrl : $defaults['developer_url'],
                'collaboration_text' => $collabText,
                'link_1_label' => $link1Label,
                'link_1_url' => $link1Url !== '' ? $link1Url : '#',
                'link_2_label' => $link2Label,
                'link_2_url' => $link2Url !== '' ? $link2Url : '#',
                'link_3_label' => $link3Label,
                'link_3_url' => $link3Url !== '' ? $link3Url : '#',
                'links' => $footerLinks,
                // Legacy aliases retained for compatibility.
                'text' => $developerName !== '' ? $developerName : $defaults['text'],
                'url' => $developerUrl !== '' ? $developerUrl : $defaults['url']
            ];
        } catch (Throwable $e) {
            return $defaults;
        }
    }
}

if (!function_exists('format_student_code')) {
    function format_student_code(int $studentId): string
    {
        return 'STU' . str_pad((string) max(0, $studentId), 6, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('assign_student_code')) {
    function assign_student_code(PDO $pdo, int $studentId): string
    {
        if ($studentId <= 0) {
            throw new InvalidArgumentException('Invalid student selected for code generation.');
        }

        $stmt = $pdo->prepare("SELECT student_code FROM students WHERE id = ? LIMIT 1");
        $stmt->execute([$studentId]);
        $existing = strtoupper(trim((string) $stmt->fetchColumn()));
        if ($existing !== '') {
            return $existing;
        }

        $pdo->exec("
            INSERT INTO student_code_counter (id, next_number)
            VALUES (1, 1)
            ON DUPLICATE KEY UPDATE next_number = next_number
        ");

        $updateStmt = $pdo->prepare("UPDATE students SET student_code = ? WHERE id = ? AND (student_code IS NULL OR student_code = '')");

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $pdo->exec("UPDATE student_code_counter SET next_number = LAST_INSERT_ID(next_number + 1) WHERE id = 1");
            $lastInserted = (int) $pdo->query("SELECT LAST_INSERT_ID()")->fetchColumn();
            $codeNumber = max(1, $lastInserted - 1);
            $candidate = format_student_code($codeNumber);

            try {
                $updateStmt->execute([$candidate, $studentId]);
                if ($updateStmt->rowCount() > 0) {
                    return $candidate;
                }

                $stmt->execute([$studentId]);
                $alreadySet = strtoupper(trim((string) $stmt->fetchColumn()));
                if ($alreadySet !== '') {
                    return $alreadySet;
                }
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
            }
        }

        throw new RuntimeException('Unable to generate a unique student code right now. Please try again.');
    }
}

if (!function_exists('normalize_student_username_token')) {
    function normalize_student_username_token(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'student';
        }

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($converted) && $converted !== '') {
                $value = $converted;
            }
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
        if ($value === '') {
            return 'student';
        }

        return substr($value, 0, 20);
    }
}

if (!function_exists('next_available_student_username')) {
    function next_available_student_username(PDO $pdo, string $studentName, int $studentId): string
    {
        $base = normalize_student_username_token($studentName);
        $base = substr($base, 0, 12);
        if ($base === '') {
            $base = 'student';
        }

        $candidate = strtolower($base . str_pad((string) $studentId, 4, '0', STR_PAD_LEFT));
        $suffix = 0;

        $checkStmt = $pdo->prepare("SELECT student_id FROM student_accounts WHERE username = ? LIMIT 1");
        while (true) {
            $checkStmt->execute([$candidate]);
            $ownerId = (int) $checkStmt->fetchColumn();
            if ($ownerId === 0 || $ownerId === $studentId) {
                return $candidate;
            }

            $suffix++;
            $suffixToken = (string) $suffix;
            $prefix = substr($base, 0, max(4, 16 - strlen($suffixToken)));
            $candidate = strtolower($prefix . $suffixToken);
        }
    }
}

if (!function_exists('generate_student_password')) {
    function generate_student_password(int $length = 10): string
    {
        $length = max(8, $length);
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%';
        $maxIndex = strlen($chars) - 1;

        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $maxIndex)];
        }
        return $password;
    }
}

if (!function_exists('issue_student_credentials')) {
    function issue_student_credentials(PDO $pdo, int $studentId, bool $forceReset = false): array
    {
        if ($studentId <= 0) {
            throw new InvalidArgumentException('Invalid student selected.');
        }

        $stmt = $pdo->prepare("
            SELECT
                s.id,
                s.name,
                sa.id AS account_id,
                sa.username,
                sa.password_plain,
                sa.is_active
            FROM students s
            LEFT JOIN student_accounts sa ON sa.student_id = s.id
            WHERE s.id = ?
            LIMIT 1
        ");
        $stmt->execute([$studentId]);
        $student = $stmt->fetch();

        if (!$student) {
            throw new RuntimeException('Student record not found.');
        }

        $accountId = (int) ($student['account_id'] ?? 0);
        $username = strtolower(trim((string) ($student['username'] ?? '')));
        $existingPlain = trim((string) ($student['password_plain'] ?? ''));

        if ($accountId > 0 && !$forceReset && $username !== '' && $existingPlain !== '') {
            return [
                'student_id' => $studentId,
                'username' => $username,
                'password_plain' => $existingPlain,
                'created' => false
            ];
        }

        if ($username === '') {
            $username = next_available_student_username($pdo, (string) $student['name'], $studentId);
        }

        $plainPassword = generate_student_password();
        $passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);

        if ($accountId > 0) {
            $updateStmt = $pdo->prepare("
                UPDATE student_accounts
                SET
                    username = ?,
                    password_hash = ?,
                    password_plain = ?,
                    is_active = 1
                WHERE id = ?
            ");
            $updateStmt->execute([$username, $passwordHash, $plainPassword, $accountId]);
        } else {
            $insertStmt = $pdo->prepare("
                INSERT INTO student_accounts (
                    student_id,
                    username,
                    password_hash,
                    password_plain,
                    is_active
                ) VALUES (?, ?, ?, ?, 1)
            ");
            $insertStmt->execute([$studentId, $username, $passwordHash, $plainPassword]);
        }

        return [
            'student_id' => $studentId,
            'username' => $username,
            'password_plain' => $plainPassword,
            'created' => true
        ];
    }
}

if (!function_exists('bulk_generate_student_credentials')) {
    function bulk_generate_student_credentials(PDO $pdo, bool $forceReset = false): int
    {
        if ($forceReset) {
            $studentIds = $pdo->query("SELECT id FROM students ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $studentIds = $pdo->query("
                SELECT s.id
                FROM students s
                LEFT JOIN student_accounts sa ON sa.student_id = s.id
                WHERE sa.id IS NULL OR sa.username = '' OR sa.password_plain = ''
                ORDER BY s.id ASC
            ")->fetchAll(PDO::FETCH_COLUMN);
        }

        $count = 0;
        foreach ($studentIds as $idValue) {
            issue_student_credentials($pdo, (int) $idValue, $forceReset);
            $count++;
        }

        return $count;
    }
}

if (!function_exists('score_to_percentage')) {
    function score_to_percentage(float $marksObtained, float $maxMarks): float
    {
        if ($maxMarks <= 0) {
            return 0.0;
        }

        return round(($marksObtained / $maxMarks) * 100, 2);
    }
}

try {
    try {
        $pdo = create_pdo_connection(
            "mysql:host=$host;dbname=$db_name;charset=utf8mb4",
            $db_user,
            $db_pass
        );
    } catch (PDOException $connectError) {
        if ((int) $connectError->getCode() !== 1049) {
            throw $connectError;
        }

        $pdoSetup = create_pdo_connection("mysql:host=$host;charset=utf8mb4", $db_user, $db_pass);
        $pdoSetup->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");

        $pdo = create_pdo_connection(
            "mysql:host=$host;dbname=$db_name;charset=utf8mb4",
            $db_user,
            $db_pass
        );
    }

    ensure_schema($pdo);
} catch (PDOException $e) {
    die('ERROR: Could not connect to the database. ' . $e->getMessage());
}
