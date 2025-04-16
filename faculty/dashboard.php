<?php
require_once '../config/database.php';
require_once '../config/security.php';

// Start session and check faculty role
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'FacultyRole') {
    header('Location: ../login.php');
    exit();
}

// Get faculty information
try {
    $stmt = $pdo->prepare("SELECT * FROM Faculty WHERE FacultyID = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $faculty = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get counts
    $courseStmt = $pdo->prepare("SELECT COUNT(*) as course_count FROM Courses WHERE FacultyID = ?");
    $courseStmt->execute([$_SESSION['user_id']]);
    $courseCount = $courseStmt->fetch(PDO::FETCH_ASSOC)['course_count'];

    $studentStmt = $pdo->prepare("SELECT COUNT(DISTINCT sc.StudentID) as student_count 
                                 FROM StudentCourses sc 
                                 JOIN Courses c ON sc.CourseID = c.CourseID 
                                 WHERE c.FacultyID = ?");
    $studentStmt->execute([$_SESSION['user_id']]);
    $studentCount = $studentStmt->fetch(PDO::FETCH_ASSOC)['student_count'];

    $assignmentStmt = $pdo->prepare("SELECT COUNT(*) as assignment_count 
                                    FROM Assignments a 
                                    JOIN Courses c ON a.CourseID = c.CourseID 
                                    WHERE c.FacultyID = ?");
    $assignmentStmt->execute([$_SESSION['user_id']]);
    $assignmentCount = $assignmentStmt->fetch(PDO::FETCH_ASSOC)['assignment_count'];
} catch (PDOException $e) {
    error_log("Error fetching faculty data: " . $e->getMessage());
    $faculty = null;
    $courseCount = 0;
    $studentCount = 0;
    $assignmentCount = 0;
}

include '../includes/faculty_header.php';
?>

<div class="container mt-4">
    <div class="card mb-4">
        <div class="card-body">
            <h2>Welcome, <?php echo htmlspecialchars($faculty['FullName'] ?? 'Faculty'); ?>!</h2>
            <p class="text-muted">Faculty ID: <?php echo htmlspecialchars($_SESSION['user_id']); ?></p>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h3>My Courses</h3>
                    <h1><?php echo $courseCount; ?></h1>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h3>Students</h3>
                    <h1><?php echo $studentCount; ?></h1>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h3>Assignments</h3>
                    <h1><?php echo $assignmentCount; ?></h1>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h3>My Courses</h3>
                    <p>View and manage your assigned courses.</p>
                    <a href="my_courses.php" class="btn btn-primary">View Courses</a>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h3>Grades</h3>
                    <p>Manage student grades for your courses.</p>
                    <a href="grades.php" class="btn btn-primary">Manage Grades</a>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h3>Attendance</h3>
                    <p>Manage student attendance for your courses.</p>
                    <a href="attendance.php" class="btn btn-primary">Manage Attendance</a>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h3>Course Materials</h3>
                    <p>Upload and manage course materials.</p>
                    <a href="course_materials.php" class="btn btn-primary">Manage Materials</a>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h3>Assignments</h3>
                    <p>Create and manage course assignments.</p>
                    <a href="assignments.php" class="btn btn-primary">Manage Assignments</a>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h3>Security Settings</h3>
                    <p>Manage your account security settings.</p>
                    <a href="security_settings.php" class="btn btn-primary">Security Settings</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/faculty_footer.php'; ?> 