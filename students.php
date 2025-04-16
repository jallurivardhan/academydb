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
$students = [];
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add' && $userRole === 'AdminRole') {
            // Add new student
            $studentId = $_POST['student_id'] ?? '';
            $fullName = $_POST['full_name'] ?? '';
            $contact = $_POST['contact'] ?? '';
            $email = $_POST['email'] ?? '';
            $additionalInfo = $_POST['additional_info'] ?? '';
            
            if (empty($studentId) || empty($fullName)) {
                $error = 'Student ID and Full Name are required.';
            } else {
                try {
                    // Check if student ID already exists
                    $stmt = $pdo->prepare("SELECT 1 FROM Students WHERE StudentID = ?");
                    $stmt->execute([$studentId]);
                    if ($stmt->rowCount() > 0) {
                        $error = 'Student ID already exists.';
                    } else {
                        // Insert new student
                        $stmt = $pdo->prepare("INSERT INTO Students (StudentID, FullName, Contact, Email, AdditionalInfo, CreatedByUser) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$studentId, $fullName, $contact, $email, $additionalInfo, $userId]);
                        $success = 'Student added successfully.';
                    }
                } catch(PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        } elseif ($_POST['action'] == 'edit' && $userRole === 'AdminRole') {
            // Edit student
            $studentId = $_POST['student_id'] ?? '';
            $fullName = $_POST['full_name'] ?? '';
            $contact = $_POST['contact'] ?? '';
            $email = $_POST['email'] ?? '';
            $additionalInfo = $_POST['additional_info'] ?? '';
            
            if (empty($studentId) || empty($fullName)) {
                $error = 'Student ID and Full Name are required.';
            } else {
                try {
                    // Update student
                    $stmt = $pdo->prepare("UPDATE Students SET FullName = ?, Contact = ?, Email = ?, AdditionalInfo = ?, LastModifiedBy = ?, LastModifiedOn = NOW() WHERE StudentID = ?");
                    $stmt->execute([$fullName, $contact, $email, $additionalInfo, $userId, $studentId]);
                    $success = 'Student updated successfully.';
                } catch(PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        } elseif ($_POST['action'] == 'delete' && $userRole === 'AdminRole') {
            // Delete student
            $studentId = $_POST['student_id'] ?? '';
            
            if (empty($studentId)) {
                $error = 'Student ID is required.';
            } else {
                try {
                    // Delete student
                    $stmt = $pdo->prepare("DELETE FROM Students WHERE StudentID = ?");
                    $stmt->execute([$studentId]);
                    $success = 'Student deleted successfully.';
                } catch(PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
    }
}

// Fetch students based on user role
try {
    if ($userRole === 'AdminRole') {
        // Admin can see all students
        $stmt = $pdo->query("SELECT * FROM Students ORDER BY StudentID");
        $students = $stmt->fetchAll();
    } elseif ($userRole === 'FacultyRole') {
        // Faculty can see students in their courses
        $stmt = $pdo->prepare("
            SELECT DISTINCT s.* 
            FROM Students s
            JOIN Results r ON s.StudentID = r.StudentID
            WHERE r.FacultyID = ?
            ORDER BY s.StudentID
        ");
        $stmt->execute([$userId]);
        $students = $stmt->fetchAll();
    } elseif ($userRole === 'StudentRole') {
        // Student can only see their own information
        $stmt = $pdo->prepare("SELECT * FROM Students WHERE StudentID = ?");
        $stmt->execute([$userId]);
        $students = $stmt->fetchAll();
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
    <title>Students - AcademyDB Management System</title>
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
                        <a class="nav-link active" href="students.php">Students</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="faculty.php">Faculty</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="courses.php">Courses</a>
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
                        <h2 class="card-title">Students</h2>
                        
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($userRole === 'AdminRole'): ?>
                            <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                                Add New Student
                            </button>
                        <?php endif; ?>
                        
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Full Name</th>
                                        <th>Contact</th>
                                        <th>Email</th>
                                        <th>Additional Info</th>
                                        <th>Status</th>
                                        <th>Created On</th>
                                        <?php if ($userRole === 'AdminRole'): ?>
                                            <th>Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($students)): ?>
                                        <tr>
                                            <td colspan="<?php echo $userRole === 'AdminRole' ? '8' : '7'; ?>" class="text-center">No students found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($students as $student): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($student['StudentID']); ?></td>
                                                <td><?php echo htmlspecialchars($student['FullName']); ?></td>
                                                <td><?php echo htmlspecialchars($student['Contact'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($student['Email'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($student['AdditionalInfo'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($student['StudentStatus']); ?></td>
                                                <td><?php echo htmlspecialchars($student['CreatedOn']); ?></td>
                                                <?php if ($userRole === 'AdminRole'): ?>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editStudentModal<?php echo $student['StudentID']; ?>">
                                                            Edit
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteStudentModal<?php echo $student['StudentID']; ?>">
                                                            Delete
                                                        </button>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                            
                                            <?php if ($userRole === 'AdminRole'): ?>
                                                <!-- Edit Student Modal -->
                                                <div class="modal fade" id="editStudentModal<?php echo $student['StudentID']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Edit Student</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <form method="POST" action="students.php">
                                                                <div class="modal-body">
                                                                    <input type="hidden" name="action" value="edit">
                                                                    <input type="hidden" name="student_id" value="<?php echo $student['StudentID']; ?>">
                                                                    
                                                                    <div class="mb-3">
                                                                        <label for="full_name<?php echo $student['StudentID']; ?>" class="form-label">Full Name</label>
                                                                        <input type="text" class="form-control" id="full_name<?php echo $student['StudentID']; ?>" name="full_name" value="<?php echo htmlspecialchars($student['FullName']); ?>" required>
                                                                    </div>
                                                                    
                                                                    <div class="mb-3">
                                                                        <label for="contact<?php echo $student['StudentID']; ?>" class="form-label">Contact</label>
                                                                        <input type="text" class="form-control" id="contact<?php echo $student['StudentID']; ?>" name="contact" value="<?php echo htmlspecialchars($student['Contact'] ?? ''); ?>">
                                                                    </div>
                                                                    
                                                                    <div class="mb-3">
                                                                        <label for="email<?php echo $student['StudentID']; ?>" class="form-label">Email</label>
                                                                        <input type="email" class="form-control" id="email<?php echo $student['StudentID']; ?>" name="email" value="<?php echo htmlspecialchars($student['Email'] ?? ''); ?>">
                                                                    </div>
                                                                    
                                                                    <div class="mb-3">
                                                                        <label for="additional_info<?php echo $student['StudentID']; ?>" class="form-label">Additional Info</label>
                                                                        <textarea class="form-control" id="additional_info<?php echo $student['StudentID']; ?>" name="additional_info"><?php echo htmlspecialchars($student['AdditionalInfo'] ?? ''); ?></textarea>
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
                                                
                                                <!-- Delete Student Modal -->
                                                <div class="modal fade" id="deleteStudentModal<?php echo $student['StudentID']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Delete Student</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p>Are you sure you want to delete student <strong><?php echo htmlspecialchars($student['FullName']); ?></strong>?</p>
                                                                <p class="text-danger">This action cannot be undone.</p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <form method="POST" action="students.php">
                                                                    <input type="hidden" name="action" value="delete">
                                                                    <input type="hidden" name="student_id" value="<?php echo $student['StudentID']; ?>">
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
        <!-- Add Student Modal -->
        <div class="modal fade" id="addStudentModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Student</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="students.php">
                        <div class="modal-body">
                            <input type="hidden" name="action" value="add">
                            
                            <div class="mb-3">
                                <label for="student_id" class="form-label">Student ID</label>
                                <input type="text" class="form-control" id="student_id" name="student_id" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="contact" class="form-label">Contact</label>
                                <input type="text" class="form-control" id="contact" name="contact">
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email">
                            </div>
                            
                            <div class="mb-3">
                                <label for="additional_info" class="form-label">Additional Info</label>
                                <textarea class="form-control" id="additional_info" name="additional_info"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Add Student</button>
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