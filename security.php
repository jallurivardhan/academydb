<?php
require_once __DIR__ . '/database.php';

// Security Configuration

// Session Security Settings - Must be set before session_start()
if (session_status() === PHP_SESSION_NONE) {
    // Set session name before starting session
    session_name('ACADSESSID'); // Custom session name to mask PHP
    
    // Configure session settings
    ini_set('session.cookie_httponly', 1); // Prevent XSS accessing session cookie
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 1);   // Only send cookie over HTTPS
    ini_set('session.use_strict_mode', 1); // Use strict session mode
    ini_set('session.cookie_samesite', 'Strict'); // Prevent CSRF
    ini_set('session.gc_maxlifetime', 1800); // 30 minutes
    
    session_start();
}

// Security configuration
define('CSRF_TOKEN_NAME', 'csrf_token');
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_TIMEOUT', 300); // 5 minutes

// Input sanitization
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// CSRF Protection
function generateCSRFToken() {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function validateCSRFToken($token) {
    if (empty($_SESSION[CSRF_TOKEN_NAME]) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

// Rate Limiting
function checkRateLimit($ip, $action, $maxAttempts, $timeframe) {
    global $conn;
    
    // Clean up old entries
    $stmt = $conn->prepare("DELETE FROM RateLimiting WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? SECOND)");
    $stmt->bind_param("i", $timeframe);
    $stmt->execute();
    
    // Count attempts
    $stmt = $conn->prepare("SELECT COUNT(*) as attempts FROM RateLimiting WHERE ip_address = ? AND action = ? AND timestamp > DATE_SUB(NOW(), INTERVAL ? SECOND)");
    $stmt->bind_param("ssi", $ip, $action, $timeframe);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['attempts'] >= $maxAttempts) {
        return false;
    }
    
    // Log attempt
    $stmt = $conn->prepare("INSERT INTO RateLimiting (ip_address, action, timestamp) VALUES (?, ?, NOW())");
    $stmt->bind_param("ss", $ip, $action);
    $stmt->execute();
    
    return true;
}

// Password validation
function validatePassword($password) {
    // Check minimum length
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        return false;
    }
    
    // Check for at least one uppercase letter
    if (!preg_match('/[A-Z]/', $password)) {
        return false;
    }
    
    // Check for at least one lowercase letter
    if (!preg_match('/[a-z]/', $password)) {
        return false;
    }
    
    // Check for at least one number
    if (!preg_match('/[0-9]/', $password)) {
        return false;
    }
    
    // Check for at least one special character
    if (!preg_match('/[!@#$%^&*()\-_=+{};:,<.>]/', $password)) {
        return false;
    }
    
    return true;
}

// Activity logging
function logUserActivity($userId, $action, $status, $details = '') {
    global $conn;
    
    $stmt = $conn->prepare("INSERT INTO UserActivityLog (user_id, action, status, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", 
        $userId,
        $action,
        $status,
        $details,
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT']
    );
    $stmt->execute();
}

// Create necessary tables if they don't exist
$tables = [
    "RateLimiting" => "
        CREATE TABLE IF NOT EXISTS RateLimiting (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            action VARCHAR(50) NOT NULL,
            timestamp DATETIME NOT NULL,
            INDEX idx_ip_action (ip_address, action),
            INDEX idx_timestamp (timestamp)
        )
    ",
    "UserActivityLog" => "
        CREATE TABLE IF NOT EXISTS UserActivityLog (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            action VARCHAR(50) NOT NULL,
            status VARCHAR(20) NOT NULL,
            details TEXT,
            ip_address VARCHAR(45) NOT NULL,
            user_agent VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_action (user_id, action),
            INDEX idx_created_at (created_at)
        )
    "
];

foreach ($tables as $table => $sql) {
    try {
        $conn->query($sql);
    } catch (Exception $e) {
        error_log("Error creating table $table: " . $e->getMessage());
    }
}

// Set secure headers
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' \'unsafe-eval\' https://cdnjs.cloudflare.com; style-src \'self\' \'unsafe-inline\' https://cdnjs.cloudflare.com; font-src \'self\' https://cdnjs.cloudflare.com');

