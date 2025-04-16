<?php
require_once '../config/database.php';
require_once '../config/security.php';

// Start session and check student role
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'StudentRole') {
    header('Location: ../login.php');
    exit();
}

// Get student's information
try {
    $stmt = $pdo->prepare("SELECT * FROM Students WHERE StudentID = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get counts
    $courseStmt = $pdo->prepare("SELECT COUNT(*) as course_count FROM StudentCourses WHERE StudentID = ?");
    $courseStmt->execute([$_SESSION['user_id']]);
    $courseCount = $courseStmt->fetch(PDO::FETCH_ASSOC)['course_count'];

    $assignmentStmt = $pdo->prepare("SELECT COUNT(*) as assignment_count FROM Assignments a 
                                    JOIN StudentCourses sc ON a.CourseID = sc.CourseID 
                                    WHERE sc.StudentID = ?");
    $assignmentStmt->execute([$_SESSION['user_id']]);
    $assignmentCount = $assignmentStmt->fetch(PDO::FETCH_ASSOC)['assignment_count'];

    $attendanceStmt = $pdo->prepare("SELECT COUNT(*) as attendance_count FROM Attendance 
                                    WHERE StudentID = ? AND IsPresent = 1");
    $attendanceStmt->execute([$_SESSION['user_id']]);
    $attendanceCount = $attendanceStmt->fetch(PDO::FETCH_ASSOC)['attendance_count'];
} catch (PDOException $e) {
    error_log("Error fetching student data: " . $e->getMessage());
    $student = null;
    $courseCount = 0;
    $assignmentCount = 0;
    $attendanceCount = 0;
}

include '../includes/student_header.php';
?>

<div class="container mt-4">
    <div class="card mb-4">
        <div class="card-body">
            <h2>Welcome, <?php echo htmlspecialchars($student['FullName'] ?? 'Student'); ?>!</h2>
            <p class="text-muted">Student ID: <?php echo htmlspecialchars($_SESSION['user_id']); ?></p>
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
                    <h3>Assignments</h3>
                    <h1><?php echo $assignmentCount; ?></h1>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h3>Attendance</h3>
                    <h1><?php echo $attendanceCount; ?></h1>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h3>Course Registration</h3>
                    <p>Register for new courses or view available courses.</p>
                    <a href="register_courses.php" class="btn btn-primary">Register Courses</a>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h3>My Courses</h3>
                    <p>View your enrolled courses and course materials.</p>
                    <a href="my_courses.php" class="btn btn-primary">View Courses</a>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h3>Grades</h3>
                    <p>View your grades for all courses.</p>
                    <a href="grades.php" class="btn btn-primary">View Grades</a>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h3>Attendance</h3>
                    <p>View your attendance records.</p>
                    <a href="attendance.php" class="btn btn-primary">View Attendance</a>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h3>Assignments</h3>
                    <p>View and submit your course assignments.</p>
                    <a href="assignments.php" class="btn btn-primary">View Assignments</a>
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

<?php include '../includes/student_footer.php'; ?> 