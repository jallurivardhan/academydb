<?php
session_start();
require_once '../config/database.php';
require_once '../config/security.php';

// Check if user is logged in and has FacultyRole
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'FacultyRole') {
    logUserActivity($_SESSION['user_id'] ?? 'unknown', 'unauthorized_access', 'Attempted to access faculty grades page', 'failed');
    header('Location: ../login.php');
    exit();
}

// Rate limiting check
if (!checkRateLimit($_SERVER['REMOTE_ADDR'], 'faculty_grades', 20, 300)) {
    logUserActivity($_SESSION['user_id'], 'rate_limit_exceeded', 'Too many attempts to access grades page', 'failed');
    header('Location: ../error.php?code=429');
    exit();
}

$error = '';
$success = '';
$selectedCourse = '';
$grades = [];
$courses = [];

// Fetch faculty's courses
try {
    $stmt = $pdo->prepare("
        SELECT c.CourseID, c.CourseCode, c.Title 
        FROM Courses c 
        WHERE c.FacultyID = ?
        ORDER BY c.CourseCode
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $courses = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    logUserActivity($_SESSION['user_id'], 'database_error', $e->getMessage(), 'failed');
}

// Handle grade updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        logUserActivity($_SESSION['user_id'], 'csrf_validation_failed', 'CSRF token validation failed on grade update', 'failed');
        $error = "Security validation failed. Please try again.";
    } else {
        if (isset($_POST['action'])) {
            try {
                $action = sanitizeInput($_POST['action']);
                
                switch ($action) {
                    case 'update_grade':
                        $studentId = sanitizeInput($_POST['student_id']);
                        $courseId = sanitizeInput($_POST['course_id']);
                        $grade = sanitizeInput($_POST['grade']);
                        
                        // Validate grade format
                        if (!preg_match('/^[A][+-]?|[B][+-]?|[C][+-]?|[D][+-]?|F$/', $grade)) {
                            throw new Exception("Invalid grade format");
                        }
                        
                        // Verify faculty owns the course
                        $stmt = $pdo->prepare("SELECT 1 FROM Courses WHERE CourseID = ? AND FacultyID = ?");
                        $stmt->execute([$courseId, $_SESSION['user_id']]);
                        if (!$stmt->fetch()) {
                            throw new Exception("Unauthorized grade update attempt");
                        }
                        
                        $stmt = $pdo->prepare("CALL sp_UpdateGrade(?, ?, ?)");
                        $stmt->execute([$studentId, $courseId, $grade]);
                        $success = "Grade updated successfully.";
                        logUserActivity($_SESSION['user_id'], 'update_grade', "Updated grade for student ID: $studentId, course ID: $courseId", 'success');
                        break;

                    case 'view_grades':
                        $selectedCourse = sanitizeInput($_POST['course_id']);
                        
                        // Verify faculty owns the course
                        $stmt = $pdo->prepare("SELECT 1 FROM Courses WHERE CourseID = ? AND FacultyID = ?");
                        $stmt->execute([$selectedCourse, $_SESSION['user_id']]);
                        if (!$stmt->fetch()) {
                            throw new Exception("Unauthorized grade view attempt");
                        }
                        
                        $stmt = $pdo->prepare("
                            SELECT s.StudentID, s.FullName, g.Grade, g.LastUpdated
                            FROM Students s
                            LEFT JOIN Grades g ON s.StudentID = g.StudentID AND g.CourseID = ?
                            WHERE s.Status = 'Active'
                            ORDER BY s.FullName
                        ");
                        $stmt->execute([$selectedCourse]);
                        $grades = $stmt->fetchAll();
                        logUserActivity($_SESSION['user_id'], 'view_grades', "Viewed grades for course ID: $selectedCourse", 'success');
                        break;
                }
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
    <title>Manage Grades - Faculty Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/faculty_navbar.php'; ?>

    <div class="container mt-4">
        <h2>Manage Grades</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Course Grades</h4>
                    </div>
                    <div class="card-body">
                        <form method="post" class="mb-4">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="view_grades">
                            
                            <div class="mb-3">
                                <label for="course_id" class="form-label">Select Course</label>
                                <select class="form-select" id="course_id" name="course_id" required>
                                    <option value="">Choose a course...</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo htmlspecialchars($course['CourseID']); ?>"
                                                <?php echo $selectedCourse == $course['CourseID'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($course['CourseCode'] . ' - ' . $course['Title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary">View Grades</button>
                        </form>

                        <?php if ($grades): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Student ID</th>
                                            <th>Name</th>
                                            <th>Grade</th>
                                            <th>Last Updated</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($grades as $grade): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($grade['StudentID']); ?></td>
                                                <td><?php echo htmlspecialchars($grade['FullName']); ?></td>
                                                <td><?php echo htmlspecialchars($grade['Grade'] ?? 'Not Set'); ?></td>
                                                <td><?php echo $grade['LastUpdated'] ? htmlspecialchars($grade['LastUpdated']) : 'Never'; ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-primary" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#editGradeModal"
                                                            data-student-id="<?php echo htmlspecialchars($grade['StudentID']); ?>"
                                                            data-student-name="<?php echo htmlspecialchars($grade['FullName']); ?>"
                                                            data-current-grade="<?php echo htmlspecialchars($grade['Grade'] ?? ''); ?>">
                                                        Edit Grade
                                                    </button>
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

    <!-- Edit Grade Modal -->
    <div class="modal fade" id="editGradeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Grade</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" onsubmit="return validateGradeForm(this);">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="update_grade">
                        <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($selectedCourse); ?>">
                        <input type="hidden" name="student_id" id="modal_student_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Student</label>
                            <p id="modal_student_name" class="form-control-plaintext"></p>
                        </div>

                        <div class="mb-3">
                            <label for="grade" class="form-label">Grade</label>
                            <select class="form-select" id="grade" name="grade" required>
                                <option value="">Select grade...</option>
                                <option value="A">A</option>
                                <option value="A-">A-</option>
                                <option value="B+">B+</option>
                                <option value="B">B</option>
                                <option value="B-">B-</option>
                                <option value="C+">C+</option>
                                <option value="C">C</option>
                                <option value="C-">C-</option>
                                <option value="D+">D+</option>
                                <option value="D">D</option>
                                <option value="F">F</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Grade</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle modal data
        const editGradeModal = document.getElementById('editGradeModal');
        if (editGradeModal) {
            editGradeModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const studentId = button.getAttribute('data-student-id');
                const studentName = button.getAttribute('data-student-name');
                const currentGrade = button.getAttribute('data-current-grade');
                
                document.getElementById('modal_student_id').value = studentId;
                document.getElementById('modal_student_name').textContent = studentName;
                document.getElementById('grade').value = currentGrade;
            });
        }

        // Client-side validation
        function validateGradeForm(form) {
            const gradeSelect = form.querySelector('select[name="grade"]');
            if (!gradeSelect.value) {
                alert('Please select a grade');
                return false;
            }
            return true;
        }

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html> 