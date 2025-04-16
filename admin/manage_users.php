<?php
session_start();
require_once '../config/db_connect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'AdminRole') {
    header("Location: ../login.php");
    exit();
}

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $username = $_POST['username'] ?? '';
                $password = $_POST['password'] ?? '';
                $role = $_POST['role'] ?? '';
                $fullName = $_POST['full_name'] ?? '';
                $email = $_POST['email'] ?? '';
                $contact = $_POST['contact'] ?? '';

                if (empty($username) || empty($password) || empty($role) || empty($fullName)) {
                    $error = "All required fields must be filled out.";
                } else {
                    try {
                        // Begin transaction
                        $pdo->beginTransaction();

                        // Insert into SystemUsers
                        $stmt = $pdo->prepare("INSERT INTO SystemUsers (UserName, UserPassword, CreatedOn) VALUES (?, SHA2(?, 256), NOW())");
                        $stmt->execute([$username, $password]);
                        $userId = $pdo->lastInsertId();

                        // Insert into role-specific table
                        switch ($role) {
                            case 'AdminRole':
                                $stmt = $pdo->prepare("INSERT INTO Admin (AdminID, FullName, Email, Contact, AdminStatus) VALUES (?, ?, ?, ?, 'Active')");
                                break;
                            case 'FacultyRole':
                                $stmt = $pdo->prepare("INSERT INTO Faculty (FacultyID, FullName, Email, Contact, Dept, FacultyStatus) VALUES (?, ?, ?, ?, 'General', 'Active')");
                                break;
                            case 'StudentRole':
                                $stmt = $pdo->prepare("INSERT INTO Students (StudentID, FullName, Email, Contact, StudentStatus) VALUES (?, ?, ?, ?, 'Active')");
                                break;
                        }
                        $stmt->execute([$userId, $fullName, $email, $contact]);

                        $pdo->commit();
                        $message = "User added successfully!";
                    } catch (PDOException $e) {
                        $pdo->rollBack();
                        $error = "Error adding user: " . $e->getMessage();
                    }
                }
                break;

            case 'delete':
                $userId = $_POST['user_id'] ?? '';
                if ($userId) {
                    try {
                        $pdo->beginTransaction();

                        // Delete from role-specific tables first
                        $pdo->exec("DELETE FROM Admin WHERE AdminID = $userId");
                        $pdo->exec("DELETE FROM Faculty WHERE FacultyID = $userId");
                        $pdo->exec("DELETE FROM Students WHERE StudentID = $userId");

                        // Delete from SystemUsers
                        $stmt = $pdo->prepare("DELETE FROM SystemUsers WHERE UserID = ?");
                        $stmt->execute([$userId]);

                        $pdo->commit();
                        $message = "User deleted successfully!";
                    } catch (PDOException $e) {
                        $pdo->rollBack();
                        $error = "Error deleting user: " . $e->getMessage();
                    }
                }
                break;
        }
    }
}

// Fetch all users with their roles
try {
    $users = [];
    
    // Get admin users
    $stmt = $pdo->query("
        SELECT 
            su.UserID,
            su.UserName,
            su.LastLogin,
            a.FullName,
            a.Email,
            a.Contact,
            'AdminRole' as Role,
            a.AdminStatus as Status
        FROM SystemUsers su
        JOIN Admin a ON su.UserID = a.AdminID
    ");
    $users = array_merge($users, $stmt->fetchAll());

    // Get faculty users
    $stmt = $pdo->query("
        SELECT 
            su.UserID,
            su.UserName,
            su.LastLogin,
            f.FullName,
            f.Email,
            f.Contact,
            'FacultyRole' as Role,
            f.FacultyStatus as Status
        FROM SystemUsers su
        JOIN Faculty f ON su.UserID = f.FacultyID
    ");
    $users = array_merge($users, $stmt->fetchAll());

    // Get student users
    $stmt = $pdo->query("
        SELECT 
            su.UserID,
            su.UserName,
            su.LastLogin,
            s.FullName,
            s.Email,
            s.Contact,
            'StudentRole' as Role,
            s.StudentStatus as Status
        FROM SystemUsers su
        JOIN Students s ON su.UserID = s.StudentID
    ");
    $users = array_merge($users, $stmt->fetchAll());

} catch (PDOException $e) {
    $error = "Error fetching users: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - AcademyDB Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../index.php">AcademyDB Management System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="manage_users.php">Manage Users</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="../logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Manage Users</h2>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Add User Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Add New User</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="manage_users.php">
                    <input type="hidden" name="action" value="add">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="role" class="form-label">Role</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="">Select Role</option>
                                    <option value="AdminRole">Admin</option>
                                    <option value="FacultyRole">Faculty</option>
                                    <option value="StudentRole">Student</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="contact" class="form-label">Contact</label>
                                <input type="text" class="form-control" id="contact" name="contact">
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </form>
            </div>
        </div>

        <!-- Users List -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Users List</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Role</th>
                                <th>Email</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['UserName']); ?></td>
                                    <td><?php echo htmlspecialchars($user['FullName']); ?></td>
                                    <td><?php echo htmlspecialchars($user['Role']); ?></td>
                                    <td><?php echo htmlspecialchars($user['Email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['Contact']); ?></td>
                                    <td><?php echo htmlspecialchars($user['Status']); ?></td>
                                    <td><?php echo $user['LastLogin'] ? htmlspecialchars($user['LastLogin']) : 'Never'; ?></td>
                                    <td>
                                        <form method="POST" action="manage_users.php" style="display: inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="user_id" value="<?php echo $user['UserID']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this user?')">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 