// Activity Logging
function logUserActivity($userId, $action, $description = '', $status = 'success') {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO UserActivityLog (
                UserID, Action, Description, Status, Timestamp, IPAddress, UserAgent
            ) VALUES (?, ?, ?, ?, NOW(), ?, ?)
        ");
        
        $stmt->execute([
            $userId,
            $action,
            $description,
            $status,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (PDOException $e) {
        error_log("Activity logging error: " . $e->getMessage());
    }
}

// Session Hijacking Prevention
function validateSession() {
    global $pdo;
    try {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['last_activity'])) {
            return false;
        }
        
        $stmt = $pdo->query("SELECT session_timeout FROM SecuritySettings WHERE setting_id = 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        $timeout = $settings['session_timeout'] * 60; // Convert minutes to seconds
        
        if (time() - $_SESSION['last_activity'] > $timeout) {
            session_destroy();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    } catch (PDOException $e) {
        error_log("Error validating session: " . $e->getMessage());
        return false;
    }
}

// Initialize Security Settings
setSecurityHeaders();
validateSession();

// Database table for rate limiting
$rateLimitingTable = "
CREATE TABLE IF NOT EXISTS RateLimiting (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    action VARCHAR(50) NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_action (ip_address, action)
)";

// Database table for user activity logging
$userActivityLogTable = "
CREATE TABLE IF NOT EXISTS UserActivityLog (
    LogID INT AUTO_INCREMENT PRIMARY KEY,
    UserID VARCHAR(50) NOT NULL,
    Action VARCHAR(100) NOT NULL,
    Details TEXT,
    Status VARCHAR(20) NOT NULL,
    IPAddress VARCHAR(45) NOT NULL,
    UserAgent VARCHAR(255),
    Timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_action (UserID, Action)
)";

// Create security tables if they don't exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS SecuritySettings (
            setting_id INT PRIMARY KEY AUTO_INCREMENT,
            min_password_length INT DEFAULT 8,
            require_special_chars BOOLEAN DEFAULT TRUE,
            require_numbers BOOLEAN DEFAULT TRUE,
            require_uppercase BOOLEAN DEFAULT TRUE,
            session_timeout INT DEFAULT 30,
            max_login_attempts INT DEFAULT 5,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS SecurityLogs (
            log_id INT PRIMARY KEY AUTO_INCREMENT,
            user_id VARCHAR(50),
            action VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45),
            status ENUM('success', 'failure') NOT NULL,
            details TEXT,
            log_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_log_time (log_time)
        )
    ");

    // Insert default security settings if not exists
    $pdo->exec("
        INSERT INTO SecuritySettings (setting_id, min_password_length, require_special_chars, require_numbers, require_uppercase)
        SELECT 1, 8, TRUE, TRUE, TRUE
        WHERE NOT EXISTS (SELECT 1 FROM SecuritySettings WHERE setting_id = 1)
    ");
} catch (PDOException $e) {
    error_log("Error creating security tables: " . $e->getMessage());
}

// Security functions
function logSecurityEvent($userId, $action, $status = 'success', $details = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO SecurityLogs (user_id, action, ip_address, status, details)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $action,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $status,
            $details
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("Error logging security event: " . $e->getMessage());
        return false;
    }
}

function checkLoginAttempts($userId) {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT max_login_attempts FROM SecuritySettings WHERE setting_id = 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        $maxAttempts = $settings['max_login_attempts'];
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as attempts
            FROM SecurityLogs
            WHERE user_id = ?
            AND action = 'login_attempt'
            AND status = 'failure'
            AND log_time > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['attempts'] >= $maxAttempts;
    } catch (PDOException $e) {
        error_log("Error checking login attempts: " . $e->getMessage());
        return true; // Fail safe - prevent login if there's an error
    }
}
?> 