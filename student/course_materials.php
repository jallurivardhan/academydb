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

// Get course ID from URL parameter
$course_id = $_GET['course_id'] ?? null;

// Verify student is enrolled in the course
if ($course_id) {
    try {
        $stmt = $conn->prepare("SELECT 1 FROM Enrollments WHERE StudentID = ? AND CourseID = ?");
        $stmt->execute([$user_id, $course_id]);
        if (!$stmt->fetch()) {
            header('Location: dashboard.php');
            exit();
        }
    } catch (PDOException $e) {
        $error = 'Error verifying course enrollment.';
        logUserActivity($user_id, 'Failed to verify course enrollment: ' . $e->getMessage());
    }
}

// Fetch enrolled courses
try {
    $stmt = $conn->prepare("
        SELECT c.*, f.FullName as FacultyName
        FROM Courses c
        JOIN Faculty f ON c.FacultyID = f.FacultyID
        JOIN Enrollments e ON c.CourseID = e.CourseID
        WHERE e.StudentID = ?
        ORDER BY c.CourseName
    ");
    $stmt->execute([$user_id]);
    $courses = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Error fetching courses.';
    logUserActivity($user_id, 'Failed to fetch courses: ' . $e->getMessage());
}

// Fetch course materials if a course is selected
$materials = [];
if ($course_id) {
    try {
        $stmt = $conn->prepare("
            SELECT m.*, f.FullName as UploadedBy
            FROM CourseMaterials m
            JOIN Faculty f ON m.UploadedBy = f.FacultyID
            WHERE m.CourseID = ?
            ORDER BY m.UploadDate DESC
        ");
        $stmt->execute([$course_id]);
        $materials = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error = 'Error fetching course materials.';
        logUserActivity($user_id, 'Failed to fetch course materials: ' . $e->getMessage());
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
    <title>Course Materials - Student Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        .material-card {
            transition: transform 0.2s;
        }
        .material-card:hover {
            transform: translateY(-5px);
        }
        .file-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        .pdf-icon { color: #dc3545; }
        .doc-icon { color: #0d6efd; }
        .ppt-icon { color: #fd7e14; }
        .zip-icon { color: #6c757d; }
    </style>
</head>
<body>
    <?php include '../includes/student_navbar.php'; ?>
    
    <div class="container mt-4">
        <h2>Course Materials</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Course Selection -->
            <div class="col-md-3">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">My Courses</h5>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php foreach ($courses as $course): ?>
                            <a href="?course_id=<?php echo $course['CourseID']; ?>" 
                               class="list-group-item list-group-item-action <?php echo $course_id == $course['CourseID'] ? 'active' : ''; ?>">
                                <?php echo htmlspecialchars($course['CourseName']); ?>
                                <small class="d-block text-muted"><?php echo htmlspecialchars($course['FacultyName']); ?></small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Course Materials -->
            <div class="col-md-9">
                <?php if ($course_id): ?>
                    <?php if (empty($materials)): ?>
                        <div class="alert alert-info">
                            No materials available for this course yet.
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($materials as $material): ?>
                                <div class="col-md-6 mb-4">
                                    <div class="card material-card">
                                        <div class="card-body text-center">
                                            <?php
                                            $file_ext = strtolower(pathinfo($material['FilePath'], PATHINFO_EXTENSION));
                                            $icon_class = 'fas fa-file';
                                            $icon_color = '';
                                            
                                            switch ($file_ext) {
                                                case 'pdf':
                                                    $icon_class = 'fas fa-file-pdf';
                                                    $icon_color = 'pdf-icon';
                                                    break;
                                                case 'doc':
                                                case 'docx':
                                                    $icon_class = 'fas fa-file-word';
                                                    $icon_color = 'doc-icon';
                                                    break;
                                                case 'ppt':
                                                case 'pptx':
                                                    $icon_class = 'fas fa-file-powerpoint';
                                                    $icon_color = 'ppt-icon';
                                                    break;
                                                case 'zip':
                                                case 'rar':
                                                    $icon_class = 'fas fa-file-archive';
                                                    $icon_color = 'zip-icon';
                                                    break;
                                            }
                                            ?>
                                            
                                            <i class="<?php echo $icon_class; ?> file-icon <?php echo $icon_color; ?>"></i>
                                            <h5 class="card-title"><?php echo htmlspecialchars($material['Title']); ?></h5>
                                            <p class="card-text"><?php echo htmlspecialchars($material['Description']); ?></p>
                                            <ul class="list-unstyled">
                                                <li><small>Uploaded by: <?php echo htmlspecialchars($material['UploadedBy']); ?></small></li>
                                                <li><small>Date: <?php echo date('M d, Y', strtotime($material['UploadDate'])); ?></small></li>
                                            </ul>
                                            <a href="../uploads/materials/<?php echo htmlspecialchars($material['FilePath']); ?>" 
                                               class="btn btn-primary" target="_blank">
                                                <i class="fas fa-download"></i> Download
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        Please select a course to view its materials.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 