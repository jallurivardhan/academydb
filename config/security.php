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

// CSRF Protection
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Rate Limiting
function checkRateLimit($ip, $action, $maxAttempts, $timeFrame) {
    global $pdo;
    
    try {
        // Clean old records
        $stmt = $pdo->prepare("DELETE FROM RateLimiting WHERE timestamp < (NOW() - INTERVAL ? SECOND)");
        $stmt->execute([$timeFrame]);
        
        // Count recent attempts
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as attempt_count 
            FROM RateLimiting 
            WHERE ip_address = ? AND action = ? AND timestamp > (NOW() - INTERVAL ? SECOND)
        ");
        $stmt->execute([$ip, $action, $timeFrame]);
        $result = $stmt->fetch();
        
        if ($result['attempt_count'] >= $maxAttempts) {
            return false;
        }
        
        // Log this attempt
        $stmt = $pdo->prepare("INSERT INTO RateLimiting (ip_address, action, timestamp) VALUES (?, ?, NOW())");
        $stmt->execute([$ip, $action]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Rate limiting error: " . $e->getMessage());
        return true; // Allow request if rate limiting fails
    }
}

// Input Validation
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Password Validation
function validatePassword($password) {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT * FROM SecuritySettings WHERE setting_id = 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (strlen($password) < $settings['min_password_length']) {
            return "Password must be at least {$settings['min_password_length']} characters long";
        }
        
        if ($settings['require_special_chars'] && !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            return "Password must contain at least one special character";
        }
        
        if ($settings['require_numbers'] && !preg_match('/[0-9]/', $password)) {
            return "Password must contain at least one number";
        }
        
        if ($settings['require_uppercase'] && !preg_match('/[A-Z]/', $password)) {
            return "Password must contain at least one uppercase letter";
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Error validating password: " . $e->getMessage());
        return "Error validating password";
    }
}

// Secure Headers
function setSecurityHeaders() {
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    header("Content-Security-Policy: default-src 'self' https://cdn.jsdelivr.net; script-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; style-src 'self' https://cdn.jsdelivr.net 'unsafe-inline';");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
}

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