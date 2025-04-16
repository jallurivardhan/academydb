-- Security Settings Table
CREATE TABLE IF NOT EXISTS SecuritySettings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    min_password_length INT DEFAULT 8,
    require_special_chars BOOLEAN DEFAULT TRUE,
    require_numbers BOOLEAN DEFAULT TRUE,
    require_uppercase BOOLEAN DEFAULT TRUE,
    session_timeout INT DEFAULT 30,
    max_login_attempts INT DEFAULT 5,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Rate Limiting Table
CREATE TABLE IF NOT EXISTS RateLimiting (
    RateLimitID INT PRIMARY KEY AUTO_INCREMENT,
    ip_address VARCHAR(45) NOT NULL,
    action VARCHAR(100) NOT NULL,
    timestamp DATETIME NOT NULL,
    INDEX idx_ip_action (ip_address, action),
    INDEX idx_timestamp (timestamp)
);

-- User Activity Log Table
CREATE TABLE IF NOT EXISTS UserActivityLog (
    LogID INT PRIMARY KEY AUTO_INCREMENT,
    UserID VARCHAR(50) NOT NULL,
    Action VARCHAR(100) NOT NULL,
    Description TEXT,
    Status ENUM('success', 'failed') NOT NULL,
    Timestamp DATETIME NOT NULL,
    IPAddress VARCHAR(45),
    UserAgent TEXT,
    INDEX idx_user (UserID),
    INDEX idx_timestamp (Timestamp),
    INDEX idx_action (Action)
);

-- Security Logs Table
CREATE TABLE IF NOT EXISTS SecurityLogs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id VARCHAR(50),
    action VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    status ENUM('success', 'failure') NOT NULL,
    details TEXT,
    log_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_log_time (log_time)
);

-- Insert default security settings if not exists
INSERT INTO SecuritySettings (id, min_password_length, require_special_chars, require_numbers, require_uppercase)
SELECT 1, 8, TRUE, TRUE, TRUE
WHERE NOT EXISTS (SELECT 1 FROM SecuritySettings WHERE id = 1);

-- Create trigger to log password changes
DELIMITER //
CREATE TRIGGER IF NOT EXISTS log_password_change
AFTER UPDATE ON Users
FOR EACH ROW
BEGIN
    IF NEW.password != OLD.password THEN
        INSERT INTO SecurityLogs (user_id, action, status, details)
        VALUES (NEW.id, 'password_change', 'success', 'Password changed');
    END IF;
END//
DELIMITER ;

-- Create trigger to log failed login attempts
DELIMITER //
CREATE TRIGGER IF NOT EXISTS log_failed_login
AFTER UPDATE ON Users
FOR EACH ROW
BEGIN
    IF NEW.failed_login_attempts > OLD.failed_login_attempts THEN
        INSERT INTO SecurityLogs (user_id, action, status, details)
        VALUES (NEW.id, 'login_attempt', 'failure', CONCAT('Failed login attempt #', NEW.failed_login_attempts));
    END IF;
END//
DELIMITER ; 