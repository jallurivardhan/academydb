<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

// Initialize variables
$courses = [];
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add' && $userRole === 'AdminRole') {
            // Add new course
            $courseCode = $_POST['course_code'] ?? '';
            $courseTitle = $_POST['course_title'] ?? '';
            $credits = $_POST['credits'] ?? 3;
            $courseLevel = $_POST['course_level'] ?? 'Undergraduate';
            
            if (empty($courseCode) || empty($courseTitle)) {
                $error = 'Course Code and Course Title are required.';
            } else {
                try {
                    // Check if course code already exists
                    $stmt = $pdo->prepare("SELECT 1 FROM Courses WHERE CourseCode = ?");
                    $stmt->execute([$courseCode]);
                    if ($stmt->rowCount() > 0) {
                        $error = 'Course Code already exists.';
                    } else {
                        // Insert new course
                        $stmt = $pdo->prepare("INSERT INTO Courses (CourseCode, CourseTitle, Credits, CourseLevel) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$courseCode, $courseTitle, $credits, $courseLevel]);
                        $success = 'Course added successfully.';
                    }
                } catch(PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        } elseif ($_POST['action'] == 'edit' && $userRole === 'AdminRole') {
            // Edit course
            $courseCode = $_POST['course_code'] ?? '';
            $courseTitle = $_POST['course_title'] ?? '';
            $credits = $_POST['credits'] ?? 3;
            $courseLevel = $_POST['course_level'] ?? 'Undergraduate';
            
            if (empty($courseCode) || empty($courseTitle)) {
                $error = 'Course Code and Course Title are required.';
            } else {
                try {
                    // Update course
                    $stmt = $pdo->prepare("UPDATE Courses SET CourseTitle = ?, Credits = ?, CourseLevel = ? WHERE CourseCode = ?");
                    $stmt->execute([$courseTitle, $credits, $courseLevel, $courseCode]);
                    $success = 'Course updated successfully.';
                } catch(PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        } elseif ($_POST['action'] == 'delete' && $userRole === 'AdminRole') {
            // Delete course
            $courseCode = $_POST['course_code'] ?? '';
            
            if (empty($courseCode)) {
                $error = 'Course Code is required.';
            } else {
                try {
                    // Delete course
                    $stmt = $pdo->prepare("DELETE FROM Courses WHERE CourseCode = ?");
                    $stmt->execute([$courseCode]);
                    $success = 'Course deleted successfully.';
                } catch(PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
    }
}

// Fetch courses based on user role
try {
    if ($userRole === 'AdminRole') {
        // Admin can see all courses
        $stmt = $pdo->query("SELECT * FROM Courses ORDER BY CourseCode");
        $courses = $stmt->fetchAll();
    } elseif ($userRole === 'FacultyRole') {
        // Faculty can see courses they teach
        $stmt = $pdo->prepare("
            SELECT DISTINCT c.* 
            FROM Courses c
            JOIN Results r ON c.CourseCode = r.CourseCode
            WHERE r.FacultyID = ?
            ORDER BY c.CourseCode
        ");
        $stmt->execute([$userId]);
        $courses = $stmt->fetchAll();
    } elseif ($userRole === 'StudentRole') {
        // Students can see courses they are enrolled in
        $stmt = $pdo->prepare("
            SELECT DISTINCT c.* 
            FROM Courses c
            JOIN Results r ON c.CourseCode = r.CourseCode
            WHERE r.StudentID = ?
            ORDER BY c.CourseCode
        ");
        $stmt->execute([$userId]);
        $courses = $stmt->fetchAll();
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
    <title>Courses - AcademyDB Management System</title>
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
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
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
                        <a class="nav-link active" href="courses.php">Courses</a>
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
                        <h2 class="card-title">Courses</h2>
                        
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($userRole === 'AdminRole'): ?>
                            <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                                Add New Course
                            </button>
                        <?php endif; ?>
                        
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Course Code</th>
                                        <th>Course Title</th>
                                        <th>Credits</th>
                                        <th>Course Level</th>
                                        <th>Created On</th>
                                        <?php if ($userRole === 'AdminRole'): ?>
                                            <th>Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($courses)): ?>
                                        <tr>
                                            <td colspan="<?php echo $userRole === 'AdminRole' ? '6' : '5'; ?>" class="text-center">No courses found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($courses as $course): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($course['CourseCode']); ?></td>
                                                <td><?php echo htmlspecialchars($course['CourseTitle']); ?></td>
                                                <td><?php echo htmlspecialchars($course['Credits']); ?></td>
                                                <td><?php echo htmlspecialchars($course['CourseLevel']); ?></td>
                                                <td><?php echo htmlspecialchars($course['SysStart']); ?></td>
                                                <?php if ($userRole === 'AdminRole'): ?>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editCourseModal<?php echo $course['CourseCode']; ?>">
                                                            Edit
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteCourseModal<?php echo $course['CourseCode']; ?>">
                                                            Delete
                                                        </button>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                            
                                            <?php if ($userRole === 'AdminRole'): ?>
                                                <!-- Edit Course Modal -->
                                                <div class="modal fade" id="editCourseModal<?php echo $course['CourseCode']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Edit Course</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <form method="POST" action="courses.php">
                                                                <div class="modal-body">
                                                                    <input type="hidden" name="action" value="edit">
                                                                    <input type="hidden" name="course_code" value="<?php echo $course['CourseCode']; ?>">
                                                                    
                                                                    <div class="mb-3">
                                                                        <label for="course_title<?php echo $course['CourseCode']; ?>" class="form-label">Course Title</label>
                                                                        <input type="text" class="form-control" id="course_title<?php echo $course['CourseCode']; ?>" name="course_title" value="<?php echo htmlspecialchars($course['CourseTitle']); ?>" required>
                                                                    </div>
                                                                    
                                                                    <div class="mb-3">
                                                                        <label for="credits<?php echo $course['CourseCode']; ?>" class="form-label">Credits</label>
                                                                        <input type="number" class="form-control" id="credits<?php echo $course['CourseCode']; ?>" name="credits" value="<?php echo htmlspecialchars($course['Credits']); ?>" min="1" max="6">
                                                                    </div>
                                                                    
                                                                    <div class="mb-3">
                                                                        <label for="course_level<?php echo $course['CourseCode']; ?>" class="form-label">Course Level</label>
                                                                        <select class="form-select" id="course_level<?php echo $course['CourseCode']; ?>" name="course_level">
                                                                            <option value="Undergraduate" <?php echo $course['CourseLevel'] === 'Undergraduate' ? 'selected' : ''; ?>>Undergraduate</option>
                                                                            <option value="Graduate" <?php echo $course['CourseLevel'] === 'Graduate' ? 'selected' : ''; ?>>Graduate</option>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Delete Course Modal -->
                                                <div class="modal fade" id="deleteCourseModal<?php echo $course['CourseCode']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Delete Course</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p>Are you sure you want to delete course <strong><?php echo htmlspecialchars($course['CourseTitle']); ?></strong>?</p>
                                                                <p class="text-danger">This action cannot be undone.</p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <form method="POST" action="courses.php">
                                                                    <input type="hidden" name="action" value="delete">
                                                                    <input type="hidden" name="course_code" value="<?php echo $course['CourseCode']; ?>">
                                                                    <button type="submit" class="btn btn-danger">Delete</button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($userRole === 'AdminRole'): ?>
        <!-- Add Course Modal -->
        <div class="modal fade" id="addCourseModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Course</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="courses.php">
                        <div class="modal-body">
                            <input type="hidden" name="action" value="add">
                            
                            <div class="mb-3">
                                <label for="course_code" class="form-label">Course Code</label>
                                <input type="text" class="form-control" id="course_code" name="course_code" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="course_title" class="form-label">Course Title</label>
                                <input type="text" class="form-control" id="course_title" name="course_title" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="credits" class="form-label">Credits</label>
                                <input type="number" class="form-control" id="credits" name="credits" value="3" min="1" max="6">
                            </div>
                            
                            <div class="mb-3">
                                <label for="course_level" class="form-label">Course Level</label>
                                <select class="form-select" id="course_level" name="course_level">
                                    <option value="Undergraduate" selected>Undergraduate</option>
                                    <option value="Graduate">Graduate</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Add Course</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 