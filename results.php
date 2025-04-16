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
$results = [];
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add' && ($userRole === 'AdminRole' || $userRole === 'FacultyRole')) {
            // Add new result
            $studentId = $_POST['student_id'] ?? '';
            $courseCode = $_POST['course_code'] ?? '';
            $facultyId = $_POST['faculty_id'] ?? '';
            $grade = $_POST['grade'] ?? '';
            
            if (empty($studentId) || empty($courseCode) || empty($facultyId) || empty($grade)) {
                $error = 'All fields are required.';
            } else {
                try {
                    // Check if result already exists
                    $stmt = $pdo->prepare("SELECT 1 FROM Results WHERE StudentID = ? AND CourseCode = ?");
                    $stmt->execute([$studentId, $courseCode]);
                    if ($stmt->rowCount() > 0) {
                        $error = 'Result already exists for this student in this course.';
                    } else {
                        // Insert new result
                        $stmt = $pdo->prepare("INSERT INTO Results (StudentID, CourseCode, FacultyID, Grade) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$studentId, $courseCode, $facultyId, $grade]);
                        $success = 'Result added successfully.';
                    }
                } catch(PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        } elseif ($_POST['action'] == 'edit' && ($userRole === 'AdminRole' || $userRole === 'FacultyRole')) {
            // Edit result
            $resultId = $_POST['result_id'] ?? '';
            $grade = $_POST['grade'] ?? '';
            
            if (empty($resultId) || empty($grade)) {
                $error = 'Result ID and Grade are required.';
            } else {
                try {
                    // Update result
                    $stmt = $pdo->prepare("UPDATE Results SET Grade = ? WHERE ResultID = ?");
                    $stmt->execute([$grade, $resultId]);
                    $success = 'Result updated successfully.';
                } catch(PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        } elseif ($_POST['action'] == 'delete' && $userRole === 'AdminRole') {
            // Delete result
            $resultId = $_POST['result_id'] ?? '';
            
            if (empty($resultId)) {
                $error = 'Result ID is required.';
            } else {
                try {
                    // Delete result
                    $stmt = $pdo->prepare("DELETE FROM Results WHERE ResultID = ?");
                    $stmt->execute([$resultId]);
                    $success = 'Result deleted successfully.';
                } catch(PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
    }
}

// Fetch results based on user role
try {
    if ($userRole === 'AdminRole') {
        // Admin can see all results
        $stmt = $pdo->query("
            SELECT r.*, 
                   s.StudentID,
                   CASE 
                       WHEN s.StudentStatus = 'Active' THEN s.FullName 
                       ELSE fn_MaskEmail(s.FullName)
                   END as StudentName,
                   f.FacultyID,
                   f.FullName as FacultyName,
                   c.CourseTitle
            FROM Results r
            JOIN Students s ON r.StudentID = s.StudentID
            JOIN Faculty f ON r.FacultyID = f.FacultyID
            JOIN Courses c ON r.CourseCode = c.CourseCode
            ORDER BY c.CourseCode
        ");
        $results = $stmt->fetchAll();
    } elseif ($userRole === 'FacultyRole') {
        // Faculty can see results for courses they teach
        $stmt = $pdo->prepare("
            SELECT r.*, 
                   s.StudentID,
                   s.FullName as StudentName,
                   f.FacultyID,
                   f.FullName as FacultyName,
                   c.CourseTitle
            FROM Results r
            JOIN Students s ON r.StudentID = s.StudentID
            JOIN Faculty f ON r.FacultyID = f.FacultyID
            JOIN Courses c ON r.CourseCode = c.CourseCode
            WHERE r.FacultyID = ?
            ORDER BY c.CourseCode
        ");
        $stmt->execute([$userId]);
        $results = $stmt->fetchAll();
    } elseif ($userRole === 'StudentRole') {
        // Students can see their own results
        $stmt = $pdo->prepare("
            SELECT r.*, 
                   s.StudentID,
                   s.FullName as StudentName,
                   f.FacultyID,
                   fn_MaskEmail(f.FullName) as FacultyName,
                   c.CourseTitle
            FROM Results r
            JOIN Students s ON r.StudentID = s.StudentID
            JOIN Faculty f ON r.FacultyID = f.FacultyID
            JOIN Courses c ON r.CourseCode = c.CourseCode
            WHERE r.StudentID = ?
            ORDER BY c.CourseCode
        ");
        $stmt->execute([$userId]);
        $results = $stmt->fetchAll();
    }
} catch(PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Fetch students and courses for the add result form
$students = [];
$courses = [];
$faculty = [];

if ($userRole === 'AdminRole' || $userRole === 'FacultyRole') {
    try {
        // Get students with masking
        $stmt = $pdo->query("
            SELECT StudentID, 
                   CASE 
                       WHEN StudentStatus = 'Active' THEN FullName 
                       ELSE fn_MaskEmail(FullName)
                   END as FullName 
            FROM Students 
            ORDER BY FullName
        ");
        $students = $stmt->fetchAll();
        
        // Get courses
        $stmt = $pdo->query("SELECT CourseCode, CourseTitle FROM Courses ORDER BY CourseCode");
        $courses = $stmt->fetchAll();
        
        // Get faculty with masking for inactive faculty
        if ($userRole === 'AdminRole') {
            $stmt = $pdo->query("
                SELECT FacultyID, 
                       CASE 
                           WHEN FacultyStatus = 'Active' THEN FullName 
                           ELSE fn_MaskEmail(FullName)
                       END as FullName 
                FROM Faculty 
                ORDER BY FullName
            ");
            $faculty = $stmt->fetchAll();
        } else {
            $stmt = $pdo->prepare("SELECT FacultyID, FullName FROM Faculty WHERE FacultyID = ?");
            $stmt->execute([$userId]);
            $faculty = $stmt->fetchAll();
        }
    } catch(PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results - AcademyDB Management System</title>
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
                        <a class="nav-link" href="courses.php">Courses</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="results.php">Results</a>
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
                        <h2 class="card-title">Results</h2>
                        
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($userRole === 'AdminRole' || $userRole === 'FacultyRole'): ?>
                            <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addResultModal">
                                Add New Result
                            </button>
                        <?php endif; ?>
                        
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Course</th>
                                        <th>Faculty</th>
                                        <th>Grade</th>
                                        <?php if ($userRole === 'AdminRole' || $userRole === 'FacultyRole'): ?>
                                            <th>Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($results)): ?>
                                        <tr>
                                            <td colspan="<?php echo ($userRole === 'AdminRole' || $userRole === 'FacultyRole') ? '5' : '4'; ?>" class="text-center">No results found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($results as $result): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($result['StudentName']); ?></td>
                                                <td><?php echo htmlspecialchars($result['CourseTitle'] . ' (' . $result['CourseCode'] . ')'); ?></td>
                                                <td><?php echo htmlspecialchars($result['FacultyName']); ?></td>
                                                <td><?php echo htmlspecialchars($result['Grade']); ?></td>
                                                <?php if ($userRole === 'AdminRole' || $userRole === 'FacultyRole'): ?>
                                                    <td>
                                                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editResultModal<?php echo $result['ResultID']; ?>">
                                                            Edit
                                                        </button>
                                                        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteResultModal<?php echo $result['ResultID']; ?>">
                                                            Delete
                                                        </button>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                            
                                            <?php if ($userRole === 'AdminRole' || $userRole === 'FacultyRole'): ?>
                                                <!-- Edit Result Modal -->
                                                <div class="modal fade" id="editResultModal<?php echo $result['ResultID']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Edit Result</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <form method="POST" action="results.php">
                                                                <div class="modal-body">
                                                                    <input type="hidden" name="action" value="edit">
                                                                    <input type="hidden" name="result_id" value="<?php echo $result['ResultID']; ?>">
                                                                    
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Student</label>
                                                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($result['StudentName']); ?>" readonly>
                                                                    </div>
                                                                    
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Course</label>
                                                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($result['CourseTitle'] . ' (' . $result['CourseCode'] . ')'); ?>" readonly>
                                                                    </div>
                                                                    
                                                                    <div class="mb-3">
                                                                        <label for="grade<?php echo $result['ResultID']; ?>" class="form-label">Grade</label>
                                                                        <select class="form-select" id="grade<?php echo $result['ResultID']; ?>" name="grade" required>
                                                                            <?php
                                                                            $grades = ['A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D+', 'D', 'F'];
                                                                            foreach ($grades as $g):
                                                                                $selected = ($g === $result['Grade']) ? 'selected' : '';
                                                                            ?>
                                                                                <option value="<?php echo $g; ?>" <?php echo $selected; ?>><?php echo $g; ?></option>
                                                                            <?php endforeach; ?>
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
                                                
                                                <?php if ($userRole === 'AdminRole'): ?>
                                                    <!-- Delete Result Modal -->
                                                    <div class="modal fade" id="deleteResultModal<?php echo $result['ResultID']; ?>" tabindex="-1" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title">Delete Result</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <p>Are you sure you want to delete this result?</p>
                                                                    <p class="text-danger">This action cannot be undone.</p>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <form method="POST" action="results.php">
                                                                        <input type="hidden" name="action" value="delete">
                                                                        <input type="hidden" name="result_id" value="<?php echo $result['ResultID']; ?>">
                                                                        <button type="submit" class="btn btn-danger">Delete</button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
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
    
    <?php if ($userRole === 'AdminRole' || $userRole === 'FacultyRole'): ?>
        <!-- Add Result Modal -->
        <div class="modal fade" id="addResultModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Result</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="results.php">
                        <div class="modal-body">
                            <input type="hidden" name="action" value="add">
                            
                            <div class="mb-3">
                                <label for="student_id" class="form-label">Student</label>
                                <select class="form-select" id="student_id" name="student_id" required>
                                    <option value="">Select Student</option>
                                    <?php foreach ($students as $student): ?>
                                        <option value="<?php echo $student['StudentID']; ?>">
                                            <?php echo htmlspecialchars($student['FullName']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="course_code" class="form-label">Course</label>
                                <select class="form-select" id="course_code" name="course_code" required>
                                    <option value="">Select Course</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo $course['CourseCode']; ?>">
                                            <?php echo htmlspecialchars($course['CourseCode'] . ' - ' . $course['CourseTitle']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <?php if ($userRole === 'AdminRole'): ?>
                                <div class="mb-3">
                                    <label for="faculty_id" class="form-label">Faculty</label>
                                    <select class="form-select" id="faculty_id" name="faculty_id" required>
                                        <option value="">Select Faculty</option>
                                        <?php foreach ($faculty as $f): ?>
                                            <option value="<?php echo $f['FacultyID']; ?>">
                                                <?php echo htmlspecialchars($f['FullName']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php else: ?>
                                <input type="hidden" name="faculty_id" value="<?php echo $userId; ?>">
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label for="grade" class="form-label">Grade</label>
                                <select class="form-select" id="grade" name="grade" required>
                                    <option value="">Select Grade</option>
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
                            <button type="submit" class="btn btn-primary">Add Result</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Edit Grade Modal -->
    <div class="modal fade" id="editGradeModal" tabindex="-1" aria-labelledby="editGradeModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editGradeModalLabel">Edit Grade</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editGradeForm" method="POST" action="results.php">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" id="editResultId" name="result_id">
                        <div class="mb-3">
                            <label for="editGradeInput" class="form-label">Grade</label>
                            <input type="text" class="form-control" id="editGradeInput" name="grade" 
                                   maxlength="2" required pattern="[A-Fa-f][+-]?"
                                   title="Enter a valid grade (A+, A, A-, B+, B, B-, C+, C, C-, D+, D, D-, F)">
                            <div class="invalid-feedback">
                                Please enter a valid grade (A+, A, A-, B+, B, B-, C+, C, C-, D+, D, D-, F)
                            </div>
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

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Store the current grade being edited
        let currentGrade = '';
        let isEditing = false;
        
        // Debounce function to prevent rapid firing of events
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // Function to handle grade editing
        function editGrade(resultId, currentGradeValue) {
            if (isEditing) return;
            isEditing = true;
            
            const modal = new bootstrap.Modal(document.getElementById('editGradeModal'));
            const gradeInput = document.getElementById('editGradeInput');
            const resultIdInput = document.getElementById('editResultId');
            
            // Store the current grade
            currentGrade = currentGradeValue;
            
            // Set up the modal
            resultIdInput.value = resultId;
            gradeInput.value = currentGrade;
            
            // Show the modal
            modal.show();
            
            // Focus the input after modal is shown
            modal._element.addEventListener('shown.bs.modal', function () {
                gradeInput.focus();
            });
            
            // Reset editing state when modal is closed
            modal._element.addEventListener('hidden.bs.modal', function () {
                isEditing = false;
                gradeInput.value = currentGrade;
            });
        }

        // Function to validate grade input
        function validateGrade(input) {
            const gradePattern = /^[A-F][+-]?$/;
            const grade = input.value.toUpperCase();
            
            if (!gradePattern.test(grade)) {
                input.classList.add('is-invalid');
                return false;
            }
            
            input.classList.remove('is-invalid');
            input.value = grade;
            return true;
        }

        // Handle form submission with debouncing
        const handleSubmit = debounce(function(event) {
            event.preventDefault();
            
            const form = event.target;
            const gradeInput = form.querySelector('#editGradeInput');
            
            if (!validateGrade(gradeInput)) {
                return;
            }
            
            // Submit the form
            form.submit();
        }, 300);

        // Add event listeners when document is ready
        document.addEventListener('DOMContentLoaded', function() {
            // Add submit handler to the edit grade form
            const editForm = document.getElementById('editGradeForm');
            if (editForm) {
                editForm.addEventListener('submit', handleSubmit);
            }
            
            // Add input validation handler
            const gradeInput = document.getElementById('editGradeInput');
            if (gradeInput) {
                gradeInput.addEventListener('input', function() {
                    this.value = this.value.toUpperCase();
                    validateGrade(this);
                });
            }
        });
    </script>
</body>
</html> 