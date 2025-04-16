-- Security Settings Table
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
);

-- Security Logs Table
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
);

-- Insert default security settings if not exists
INSERT INTO SecuritySettings (setting_id, min_password_length, require_special_chars, require_numbers, require_uppercase)
SELECT 1, 8, TRUE, TRUE, TRUE
WHERE NOT EXISTS (SELECT 1 FROM SecuritySettings WHERE setting_id = 1); 