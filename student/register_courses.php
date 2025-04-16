<?php
session_start();
require_once '../config/database.php';
require_once '../config/security.php';

// Check if user is logged in and has StudentRole
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'StudentRole') {
    logUserActivity($_SESSION['user_id'] ?? 'unknown', 'unauthorized_access', 'Attempted to access student course registration', 'failed');
    header('Location: ../login.php');
    exit();
}

// Rate limiting check
if (!checkRateLimit($_SERVER['REMOTE_ADDR'], 'student_course_registration', 10, 300)) {
    logUserActivity($_SESSION['user_id'], 'rate_limit_exceeded', 'Too many attempts to access course registration', 'failed');
    header('Location: ../error.php?code=429');
    exit();
}

$error = '';
$success = '';
$enrolledCourses = [];
$availableCourses = [];

// Fetch student's enrolled courses
try {
    $stmt = $pdo->prepare("
        SELECT c.CourseID, c.CourseCode, c.Title, c.Credits, f.FullName as FacultyName
        FROM Enrollments e
        JOIN Courses c ON e.CourseID = c.CourseID
        JOIN Faculty f ON c.FacultyID = f.FacultyID
        WHERE e.StudentID = ? AND e.Status = 'Active'
        ORDER BY c.CourseCode
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $enrolledCourses = $stmt->fetchAll();

    // Fetch available courses (not enrolled)
    $stmt = $pdo->prepare("
        SELECT c.CourseID, c.CourseCode, c.Title, c.Credits, 
               fn_MaskEmail(f.FullName) as FacultyName
        FROM Courses c
        JOIN Faculty f ON c.FacultyID = f.FacultyID
        WHERE c.CourseID NOT IN (
            SELECT CourseID 
            FROM Enrollments 
            WHERE StudentID = ? AND Status = 'Active'
        )
        AND c.Status = 'Active'
        ORDER BY c.CourseCode
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $availableCourses = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    logUserActivity($_SESSION['user_id'], 'database_error', $e->getMessage(), 'failed');
}

// Handle course registration/dropping
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        logUserActivity($_SESSION['user_id'], 'csrf_validation_failed', 'CSRF token validation failed on course registration', 'failed');
        $error = "Security validation failed. Please try again.";
    } else {
        if (isset($_POST['action'])) {
            try {
                $action = sanitizeInput($_POST['action']);
                
                switch ($action) {
                    case 'register':
                        $courseId = sanitizeInput($_POST['course_id']);
                        
                        // Verify course exists and is active
                        $stmt = $pdo->prepare("SELECT 1 FROM Courses WHERE CourseID = ? AND Status = 'Active'");
                        $stmt->execute([$courseId]);
                        if (!$stmt->fetch()) {
                            throw new Exception("Invalid course selection");
                        }
                        
                        // Check if already enrolled
                        $stmt = $pdo->prepare("SELECT 1 FROM Enrollments WHERE StudentID = ? AND CourseID = ? AND Status = 'Active'");
                        $stmt->execute([$_SESSION['user_id'], $courseId]);
                        if ($stmt->fetch()) {
                            throw new Exception("Already enrolled in this course");
                        }
                        
                        // Check credit limit
                        $stmt = $pdo->prepare("
                            SELECT COALESCE(SUM(c.Credits), 0) as TotalCredits
                            FROM Enrollments e
                            JOIN Courses c ON e.CourseID = c.CourseID
                            WHERE e.StudentID = ? AND e.Status = 'Active'
                        ");
                        $stmt->execute([$_SESSION['user_id']]);
                        $currentCredits = $stmt->fetch()['TotalCredits'];
                        
                        $stmt = $pdo->prepare("SELECT Credits FROM Courses WHERE CourseID = ?");
                        $stmt->execute([$courseId]);
                        $newCourseCredits = $stmt->fetch()['Credits'];
                        
                        if (($currentCredits + $newCourseCredits) > 21) {
                            throw new Exception("Cannot exceed 21 credits per semester");
                        }
                        
                        $stmt = $pdo->prepare("CALL sp_EnrollStudent(?, ?)");
                        $stmt->execute([$_SESSION['user_id'], $courseId]);
                        $success = "Successfully registered for the course.";
                        logUserActivity($_SESSION['user_id'], 'course_registration', "Registered for course ID: $courseId", 'success');
                        break;

                    case 'drop':
                        $courseId = sanitizeInput($_POST['course_id']);
                        
                        // Verify enrollment exists
                        $stmt = $pdo->prepare("SELECT 1 FROM Enrollments WHERE StudentID = ? AND CourseID = ? AND Status = 'Active'");
                        $stmt->execute([$_SESSION['user_id'], $courseId]);
                        if (!$stmt->fetch()) {
                            throw new Exception("Not enrolled in this course");
                        }
                        
                        $stmt = $pdo->prepare("CALL sp_DropCourse(?, ?)");
                        $stmt->execute([$_SESSION['user_id'], $courseId]);
                        $success = "Successfully dropped the course.";
                        logUserActivity($_SESSION['user_id'], 'course_drop', "Dropped course ID: $courseId", 'success');
                        break;
                }
                
                // Refresh course lists
                header("Location: register_courses.php");
                exit();
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
                logUserActivity($_SESSION['user_id'], 'error', $e->getMessage(), 'failed');
            }
        }
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Registration - Student Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/student_navbar.php'; ?>

    <div class="container mt-4">
        <h2>Course Registration</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="row">
            <!-- Enrolled Courses -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4>Enrolled Courses</h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($enrolledCourses)): ?>
                            <p class="text-muted">No courses enrolled.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Course</th>
                                            <th>Credits</th>
                                            <th>Faculty</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($enrolledCourses as $course): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($course['CourseCode'] . ' - ' . $course['Title']); ?></td>
                                                <td><?php echo htmlspecialchars($course['Credits']); ?></td>
                                                <td><?php echo htmlspecialchars($course['FacultyName']); ?></td>
                                                <td>
                                                    <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to drop this course?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                        <input type="hidden" name="action" value="drop">
                                                        <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($course['CourseID']); ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm">Drop</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Available Courses -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4>Available Courses</h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($availableCourses)): ?>
                            <p class="text-muted">No available courses.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Course</th>
                                            <th>Credits</th>
                                            <th>Faculty</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($availableCourses as $course): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($course['CourseCode'] . ' - ' . $course['Title']); ?></td>
                                                <td><?php echo htmlspecialchars($course['Credits']); ?></td>
                                                <td><?php echo htmlspecialchars($course['FacultyName']); ?></td>
                                                <td>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                        <input type="hidden" name="action" value="register">
                                                        <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($course['CourseID']); ?>">
                                                        <button type="submit" class="btn btn-primary btn-sm">Register</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html> 