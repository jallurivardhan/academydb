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
$selectedCourse = $_GET['course'] ?? '';
$attendance = [];

try {
    // Get student's courses
    $stmt = $pdo->prepare("
        SELECT DISTINCT c.CourseCode, c.CourseTitle
        FROM Courses c
        JOIN Results r ON c.CourseCode = r.CourseCode
        WHERE r.StudentID = ?
        ORDER BY c.CourseCode
    ");
    $stmt->execute([$userId]);
    $courses = $stmt->fetchAll();

    // If a course is selected, get attendance records
    if ($selectedCourse) {
        $stmt = $pdo->prepare("
            SELECT a.*, c.CourseTitle
            FROM Attendance a
            JOIN Courses c ON a.CourseCode = c.CourseCode
            WHERE a.StudentID = ? AND a.CourseCode = ?
            ORDER BY a.AttendanceDate DESC
        ");
        $stmt->execute([$userId, $selectedCourse]);
        $attendance = $stmt->fetchAll();
    }

} catch(PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Attendance - AcademyDB Management System</title>
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
                        <a class="nav-link" href="grades.php">Grades</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="attendance.php">Attendance</a>
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
        <h2>My Attendance</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Course Selection -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="attendance.php" class="row g-3">
                    <div class="col-md-6">
                        <label for="course" class="form-label">Select Course</label>
                        <select class="form-select" id="course" name="course" onchange="this.form.submit()">
                            <option value="">Choose a course...</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo htmlspecialchars($course['CourseCode']); ?>"
                                        <?php echo $selectedCourse === $course['CourseCode'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['CourseCode'] . ' - ' . $course['CourseTitle']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <!-- Attendance Records -->
        <?php if ($selectedCourse): ?>
            <?php if (empty($attendance)): ?>
                <div class="alert alert-info">No attendance records found for this course.</div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendance as $record): ?>
                                        <tr>
                                            <td><?php echo date('F j, Y', strtotime($record['AttendanceDate'])); ?></td>
                                            <td>
                                                <span class="badge <?php 
                                                    echo match($record['Status']) {
                                                        'Present' => 'bg-success',
                                                        'Absent' => 'bg-danger',
                                                        'Late' => 'bg-warning',
                                                        'Excused' => 'bg-info',
                                                        default => 'bg-secondary'
                                                    };
                                                ?>">
                                                    <?php echo htmlspecialchars($record['Status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 