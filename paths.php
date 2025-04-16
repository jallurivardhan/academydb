<?php
// Define base paths
define('BASE_PATH', dirname(__DIR__));
define('CONFIG_PATH', BASE_PATH . '/config');
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('UPLOADS_PATH', BASE_PATH . '/uploads');

// Define upload directories
define('MATERIALS_UPLOAD_PATH', UPLOADS_PATH . '/materials');
define('ASSIGNMENTS_UPLOAD_PATH', UPLOADS_PATH . '/assignments');
define('BACKUPS_PATH', BASE_PATH . '/backups');

// Define module paths
define('ADMIN_PATH', BASE_PATH . '/admin');
define('STUDENT_PATH', BASE_PATH . '/student');
define('FACULTY_PATH', BASE_PATH . '/faculty');

// Create upload directories if they don't exist
$directories = [
    UPLOADS_PATH,
    MATERIALS_UPLOAD_PATH,
    ASSIGNMENTS_UPLOAD_PATH,
    BACKUPS_PATH
];

foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
}

// URL paths (adjust these according to your setup)
define('BASE_URL', 'http://localhost:8000');
define('ADMIN_URL', BASE_URL . '/admin');
define('STUDENT_URL', BASE_URL . '/student');
define('FACULTY_URL', BASE_URL . '/faculty'); 