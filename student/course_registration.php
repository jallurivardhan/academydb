<?php
session_start();
require_once '../config/database.php';
require_once '../config/security.php';

// Check if user is logged in and has StudentRole
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'StudentRole') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle course registration/drop
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $course_id = $_POST['course_id'] ?? '';
        
        if (isset($_POST['register_course'])) {
            try {
                // Check if already enrolled
                $stmt = $conn->prepare("SELECT 1 FROM Enrollments WHERE StudentID = ? AND CourseID = ?");
                $stmt->execute([$user_id, $course_id]);
                if ($stmt->fetch()) {
                    $error = 'You are already enrolled in this course.';
                } else {
                    // Check course capacity
                    $stmt = $conn->prepare("
                        SELECT c.MaxCapacity, COUNT(e.EnrollmentID) as CurrentEnrollments
                        FROM Courses c
                        LEFT JOIN Enrollments e ON c.CourseID = e.CourseID
                        WHERE c.CourseID = ?
                        GROUP BY c.CourseID, c.MaxCapacity
                    ");
                    $stmt->execute([$course_id]);
                    $course = $stmt->fetch();
                    
                    if ($course && $course['CurrentEnrollments'] < $course['MaxCapacity']) {
                        // Register for course
                        $stmt = $conn->prepare("INSERT INTO Enrollments (StudentID, CourseID, EnrollmentDate) VALUES (?, ?, CURRENT_TIMESTAMP)");
                        $stmt->execute([$user_id, $course_id]);
                        
                        $message = 'Successfully registered for the course.';
                        logUserActivity($user_id, 'Registered for course ' . $course_id);
                    } else {
                        $error = 'Course has reached maximum capacity.';
                    }
                }
            } catch (PDOException $e) {
                $error = 'Error registering for course.';
                logUserActivity($user_id, 'Failed to register for course: ' . $e->getMessage());
            }
        } elseif (isset($_POST['drop_course'])) {
            try {
                // Check if within drop period
                $stmt = $conn->prepare("
                    SELECT 1 FROM Enrollments e
                    JOIN Courses c ON e.CourseID = c.CourseID
                    WHERE e.StudentID = ? AND e.CourseID = ?
                    AND CURRENT_TIMESTAMP <= DATE_ADD(c.StartDate, INTERVAL 2 WEEK)
                ");
                $stmt->execute([$user_id, $course_id]);
                
                if ($stmt->fetch()) {
                    // Drop course
                    $stmt = $conn->prepare("DELETE FROM Enrollments WHERE StudentID = ? AND CourseID = ?");
                    $stmt->execute([$user_id, $course_id]);
                    
                    $message = 'Successfully dropped the course.';
                    logUserActivity($user_id, 'Dropped course ' . $course_id);
                } else {
                    $error = 'Course drop period has ended.';
                }
            } catch (PDOException $e) {
                $error = 'Error dropping course.';
                logUserActivity($user_id, 'Failed to drop course: ' . $e->getMessage());
            }
        }
    }
}

