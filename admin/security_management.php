<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

// Check if user is logged in and has AdminRole
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'AdminRole') {
    logUserActivity($_SESSION['user_id'] ?? 'unknown', 'unauthorized_access', 'Attempted to access admin security management page', 'failed');
    header('Location: ../login.php');
    exit();
}

// Rate limiting check
if (!checkRateLimit($_SERVER['REMOTE_ADDR'], 'admin_security_management', 10, 300)) {
    logUserActivity($_SESSION['user_id'], 'rate_limit_exceeded', 'Too many attempts to access security management', 'failed');
    header('Location: ../error.php?code=429');
    exit();
}

$error = '';
$success = '';
$activityLogs = [];
$rateLimitViolations = [];
$securitySettings = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        logUserActivity($_SESSION['user_id'], 'csrf_validation_failed', 'CSRF token validation failed on security settings update', 'failed');
        $error = "Security validation failed. Please try again.";
    } else {
        if (isset($_POST['action'])) {
            try {
                $action = sanitizeInput($_POST['action']);
                
                switch ($action) {
                    case 'update_security_settings':
                        $maxLoginAttempts = sanitizeInput($_POST['max_login_attempts']);
                        $lockoutDuration = sanitizeInput($_POST['lockout_duration']);
                        $passwordExpiryDays = sanitizeInput($_POST['password_expiry_days']);
                        $sessionTimeout = sanitizeInput($_POST['session_timeout']);
                        $enableTwoFactor = isset($_POST['enable_two_factor']) ? 1 : 0;
                        
                        // Validate inputs
                        if (!is_numeric($maxLoginAttempts) || $maxLoginAttempts < 1) {
                            throw new Exception("Invalid maximum login attempts value");
                        }
                        if (!is_numeric($lockoutDuration) || $lockoutDuration < 1) {
                            throw new Exception("Invalid lockout duration value");
                        }
                        if (!is_numeric($passwordExpiryDays) || $passwordExpiryDays < 0) {
                            throw new Exception("Invalid password expiry days value");
                        }
                        if (!is_numeric($sessionTimeout) || $sessionTimeout < 1) {
                            throw new Exception("Invalid session timeout value");
                        }
                        
                        // Update security settings in database
                        $stmt = $pdo->prepare("
                            UPDATE SecuritySettings 
                            SET MaxLoginAttempts = ?, 
                                LockoutDuration = ?, 
                                PasswordExpiryDays = ?, 
                                SessionTimeout = ?, 
                                EnableTwoFactor = ?,
                                LastUpdated = NOW(),
                                UpdatedBy = ?
                        ");
                        $stmt->execute([
                            $maxLoginAttempts, 
                            $lockoutDuration, 
                            $passwordExpiryDays, 
                            $sessionTimeout, 
                            $enableTwoFactor,
                            $_SESSION['user_id']
                        ]);
                        
                        $success = "Security settings updated successfully.";
                        logUserActivity($_SESSION['user_id'], 'update_security_settings', "Updated security settings", 'success');
                        break;
                        
                    case 'reset_rate_limits':
                        $stmt = $pdo->prepare("DELETE FROM RateLimiting");
                        $stmt->execute();
                        $success = "Rate limiting records cleared successfully.";
                        logUserActivity($_SESSION['user_id'], 'reset_rate_limits', "Cleared rate limiting records", 'success');
                        break;
                        
                    case 'clear_activity_logs':
                        $daysToKeep = sanitizeInput($_POST['days_to_keep']);
                        
                        if (!is_numeric($daysToKeep) || $daysToKeep < 0) {
                            throw new Exception("Invalid days to keep value");
                        }
                        
                        $stmt = $pdo->prepare("DELETE FROM UserActivityLog WHERE Timestamp < (NOW() - INTERVAL ? DAY)");
                        $stmt->execute([$daysToKeep]);
                        $success = "Activity logs older than $daysToKeep days cleared successfully.";
                        logUserActivity($_SESSION['user_id'], 'clear_activity_logs', "Cleared activity logs older than $daysToKeep days", 'success');
                        break;
                        
                    case 'view_activity_logs':
                        $days = sanitizeInput($_POST['days'] ?? 7);
                        $limit = sanitizeInput($_POST['limit'] ?? 100);
                        
                        if (!is_numeric($days) || $days < 1) {
                            $days = 7;
                        }
                        if (!is_numeric($limit) || $limit < 1) {
                            $limit = 100;
                        }
                        
                        $stmt = $pdo->prepare("
                            SELECT * FROM UserActivityLog 
                            WHERE Timestamp > (NOW() - INTERVAL ? DAY)
                            ORDER BY Timestamp DESC
                            LIMIT ?
                        ");
                        $stmt->execute([$days, $limit]);
                        $activityLogs = $stmt->fetchAll();
                        logUserActivity($_SESSION['user_id'], 'view_activity_logs', "Viewed activity logs for the past $days days", 'success');
                        break;
                        
                    case 'view_rate_limit_violations':
                        $days = sanitizeInput($_POST['days'] ?? 7);
                        $limit = sanitizeInput($_POST['limit'] ?? 100);
                        
                        if (!is_numeric($days) || $days < 1) {
                            $days = 7;
                        }
                        if (!is_numeric($limit) || $limit < 1) {
                            $limit = 100;
                        }
                        
                        $stmt = $pdo->prepare("
                            SELECT ip_address, action, COUNT(*) as violation_count, MAX(timestamp) as last_violation
                            FROM RateLimiting 
                            WHERE timestamp > (NOW() - INTERVAL ? DAY)
                            GROUP BY ip_address, action
                            HAVING violation_count > 5
                            ORDER BY violation_count DESC
                            LIMIT ?
                        ");
                        $stmt->execute([$days, $limit]);
                        $rateLimitViolations = $stmt->fetchAll();
                        logUserActivity($_SESSION['user_id'], 'view_rate_limit_violations', "Viewed rate limit violations for the past $days days", 'success');
                        break;
                }
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
                logUserActivity($_SESSION['user_id'], 'error', $e->getMessage(), 'failed');
            }
        }
    }
}

// Fetch current security settings
try {
    $stmt = $pdo->query("SELECT * FROM SecuritySettings LIMIT 1");
    $securitySettings = $stmt->fetch();
    
    // If no settings exist, create default settings
    if (!$securitySettings) {
        $stmt = $pdo->prepare("
            INSERT INTO SecuritySettings (
                MaxLoginAttempts, LockoutDuration, PasswordExpiryDays, 
                SessionTimeout, EnableTwoFactor, LastUpdated, UpdatedBy
            ) VALUES (5, 15, 90, 30, 0, NOW(), ?)
        ");
        $stmt->execute([$_SESSION['user_id']]);
        
        $stmt = $pdo->query("SELECT * FROM SecuritySettings LIMIT 1");
        $securitySettings = $stmt->fetch();
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    logUserActivity($_SESSION['user_id'], 'database_error', $e->getMessage(), 'failed');
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Management - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/admin_navbar.php'; ?>

    <div class="container mt-4">
        <h2>Security Management</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="row">
            <!-- Security Settings -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h4>Security Settings</h4>
                    </div>
                    <div class="card-body">
                        <form method="post" onsubmit="return validateSecurityForm(this);">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="update_security_settings">
                            
                            <div class="mb-3">
                                <label for="max_login_attempts" class="form-label">Maximum Login Attempts</label>
                                <input type="number" class="form-control" id="max_login_attempts" name="max_login_attempts" 
                                       value="<?php echo htmlspecialchars($securitySettings['MaxLoginAttempts']); ?>" 
                                       min="1" max="10" required>
                                <div class="form-text">Number of failed login attempts before account lockout</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="lockout_duration" class="form-label">Lockout Duration (minutes)</label>
                                <input type="number" class="form-control" id="lockout_duration" name="lockout_duration" 
                                       value="<?php echo htmlspecialchars($securitySettings['LockoutDuration']); ?>" 
                                       min="1" max="60" required>
                                <div class="form-text">How long to lock an account after exceeding login attempts</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password_expiry_days" class="form-label">Password Expiry (days)</label>
                                <input type="number" class="form-control" id="password_expiry_days" name="password_expiry_days" 
                                       value="<?php echo htmlspecialchars($securitySettings['PasswordExpiryDays']); ?>" 
                                       min="0" max="365" required>
                                <div class="form-text">Number of days before requiring password change (0 for never)</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="session_timeout" class="form-label">Session Timeout (minutes)</label>
                                <input type="number" class="form-control" id="session_timeout" name="session_timeout" 
                                       value="<?php echo htmlspecialchars($securitySettings['SessionTimeout']); ?>" 
                                       min="1" max="1440" required>
                                <div class="form-text">How long before an inactive session expires</div>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="enable_two_factor" name="enable_two_factor" 
                                       <?php echo $securitySettings['EnableTwoFactor'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="enable_two_factor">Enable Two-Factor Authentication</label>
                                <div class="form-text">Require 2FA for all users (requires additional setup)</div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Update Security Settings</button>
                        </form>
                    </div>
                </div>
                
                <!-- Security Actions -->
                <div class="card">
                    <div class="card-header">
                        <h4>Security Actions</h4>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <form method="post" class="mb-2" onsubmit="return confirm('Are you sure you want to reset all rate limiting records?');">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="action" value="reset_rate_limits">
                                <button type="submit" class="btn btn-warning w-100">Reset Rate Limiting Records</button>
                            </form>
                            
                            <form method="post" class="mb-2" onsubmit="return confirm('Are you sure you want to clear old activity logs?');">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="action" value="clear_activity_logs">
                                <div class="input-group">
                                    <input type="number" class="form-control" name="days_to_keep" value="30" min="1" max="365" required>
                                    <button type="submit" class="btn btn-danger">Clear Activity Logs Older Than (Days)</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Activity Logs -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h4>Activity Logs</h4>
                    </div>
                    <div class="card-body">
                        <form method="post" class="mb-3">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="view_activity_logs">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="days" class="form-label">Days to Show</label>
                                        <input type="number" class="form-control" id="days" name="days" 
                                               value="7" min="1" max="30">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="limit" class="form-label">Limit Results</label>
                                        <input type="number" class="form-control" id="limit" name="limit" 
                                               value="100" min="10" max="1000">
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">View Activity Logs</button>
                        </form>
                        
                        <?php if ($activityLogs): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-sm">
                                    <thead>
                                        <tr>
                                            <th>Timestamp</th>
                                            <th>User ID</th>
                                            <th>Action</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($activityLogs as $log): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($log['Timestamp']); ?></td>
                                                <td><?php echo htmlspecialchars($log['UserID']); ?></td>
                                                <td><?php echo htmlspecialchars($log['Action']); ?></td>
                                                <td>
                                                    <?php if ($log['Status'] === 'success'): ?>
                                                        <span class="badge bg-success">Success</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Failed</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No activity logs to display. Use the form above to view logs.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Rate Limit Violations -->
                <div class="card">
                    <div class="card-header">
                        <h4>Rate Limit Violations</h4>
                    </div>
                    <div class="card-body">
                        <form method="post" class="mb-3">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="view_rate_limit_violations">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="violation_days" class="form-label">Days to Show</label>
                                        <input type="number" class="form-control" id="violation_days" name="days" 
                                               value="7" min="1" max="30">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="violation_limit" class="form-label">Limit Results</label>
                                        <input type="number" class="form-control" id="violation_limit" name="limit" 
                                               value="100" min="10" max="1000">
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">View Rate Limit Violations</button>
                        </form>
                        
                        <?php if ($rateLimitViolations): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-sm">
                                    <thead>
                                        <tr>
                                            <th>IP Address</th>
                                            <th>Action</th>
                                            <th>Violations</th>
                                            <th>Last Violation</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rateLimitViolations as $violation): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($violation['ip_address']); ?></td>
                                                <td><?php echo htmlspecialchars($violation['action']); ?></td>
                                                <td><?php echo htmlspecialchars($violation['violation_count']); ?></td>
                                                <td><?php echo htmlspecialchars($violation['last_violation']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No rate limit violations to display. Use the form above to view violations.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Client-side validation
        function validateSecurityForm(form) {
            const maxLoginAttempts = form.querySelector('#max_login_attempts').value;
            const lockoutDuration = form.querySelector('#lockout_duration').value;
            const passwordExpiryDays = form.querySelector('#password_expiry_days').value;
            const sessionTimeout = form.querySelector('#session_timeout').value;
            
            if (maxLoginAttempts < 1 || maxLoginAttempts > 10) {
                alert('Maximum login attempts must be between 1 and 10');
                return false;
            }
            
            if (lockoutDuration < 1 || lockoutDuration > 60) {
                alert('Lockout duration must be between 1 and 60 minutes');
                return false;
            }
            
            if (passwordExpiryDays < 0 || passwordExpiryDays > 365) {
                alert('Password expiry days must be between 0 and 365');
                return false;
            }
            
            if (sessionTimeout < 1 || sessionTimeout > 1440) {
                alert('Session timeout must be between 1 and 1440 minutes');
                return false;
            }
            
            return true;
        }

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html> 