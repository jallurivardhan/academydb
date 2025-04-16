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
$courses = [];

try {
    // Get student's courses
    $stmt = $pdo->prepare("
        SELECT c.*, r.Grade, f.FullName as FacultyName
        FROM Courses c
        JOIN Results r ON c.CourseCode = r.CourseCode
        JOIN Faculty f ON r.FacultyID = f.FacultyID
        WHERE r.StudentID = ?
        ORDER BY c.CourseCode
    ");
    $stmt->execute([$userId]);
    $courses = $stmt->fetchAll();

} catch(PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - AcademyDB Management System</title>
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
                        <a class="nav-link active" href="my_courses.php">My Courses</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="grades.php">Grades</a>
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
        <h2>My Courses</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if (empty($courses)): ?>
            <div class="alert alert-info">You are not currently enrolled in any courses.</div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($courses as $course): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($course['CourseCode']); ?></h5>
                                <h6 class="card-subtitle mb-2 text-muted"><?php echo htmlspecialchars($course['CourseTitle']); ?></h6>
                                <p class="card-text">
                                    <strong>Credits:</strong> <?php echo htmlspecialchars($course['Credits']); ?><br>
                                    <strong>Level:</strong> <?php echo htmlspecialchars($course['CourseLevel']); ?><br>
                                    <strong>Faculty:</strong> <?php echo htmlspecialchars($course['FacultyName']); ?><br>
                                    <strong>Grade:</strong> <?php echo $course['Grade'] ? htmlspecialchars($course['Grade']) : 'Not graded'; ?>
                                </p>
                                <div class="btn-group">
                                    <a href="grades.php?course=<?php echo urlencode($course['CourseCode']); ?>" class="btn btn-primary">View Grade</a>
                                    <a href="attendance.php?course=<?php echo urlencode($course['CourseCode']); ?>" class="btn btn-secondary">View Attendance</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 