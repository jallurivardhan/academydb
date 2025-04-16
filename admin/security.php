<?php
require_once '../config/database.php';
require_once '../config/security.php';

// Start session and check admin role
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'AdminRole') {
    header('Location: ../login.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Update security settings
        $stmt = $pdo->prepare("
            UPDATE SecuritySettings 
            SET min_password_length = ?,
                require_special_chars = ?,
                require_numbers = ?,
                require_uppercase = ?,
                session_timeout = ?,
                max_login_attempts = ?
            WHERE setting_id = 1
        ");
        
        $stmt->execute([
            $_POST['min_password_length'],
            isset($_POST['require_special_chars']) ? 1 : 0,
            isset($_POST['require_numbers']) ? 1 : 0,
            isset($_POST['require_uppercase']) ? 1 : 0,
            $_POST['session_timeout'],
            $_POST['max_login_attempts']
        ]);
        
        $_SESSION['success_message'] = "Security settings updated successfully!";
    } catch (PDOException $e) {
        error_log("Error updating security settings: " . $e->getMessage());
        $_SESSION['error_message'] = "Error updating security settings. Please try again.";
    }
    
    header('Location: security.php');
    exit();
}

// Fetch current security settings
try {
    $stmt = $pdo->query("SELECT * FROM SecuritySettings WHERE setting_id = 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching security settings: " . $e->getMessage());
    $settings = null;
}

// Fetch recent security logs
try {
    $stmt = $pdo->query("
        SELECT * FROM SecurityLogs 
        ORDER BY log_time DESC 
        LIMIT 50
    ");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching security logs: " . $e->getMessage());
    $logs = [];
}

include '../includes/admin_header.php';
?>

<div class="container mt-4">
    <h2>Security Management</h2>
    
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <?php 
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
            ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger">
            <?php 
                echo $_SESSION['error_message'];
                unset($_SESSION['error_message']);
            ?>
        </div>
    <?php endif; ?>

    <?php if ($settings): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h3>Password Policy</h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="mb-3">
                    <label for="min_password_length" class="form-label">Minimum Password Length</label>
                    <input type="number" class="form-control" id="min_password_length" name="min_password_length" 
                           value="<?php echo htmlspecialchars($settings['min_password_length']); ?>" required min="8" max="32">
                </div>
                
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="require_special_chars" name="require_special_chars"
                           <?php echo $settings['require_special_chars'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="require_special_chars">Require Special Characters</label>
                </div>
                
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="require_numbers" name="require_numbers"
                           <?php echo $settings['require_numbers'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="require_numbers">Require Numbers</label>
                </div>
                
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="require_uppercase" name="require_uppercase"
                           <?php echo $settings['require_uppercase'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="require_uppercase">Require Uppercase Letters</label>
                </div>
                
                <div class="mb-3">
                    <label for="session_timeout" class="form-label">Session Timeout (minutes)</label>
                    <input type="number" class="form-control" id="session_timeout" name="session_timeout"
                           value="<?php echo htmlspecialchars($settings['session_timeout']); ?>" required min="5" max="180">
                </div>
                
                <div class="mb-3">
                    <label for="max_login_attempts" class="form-label">Maximum Login Attempts</label>
                    <input type="number" class="form-control" id="max_login_attempts" name="max_login_attempts"
                           value="<?php echo htmlspecialchars($settings['max_login_attempts']); ?>" required min="3" max="10">
                </div>
                
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>
        </div>
    </div>
    <?php else: ?>
        <div class="alert alert-danger">
            Error loading security settings. Please try refreshing the page.
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3>Security Logs</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>User ID</th>
                            <th>Action</th>
                            <th>IP Address</th>
                            <th>Status</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($log['log_time']); ?></td>
                                <td><?php echo htmlspecialchars($log['user_id']); ?></td>
                                <td><?php echo htmlspecialchars($log['action']); ?></td>
                                <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $log['status'] === 'success' ? 'success' : 'danger'; ?>">
                                        <?php echo htmlspecialchars($log['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($log['details']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="6" class="text-center">No security logs found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?> 