// Fetch student's current courses
try {
    $stmt = $conn->prepare("
        SELECT c.*, f.FullName as FacultyName, e.EnrollmentDate,
               (SELECT COUNT(*) FROM Enrollments WHERE CourseID = c.CourseID) as CurrentEnrollments
        FROM Courses c
        JOIN Faculty f ON c.FacultyID = f.FacultyID
        JOIN Enrollments e ON c.CourseID = e.CourseID
        WHERE e.StudentID = ?
        ORDER BY c.CourseName
    ");
    $stmt->execute([$user_id]);
    $enrolled_courses = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Error fetching enrolled courses.';
    logUserActivity($user_id, 'Failed to fetch enrolled courses: ' . $e->getMessage());
}

// Fetch available courses
try {
    $stmt = $conn->prepare("
        SELECT c.*, f.FullName as FacultyName,
               (SELECT COUNT(*) FROM Enrollments WHERE CourseID = c.CourseID) as CurrentEnrollments
        FROM Courses c
        JOIN Faculty f ON c.FacultyID = f.FacultyID
        WHERE c.CourseID NOT IN (
            SELECT CourseID FROM Enrollments WHERE StudentID = ?
        )
        AND c.StartDate > CURRENT_DATE
        ORDER BY c.StartDate
    ");
    $stmt->execute([$user_id]);
    $available_courses = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Error fetching available courses.';
    logUserActivity($user_id, 'Failed to fetch available courses: ' . $e->getMessage());
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Registration - Student Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        .course-card {
            transition: transform 0.2s;
        }
        .course-card:hover {
            transform: translateY(-5px);
        }
        .capacity-bar {
            height: 5px;
            margin-top: 10px;
        }
        .prerequisites {
            font-size: 0.9rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <?php include '../includes/student_navbar.php'; ?>
    
    <div class="container mt-4">
        <h2>Course Registration</h2>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- Current Courses -->
        <div class="card mb-4">
            <div class="card-header">
                <h4>My Current Courses</h4>
            </div>
            <div class="card-body">
                <?php if (empty($enrolled_courses)): ?>
                    <p class="text-muted">You are not enrolled in any courses.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Course Code</th>
                                    <th>Course Name</th>
                                    <th>Faculty</th>
                                    <th>Credits</th>
                                    <th>Schedule</th>
                                    <th>Enrolled</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($enrolled_courses as $course): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($course['CourseCode']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($course['CourseName']); ?>
                                            <?php if ($course['Prerequisites']): ?>
                                                <div class="prerequisites">
                                                    Prerequisites: <?php echo htmlspecialchars($course['Prerequisites']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($course['FacultyName']); ?></td>
                                        <td><?php echo htmlspecialchars($course['Credits']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($course['Schedule']); ?><br>
                                            <small class="text-muted">
                                                <?php echo date('M d, Y', strtotime($course['StartDate'])); ?> - 
                                                <?php echo date('M d, Y', strtotime($course['EndDate'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php echo $course['CurrentEnrollments']; ?>/<?php echo $course['MaxCapacity']; ?>
                                            <div class="progress capacity-bar">
                                                <div class="progress-bar" role="progressbar" 
                                                     style="width: <?php echo ($course['CurrentEnrollments'] / $course['MaxCapacity']) * 100; ?>%">
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (strtotime($course['StartDate']) > strtotime('+2 weeks')): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="course_id" value="<?php echo $course['CourseID']; ?>">
                                                    <button type="submit" name="drop_course" class="btn btn-danger btn-sm" 
                                                            onclick="return confirm('Are you sure you want to drop this course?')">
                                                        Drop Course
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Available Courses -->
        <div class="card">
            <div class="card-header">
                <h4>Available Courses</h4>
            </div>
            <div class="card-body">
                <?php if (empty($available_courses)): ?>
                    <p class="text-muted">No courses available for registration at this time.</p>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($available_courses as $course): ?>
                            <div class="col-md-6 mb-4">
                                <div class="card course-card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <?php echo htmlspecialchars($course['CourseCode']); ?> - 
                                            <?php echo htmlspecialchars($course['CourseName']); ?>
                                        </h5>
                                        <p class="card-text"><?php echo htmlspecialchars($course['Description'] ?? ''); ?></p>
                                        <ul class="list-unstyled">
                                            <li><strong>Faculty:</strong> <?php echo htmlspecialchars($course['FacultyName']); ?></li>
                                            <li><strong>Credits:</strong> <?php echo htmlspecialchars($course['Credits']); ?></li>
                                            <li><strong>Schedule:</strong> <?php echo htmlspecialchars($course['Schedule']); ?></li>
                                            <li>
                                                <strong>Duration:</strong>
                                                <?php echo date('M d, Y', strtotime($course['StartDate'])); ?> - 
                                                <?php echo date('M d, Y', strtotime($course['EndDate'])); ?>
                                            </li>
                                            <?php if ($course['Prerequisites']): ?>
                                                <li class="prerequisites">
                                                    <strong>Prerequisites:</strong> <?php echo htmlspecialchars($course['Prerequisites']); ?>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                        
                                        <div class="mt-3">
                                            Capacity: <?php echo $course['CurrentEnrollments']; ?>/<?php echo $course['MaxCapacity']; ?>
                                            <div class="progress capacity-bar">
                                                <div class="progress-bar <?php echo $course['CurrentEnrollments'] >= $course['MaxCapacity'] ? 'bg-danger' : ''; ?>" 
                                                     role="progressbar" 
                                                     style="width: <?php echo ($course['CurrentEnrollments'] / $course['MaxCapacity']) * 100; ?>%">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php if ($course['CurrentEnrollments'] < $course['MaxCapacity']): ?>
                                            <form method="POST" class="mt-3">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                <input type="hidden" name="course_id" value="<?php echo $course['CourseID']; ?>">
                                                <button type="submit" name="register_course" class="btn btn-primary">
                                                    Register for Course
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button class="btn btn-secondary mt-3" disabled>Course Full</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 