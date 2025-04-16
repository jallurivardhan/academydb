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

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assignment'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $assignment_id = $_POST['assignment_id'] ?? '';
        $submission_text = $_POST['submission_text'] ?? '';
        
        // Handle file upload
        if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['submission_file'];
            $allowed_types = ['pdf', 'doc', 'docx', 'txt'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($file_ext, $allowed_types)) {
                $error = 'Invalid file type. Allowed types: ' . implode(', ', $allowed_types);
            } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
                $error = 'File size too large. Maximum size: 5MB';
            } else {
                $upload_dir = '../uploads/assignments/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_name = uniqid() . '_' . $file['name'];
                $file_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($file['tmp_name'], $file_path)) {
                    try {
                        // Save submission to database
                        $stmt = $conn->prepare("INSERT INTO AssignmentSubmissions 
                            (AssignmentID, StudentID, SubmissionText, FilePath, SubmissionDate) 
                            VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)");
                        $stmt->execute([$assignment_id, $user_id, $submission_text, $file_name]);
                        
                        $message = 'Assignment submitted successfully.';
                        logUserActivity($user_id, 'Submitted assignment ' . $assignment_id);
                    } catch (PDOException $e) {
                        $error = 'Error submitting assignment.';
                        logUserActivity($user_id, 'Failed to submit assignment: ' . $e->getMessage());
                    }
                } else {
                    $error = 'Error uploading file.';
                }
            }
        } else {
            $error = 'Please select a file to upload.';
        }
    }
}

// Fetch assignments for the student's enrolled courses
try {
    $stmt = $conn->prepare("
        SELECT a.*, c.CourseName, f.FullName as FacultyName,
               s.SubmissionDate, s.Grade, s.FilePath
        FROM Assignments a
        JOIN Courses c ON a.CourseID = c.CourseID
        JOIN Faculty f ON c.FacultyID = f.FacultyID
        JOIN Enrollments e ON c.CourseID = e.CourseID
        LEFT JOIN AssignmentSubmissions s ON a.AssignmentID = s.AssignmentID 
            AND s.StudentID = ?
        WHERE e.StudentID = ?
        ORDER BY a.DueDate ASC
    ");
    $stmt->execute([$user_id, $user_id]);
    $assignments = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Error fetching assignments.';
    logUserActivity($user_id, 'Failed to fetch assignments: ' . $e->getMessage());
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignments - Student Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        .assignment-card {
            transition: transform 0.2s;
        }
        .assignment-card:hover {
            transform: translateY(-5px);
        }
        .deadline-near {
            color: #dc3545;
        }
        .submitted {
            color: #198754;
        }
        .graded {
            color: #0d6efd;
        }
    </style>
</head>
<body>
    <?php include '../includes/student_navbar.php'; ?>
    
    <div class="container mt-4">
        <h2>My Assignments</h2>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="row">
            <?php foreach ($assignments as $assignment): ?>
                <div class="col-md-6 mb-4">
                    <div class="card assignment-card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <?php echo htmlspecialchars($assignment['Title']); ?>
                                <?php if ($assignment['SubmissionDate']): ?>
                                    <span class="badge bg-success float-end">Submitted</span>
                                <?php elseif (strtotime($assignment['DueDate']) < time()): ?>
                                    <span class="badge bg-danger float-end">Overdue</span>
                                <?php endif; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="card-text"><?php echo htmlspecialchars($assignment['Description']); ?></p>
                            <ul class="list-unstyled">
                                <li><strong>Course:</strong> <?php echo htmlspecialchars($assignment['CourseName']); ?></li>
                                <li><strong>Faculty:</strong> <?php echo htmlspecialchars($assignment['FacultyName']); ?></li>
                                <li><strong>Due Date:</strong> 
                                    <span class="<?php echo strtotime($assignment['DueDate']) - time() < 86400 ? 'deadline-near' : ''; ?>">
                                        <?php echo date('M d, Y H:i', strtotime($assignment['DueDate'])); ?>
                                    </span>
                                </li>
                                <?php if ($assignment['SubmissionDate']): ?>
                                    <li><strong>Submitted:</strong> <?php echo date('M d, Y H:i', strtotime($assignment['SubmissionDate'])); ?></li>
                                    <?php if ($assignment['Grade']): ?>
                                        <li><strong>Grade:</strong> <?php echo htmlspecialchars($assignment['Grade']); ?></li>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </ul>
                            
                            <?php if (!$assignment['SubmissionDate']): ?>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" 
                                        data-bs-target="#submitModal<?php echo $assignment['AssignmentID']; ?>">
                                    Submit Assignment
                                </button>
                                
                                <!-- Submit Modal -->
                                <div class="modal fade" id="submitModal<?php echo $assignment['AssignmentID']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Submit Assignment</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST" enctype="multipart/form-data">
                                                <div class="modal-body">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="assignment_id" value="<?php echo $assignment['AssignmentID']; ?>">
                                                    
                                                    <div class="mb-3">
                                                        <label for="submission_text" class="form-label">Comments (Optional)</label>
                                                        <textarea class="form-control" id="submission_text" name="submission_text" rows="3"></textarea>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="submission_file" class="form-label">Upload File</label>
                                                        <input type="file" class="form-control" id="submission_file" name="submission_file" required>
                                                        <div class="form-text">Allowed types: PDF, DOC, DOCX, TXT. Maximum size: 5MB</div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" name="submit_assignment" class="btn btn-primary">Submit</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php elseif ($assignment['FilePath']): ?>
                                <a href="../uploads/assignments/<?php echo htmlspecialchars($assignment['FilePath']); ?>" 
                                   class="btn btn-info" target="_blank">
                                    <i class="fas fa-download"></i> View Submission
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 