<?php
session_start();
require_once 'config/db_connect.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['user_id'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        try {
            // Check in SystemUsers table
            $stmt = $pdo->prepare("SELECT UserID, UserName, UserPassword FROM SystemUsers WHERE UserName = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Verify password using SHA2 hash
                $hashedPassword = hash('sha256', $password);
                if ($hashedPassword === $user['UserPassword']) {
                    $_SESSION['user_id'] = $user['UserID'];
                    $_SESSION['user_name'] = $user['UserName'];
                    
                    // Get user role
                    $role = getUserRole($pdo, $user['UserID']);
                    $_SESSION['user_role'] = $role;
                    
                    // Update last login
                    $updateStmt = $pdo->prepare("UPDATE SystemUsers SET LastLogin = NOW() WHERE UserID = ?");
                    $updateStmt->execute([$user['UserID']]);
                    
                    header("Location: dashboard.php");
                    exit();
                }
            }
            
            $error = 'Invalid username or password.';
        } catch(PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - AcademyDB Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">AcademyDB Management System</a>
        </div>
    </nav>

    <div class="container">
        <div class="login-container">
            <h2 class="text-center mb-4">Login</h2>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="login.php">
                <div class="mb-3">
                    <label for="user_id" class="form-label">Username</label>
                    <input type="text" class="form-control" id="user_id" name="user_id" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Login</button>
                </div>
            </form>
            
            <div class="mt-4 text-center">
                <p><strong>Demo Credentials:</strong></p>
                <p>Admin: admin / admin123</p>
                <p>Faculty: faculty1 / faculty123</p>
                <p>Student: student1 / student123</p>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 