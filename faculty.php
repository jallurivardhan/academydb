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
$faculty = [];
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add' && $userRole === 'AdminRole') {
            // Add new faculty
            $facultyId = $_POST['faculty_id'] ?? '';
            $fullName = $_POST['full_name'] ?? '';
            $contact = $_POST['contact'] ?? '';
            $email = $_POST['email'] ?? '';
            $dept = $_POST['dept'] ?? '';
            $additionalInfo = $_POST['additional_info'] ?? '';
            
            if (empty($facultyId) || empty($fullName)) {
                $error = 'Faculty ID and Full Name are required.';
            } else {
                try {
                    // Check if faculty ID already exists
                    $stmt = $pdo->prepare("SELECT 1 FROM Faculty WHERE FacultyID = ?");
                    $stmt->execute([$facultyId]);
                    if ($stmt->rowCount() > 0) {
                        $error = 'Faculty ID already exists.';
                    } else {
                        // Insert new faculty
                        $stmt = $pdo->prepare("INSERT INTO Faculty (FacultyID, FullName, Contact, Email, Dept, AdditionalInfo, CreatedByUser) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$facultyId, $fullName, $contact, $email, $dept, $additionalInfo, $userId]);
                        $success = 'Faculty added successfully.';
                    }
                } catch(PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        } elseif ($_POST['action'] == 'edit' && $userRole === 'AdminRole') {
            // Edit faculty
            $facultyId = $_POST['faculty_id'] ?? '';
            $fullName = $_POST['full_name'] ?? '';
            $contact = $_POST['contact'] ?? '';
            $email = $_POST['email'] ?? '';
            $dept = $_POST['dept'] ?? '';
            $additionalInfo = $_POST['additional_info'] ?? '';
            
            if (empty($facultyId) || empty($fullName)) {
                $error = 'Faculty ID and Full Name are required.';
            } else {
                try {
                    // Update faculty
                    $stmt = $pdo->prepare("UPDATE Faculty SET FullName = ?, Contact = ?, Email = ?, Dept = ?, AdditionalInfo = ?, LastModifiedBy = ?, LastModifiedOn = NOW() WHERE FacultyID = ?");
                    $stmt->execute([$fullName, $contact, $email, $dept, $additionalInfo, $userId, $facultyId]);
                    $success = 'Faculty updated successfully.';
                } catch(PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        } elseif ($_POST['action'] == 'delete' && $userRole === 'AdminRole') {
            // Delete faculty
            $facultyId = $_POST['faculty_id'] ?? '';
            
            if (empty($facultyId)) {
                $error = 'Faculty ID is required.';
            } else {
                try {
                    // Delete faculty
                    $stmt = $pdo->prepare("DELETE FROM Faculty WHERE FacultyID = ?");
                    $stmt->execute([$facultyId]);
                    $success = 'Faculty deleted successfully.';
                } catch(PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
    }
}

// Fetch faculty based on user role
try {
    if ($userRole === 'AdminRole') {
        // Admin can see all faculty
        $stmt = $pdo->query("SELECT * FROM Faculty ORDER BY FacultyID");
        $faculty = $stmt->fetchAll();
    } elseif ($userRole === 'FacultyRole') {
        // Faculty can only see their own information
        $stmt = $pdo->prepare("SELECT * FROM Faculty WHERE FacultyID = ?");
        $stmt->execute([$userId]);
        $faculty = $stmt->fetchAll();
    } elseif ($userRole === 'StudentRole') {
        // Students can see faculty teaching their courses
        $stmt = $pdo->prepare("
            SELECT DISTINCT f.* 
            FROM Faculty f
            JOIN Results r ON f.FacultyID = r.FacultyID
            JOIN Results s ON r.CourseCode = s.CourseCode
            WHERE s.StudentID = ?
            ORDER BY f.FacultyID
        ");
        $stmt->execute([$userId]);
        $faculty = $stmt->fetchAll();
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
    <title>Faculty - AcademyDB Management System</title>
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
                        <a class="nav-link active" href="faculty.php">Faculty</a>
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
                        <h2 class="card-title">Faculty</h2>
                        
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($userRole === 'AdminRole'): ?>
                            <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addFacultyModal">
                                Add New Faculty
                            </button>
                        <?php endif; ?>
                        
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Faculty ID</th>
                                        <th>Full Name</th>
                                        <th>Contact</th>
                                        <th>Email</th>
                                        <th>Department</th>
                                        <th>Additional Info</th>
                                        <th>Status</th>
                                        <th>Created On</th>
                                        <?php if ($userRole === 'AdminRole'): ?>
                                            <th>Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($faculty)): ?>
                                        <tr>
                                            <td colspan="<?php echo $userRole === 'AdminRole' ? '9' : '8'; ?>" class="text-center">No faculty found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($faculty as $fac): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($fac['FacultyID']); ?></td>
                                                <td><?php echo htmlspecialchars($fac['FullName']); ?></td>
                                                <td><?php echo htmlspecialchars($fac['Contact'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($fac['Email'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($fac['Dept'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($fac['AdditionalInfo'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($fac['FacultyStatus']); ?></td>
                                                <td><?php echo htmlspecialchars($fac['CreatedOn']); ?></td>
                                                <?php if ($userRole === 'AdminRole'): ?>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editFacultyModal<?php echo $fac['FacultyID']; ?>">
                                                            Edit
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteFacultyModal<?php echo $fac['FacultyID']; ?>">
                                                            Delete
                                                        </button>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                            
                                            <?php if ($userRole === 'AdminRole'): ?>
                                                <!-- Edit Faculty Modal -->
                                                <div class="modal fade" id="editFacultyModal<?php echo $fac['FacultyID']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Edit Faculty</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <form method="POST" action="faculty.php">
                                                                <div class="modal-body">
                                                                    <input type="hidden" name="action" value="edit">
                                                                    <input type="hidden" name="faculty_id" value="<?php echo $fac['FacultyID']; ?>">
                                                                    
                                                                    <div class="mb-3">
                                                                        <label for="full_name<?php echo $fac['FacultyID']; ?>" class="form-label">Full Name</label>
                                                                        <input type="text" class="form-control" id="full_name<?php echo $fac['FacultyID']; ?>" name="full_name" value="<?php echo htmlspecialchars($fac['FullName']); ?>" required>
                                                                    </div>
                                                                    
                                                                    <div class="mb-3">
                                                                        <label for="contact<?php echo $fac['FacultyID']; ?>" class="form-label">Contact</label>
                                                                        <input type="text" class="form-control" id="contact<?php echo $fac['FacultyID']; ?>" name="contact" value="<?php echo htmlspecialchars($fac['Contact'] ?? ''); ?>">
                                                                    </div>
                                                                    
                                                                    <div class="mb-3">
                                                                        <label for="email<?php echo $fac['FacultyID']; ?>" class="form-label">Email</label>
                                                                        <input type="email" class="form-control" id="email<?php echo $fac['FacultyID']; ?>" name="email" value="<?php echo htmlspecialchars($fac['Email'] ?? ''); ?>">
                                                                    </div>
                                                                    
                                                                    <div class="mb-3">
                                                                        <label for="dept<?php echo $fac['FacultyID']; ?>" class="form-label">Department</label>
                                                                        <input type="text" class="form-control" id="dept<?php echo $fac['FacultyID']; ?>" name="dept" value="<?php echo htmlspecialchars($fac['Dept'] ?? ''); ?>">
                                                                    </div>
                                                                    
                                                                    <div class="mb-3">
                                                                        <label for="additional_info<?php echo $fac['FacultyID']; ?>" class="form-label">Additional Info</label>
                                                                        <textarea class="form-control" id="additional_info<?php echo $fac['FacultyID']; ?>" name="additional_info"><?php echo htmlspecialchars($fac['AdditionalInfo'] ?? ''); ?></textarea>
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
                                                
                                                <!-- Delete Faculty Modal -->
                                                <div class="modal fade" id="deleteFacultyModal<?php echo $fac['FacultyID']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Delete Faculty</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p>Are you sure you want to delete faculty <strong><?php echo htmlspecialchars($fac['FullName']); ?></strong>?</p>
                                                                <p class="text-danger">This action cannot be undone.</p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <form method="POST" action="faculty.php">
                                                                    <input type="hidden" name="action" value="delete">
                                                                    <input type="hidden" name="faculty_id" value="<?php echo $fac['FacultyID']; ?>">
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
        <!-- Add Faculty Modal -->
        <div class="modal fade" id="addFacultyModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Faculty</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="faculty.php">
                        <div class="modal-body">
                            <input type="hidden" name="action" value="add">
                            
                            <div class="mb-3">
                                <label for="faculty_id" class="form-label">Faculty ID</label>
                                <input type="text" class="form-control" id="faculty_id" name="faculty_id" required>
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
                                <label for="dept" class="form-label">Department</label>
                                <input type="text" class="form-control" id="dept" name="dept">
                            </div>
                            
                            <div class="mb-3">
                                <label for="additional_info" class="form-label">Additional Info</label>
                                <textarea class="form-control" id="additional_info" name="additional_info"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Add Faculty</button>
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