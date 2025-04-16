<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_name'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];
$userRole = $_SESSION['user_role'] ?? null;

// Get user information
try {
    // Get basic user info from SystemUsers
    $stmt = $pdo->prepare("SELECT UserID, UserName, LastLogin FROM SystemUsers WHERE UserID = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        header("Location: logout.php");
        exit();
    }

    // Get additional role-specific information
    $roleInfo = null;
    if ($userRole === 'AdminRole') {
        $stmt = $pdo->prepare("SELECT * FROM Admin WHERE AdminID = ?");
        $stmt->execute([$userId]);
        $roleInfo = $stmt->fetch();
    } elseif ($userRole === 'FacultyRole') {
        $stmt = $pdo->prepare("SELECT * FROM Faculty WHERE FacultyID = ?");
        $stmt->execute([$userId]);
        $roleInfo = $stmt->fetch();
    } elseif ($userRole === 'StudentRole') {
        $stmt = $pdo->prepare("SELECT * FROM Students WHERE StudentID = ?");
        $stmt->execute([$userId]);
        $roleInfo = $stmt->fetch();
    }

} catch(PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Get counts for dashboard
try {
    $counts = [];
    
    $stmt = $pdo->query("SELECT COUNT(*) as student_count FROM Students WHERE StudentStatus = 'Active'");
    $counts['students'] = $stmt->fetch()['student_count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as faculty_count FROM Faculty WHERE FacultyStatus = 'Active'");
    $counts['faculty'] = $stmt->fetch()['faculty_count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as course_count FROM Courses");
    $counts['courses'] = $stmt->fetch()['course_count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as result_count FROM Results");
    $counts['results'] = $stmt->fetch()['result_count'];
} catch(PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - AcademyDB Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">AcademyDB Management System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">Dashboard</a>
                    </li>
                    <?php if ($userRole === 'AdminRole'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="admin/manage_users.php">Manage Users</a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="students.php">Students</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="faculty.php">Faculty</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="courses.php">Courses</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="results.php">Results</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <h2 class="card-title">Welcome, <?php echo htmlspecialchars($userName); ?>!</h2>
                        <p class="card-text">Role: <?php echo htmlspecialchars($userRole ?? 'Unknown'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dashboard Statistics -->
        <div class="row mt-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Students</h5>
                        <p class="card-text display-4"><?php echo $counts['students'] ?? 0; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Faculty</h5>
                        <p class="card-text display-4"><?php echo $counts['faculty'] ?? 0; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">Courses</h5>
                        <p class="card-text display-4"><?php echo $counts['courses'] ?? 0; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h5 class="card-title">Results</h5>
                        <p class="card-text display-4"><?php echo $counts['results'] ?? 0; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Role-specific Dashboard Content -->
        <div class="row mt-4">
            <?php if ($userRole === 'AdminRole'): ?>
                <!-- Admin Dashboard -->
                <div class="col-md-4">
                    <div class="card dashboard-card">
                        <div class="card-body">
                            <h5 class="card-title">Manage Users</h5>
                            <p class="card-text">Add, edit, or remove users from the system.</p>
                            <a href="admin/manage_users.php" class="btn btn-primary">Manage Users</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card dashboard-card">
                        <div class="card-body">
                            <h5 class="card-title">System Reports</h5>
                            <p class="card-text">View system-wide reports and statistics.</p>
                            <a href="admin/reports.php" class="btn btn-primary">View Reports</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card dashboard-card">
                        <div class="card-body">
                            <h5 class="card-title">Database Management</h5>
                            <p class="card-text">Manage database tables and settings.</p>
                            <a href="admin/database.php" class="btn btn-primary">Manage Database</a>
                        </div>
                    </div>
                </div>
            <?php elseif ($userRole === 'FacultyRole'): ?>
                <!-- Faculty Dashboard -->
                <div class="col-md-4">
                    <div class="card dashboard-card">
                        <div class="card-body">
                            <h5 class="card-title">My Courses</h5>
                            <p class="card-text">View and manage your assigned courses.</p>
                            <a href="faculty/my_courses.php" class="btn btn-primary">View Courses</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card dashboard-card">
                        <div class="card-body">
                            <h5 class="card-title">Grade Management</h5>
                            <p class="card-text">Enter and manage student grades.</p>
                            <a href="faculty/grades.php" class="btn btn-primary">Manage Grades</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card dashboard-card">
                        <div class="card-body">
                            <h5 class="card-title">Attendance</h5>
                            <p class="card-text">Mark and view student attendance.</p>
                            <a href="faculty/attendance.php" class="btn btn-primary">Manage Attendance</a>
                        </div>
                    </div>
                </div>
            <?php elseif ($userRole === 'StudentRole'): ?>
                <!-- Student Dashboard -->
                <div class="col-md-4">
                    <div class="card dashboard-card">
                        <div class="card-body">
                            <h5 class="card-title">My Courses</h5>
                            <p class="card-text">View your enrolled courses.</p>
                            <a href="student/my_courses.php" class="btn btn-primary">View Courses</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card dashboard-card">
                        <div class="card-body">
                            <h5 class="card-title">My Grades</h5>
                            <p class="card-text">View your grades and academic performance.</p>
                            <a href="student/grades.php" class="btn btn-primary">View Grades</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card dashboard-card">
                        <div class="card-body">
                            <h5 class="card-title">My Attendance</h5>
                            <p class="card-text">View your attendance records.</p>
                            <a href="student/attendance.php" class="btn btn-primary">View Attendance</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 