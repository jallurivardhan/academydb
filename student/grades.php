<?php
session_start();
require_once '../config/db_connect.php';

// Check if user is logged in and is student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'StudentRole') {
    header("Location: ../login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$error = '';
$success = '';
$results = [];

try {
    // Get student's grades
    $stmt = $pdo->prepare("
        SELECT r.*, c.CourseTitle, fn_MaskEmail(f.FullName) as FacultyName
        FROM Results r
        JOIN Courses c ON r.CourseCode = c.CourseCode
        JOIN Faculty f ON r.FacultyID = f.FacultyID
        WHERE r.StudentID = ?
        ORDER BY c.CourseCode
    ");
    $stmt->execute([$userId]);
    $results = $stmt->fetchAll();

} catch(PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Grades - AcademyDB Management System</title>
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
                        <a class="nav-link" href="my_courses.php">My Courses</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="grades.php">Grades</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="attendance.php">Attendance</a>
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
        <h2>My Grades</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if (empty($results)): ?>
            <div class="alert alert-info">No grades found.</div>
        <?php else: ?>
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Course Code</th>
                                    <th>Course Title</th>
                                    <th>Faculty</th>
                                    <th>Grade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results as $result): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($result['CourseCode']); ?></td>
                                        <td><?php echo htmlspecialchars($result['CourseTitle']); ?></td>
                                        <td><?php echo htmlspecialchars($result['FacultyName']); ?></td>
                                        <td><?php echo $result['Grade'] ? htmlspecialchars($result['Grade']) : 'Not graded'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 