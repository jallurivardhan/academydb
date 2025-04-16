<?php
session_start();
require_once '../config/database.php';
require_once '../config/security.php';

// Check if user is logged in and has StudentRole
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'StudentRole') {
    header('Location: ../login.php');
    exit();
}

// Check rate limiting
if (!checkRateLimit($_SERVER['REMOTE_ADDR'], 'student_security', 20, 300)) {
    logUserActivity($_SESSION['user_id'], 'Rate limit exceeded for security settings access');
    die('Too many requests. Please try again later.');
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
        logUserActivity($user_id, 'CSRF token validation failed in security settings');
    } else {
        // Handle security settings update
        if (isset($_POST['update_settings'])) {
            $two_factor_enabled = isset($_POST['two_factor_enabled']) ? 1 : 0;
            $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
            $login_notifications = isset($_POST['login_notifications']) ? 1 : 0;
            
            try {
                // Check if settings exist
                $stmt = $conn->prepare("SELECT * FROM StudentSecuritySettings WHERE StudentID = ?");
                $stmt->execute([$user_id]);
                
                if ($stmt->rowCount() > 0) {
                    // Update existing settings
                    $stmt = $conn->prepare("UPDATE StudentSecuritySettings SET 
                        TwoFactorEnabled = ?, 
                        EmailNotifications = ?, 
                        LoginNotifications = ?,
                        LastUpdated = CURRENT_TIMESTAMP
                        WHERE StudentID = ?");
                    $stmt->execute([$two_factor_enabled, $email_notifications, $login_notifications, $user_id]);
                } else {
                    // Insert new settings
                    $stmt = $conn->prepare("INSERT INTO StudentSecuritySettings 
                        (StudentID, TwoFactorEnabled, EmailNotifications, LoginNotifications) 
                        VALUES (?, ?, ?, ?)");
                    $stmt->execute([$user_id, $two_factor_enabled, $email_notifications, $login_notifications]);
                }
                
                $message = 'Security settings updated successfully.';
                logUserActivity($user_id, 'Updated security settings');
            } catch (PDOException $e) {
                $error = 'Error updating security settings.';
                logUserActivity($user_id, 'Failed to update security settings: ' . $e->getMessage());
            }
        }
        
        // Handle password change
        if (isset($_POST['change_password'])) {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if ($new_password !== $confirm_password) {
                $error = 'New passwords do not match.';
            } elseif (!validatePassword($new_password)) {
                $error = 'Password does not meet security requirements.';
            } else {
                try {
                    // Verify current password
                    $stmt = $conn->prepare("SELECT Password FROM Students WHERE StudentID = ?");
                    $stmt->execute([$user_id]);
                    $student = $stmt->fetch();
                    
                    if ($student && password_verify($current_password, $student['Password'])) {
                        // Update password
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE Students SET Password = ? WHERE StudentID = ?");
                        $stmt->execute([$hashed_password, $user_id]);
                        
                        $message = 'Password updated successfully.';
                        logUserActivity($user_id, 'Changed password');
                    } else {
                        $error = 'Current password is incorrect.';
                        logUserActivity($user_id, 'Failed password change attempt');
                    }
                } catch (PDOException $e) {
                    $error = 'Error changing password.';
                    logUserActivity($user_id, 'Failed to change password: ' . $e->getMessage());
                }
            }
        }
    }
}

// Fetch current security settings
try {
    $stmt = $conn->prepare("SELECT * FROM StudentSecuritySettings WHERE StudentID = ?");
    $stmt->execute([$user_id]);
    $settings = $stmt->fetch();
    
    // Set default values if no settings exist
    if (!$settings) {
        $settings = [
            'TwoFactorEnabled' => 0,
            'EmailNotifications' => 1,
            'LoginNotifications' => 1
        ];
    }
} catch (PDOException $e) {
    $error = 'Error fetching security settings.';
    logUserActivity($user_id, 'Failed to fetch security settings: ' . $e->getMessage());
}

// Fetch recent activity logs
try {
    $stmt = $conn->prepare("SELECT * FROM UserActivityLog 
        WHERE UserID = ? 
        ORDER BY Timestamp DESC 
        LIMIT 10");
    $stmt->execute([$user_id]);
    $activity_logs = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Error fetching activity logs.';
    logUserActivity($user_id, 'Failed to fetch activity logs: ' . $e->getMessage());
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Settings - Student Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/student_navbar.php'; ?>
    
    <div class="container mt-4">
        <h2>Security Settings</h2>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Security Preferences</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="two_factor_enabled" 
                                        name="two_factor_enabled" <?php echo $settings['TwoFactorEnabled'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="two_factor_enabled">Two-Factor Authentication</label>
                                </div>
                                <small class="text-muted">Add an extra layer of security to your account</small>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="email_notifications" 
                                        name="email_notifications" <?php echo $settings['EmailNotifications'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="email_notifications">Email Notifications</label>
                                </div>
                                <small class="text-muted">Receive security alerts via email</small>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="login_notifications" 
                                        name="login_notifications" <?php echo $settings['LoginNotifications'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="login_notifications">Login Notifications</label>
                                </div>
                                <small class="text-muted">Get notified of new sign-ins to your account</small>
                            </div>
                            
                            <button type="submit" name="update_settings" class="btn btn-primary">Update Settings</button>
                        </form>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Change Password</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="passwordForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                <div id="passwordStrength" class="mt-2"></div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            
                            <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($activity_logs)): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Action</th>
                                            <th>IP Address</th>
                                            <th>Timestamp</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($activity_logs as $log): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($log['Action']); ?></td>
                                                <td><?php echo htmlspecialchars($log['IPAddress']); ?></td>
                                                <td><?php echo date('Y-m-d H:i:s', strtotime($log['Timestamp'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No recent activity found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password strength checker
        document.getElementById('new_password').addEventListener('input', function() {
            const password = this.value;
            const strengthDiv = document.getElementById('passwordStrength');
            let strength = 0;
            let message = '';
            
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            switch(strength) {
                case 0:
                case 1:
                    message = '<span class="text-danger">Very Weak</span>';
                    break;
                case 2:
                    message = '<span class="text-warning">Weak</span>';
                    break;
                case 3:
                    message = '<span class="text-info">Medium</span>';
                    break;
                case 4:
                    message = '<span class="text-primary">Strong</span>';
                    break;
                case 5:
                    message = '<span class="text-success">Very Strong</span>';
                    break;
            }
            
            strengthDiv.innerHTML = 'Password Strength: ' + message;
        });
        
        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html> 