<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and has StudentRole
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'StudentRole') {
    header('Location: ../login.php');
    exit();
}

$studentId = $_SESSION['user_id'];
$error = '';
$success = '';

try {
    // Get student's own data with masking
    $stmt = $pdo->prepare("
        SELECT 
            StudentID,
            FullName,
            fn_MaskEmail(Email) as Email,
            fn_MaskContact(Contact) as Contact,
            StudentStatus,
            CreatedOn,
            LastModifiedOn
        FROM Students
        WHERE StudentID = ?
    ");
    $stmt->execute([$studentId]);
    $studentData = $stmt->fetch();

    // Get student's academic summary
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT e.CourseCode) as TotalCourses,
            COUNT(DISTINCT r.ResultID) as GradedCourses,
            ROUND(AVG(CASE 
                WHEN r.Grade = 'A' THEN 4.0
                WHEN r.Grade = 'A-' THEN 3.7
                WHEN r.Grade = 'B+' THEN 3.3
                WHEN r.Grade = 'B' THEN 3.0
                WHEN r.Grade = 'B-' THEN 2.7
                WHEN r.Grade = 'C+' THEN 2.3
                WHEN r.Grade = 'C' THEN 2.0
                WHEN r.Grade = 'C-' THEN 1.7
                WHEN r.Grade = 'D+' THEN 1.3
                WHEN r.Grade = 'D' THEN 1.0
                WHEN r.Grade = 'F' THEN 0.0
            END), 2) as GPA
        FROM Enrollments e
        LEFT JOIN Results r ON e.StudentID = r.StudentID AND e.CourseCode = r.CourseCode
        WHERE e.StudentID = ?
    ");
    $stmt->execute([$studentId]);
    $academicSummary = $stmt->fetch();

} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Student Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">Student Portal</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="courses.php">My Courses</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="grades.php">Grades</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="view_profile.php">My Profile</a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>My Profile</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8">
                <?php if ($studentData): ?>
                    <div class="card">
                        <div class="card-header">
                            <h4>Student Information</h4>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-3"><strong>Name:</strong></div>
                                <div class="col-md-9"><?php echo htmlspecialchars($studentData['FullName']); ?></div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-3"><strong>Email:</strong></div>
                                <div class="col-md-9"><?php echo htmlspecialchars($studentData['Email']); ?></div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-3"><strong>Contact:</strong></div>
                                <div class="col-md-9"><?php echo htmlspecialchars($studentData['Contact']); ?></div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-3"><strong>Status:</strong></div>
                                <div class="col-md-9">
                                    <span class="badge bg-<?php echo $studentData['StudentStatus'] === 'Active' ? 'success' : 'warning'; ?>">
                                        <?php echo htmlspecialchars($studentData['StudentStatus']); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-3"><strong>Last Updated:</strong></div>
                                <div class="col-md-9"><?php echo htmlspecialchars($studentData['LastModifiedOn']); ?></div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="alert alert-info mt-4">
                    <h5>Note:</h5>
                    <p>For security reasons, some sensitive information is masked. If you need to update your information or view complete details, please contact the administrator.</p>
                </div>
            </div>

            <div class="col-md-4">
                <?php if ($academicSummary): ?>
                    <div class="card">
                        <div class="card-header">
                            <h4>Academic Summary</h4>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-7"><strong>Total Courses:</strong></div>
                                <div class="col-5"><?php echo htmlspecialchars($academicSummary['TotalCourses']); ?></div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-7"><strong>Graded Courses:</strong></div>
                                <div class="col-5"><?php echo htmlspecialchars($academicSummary['GradedCourses']); ?></div>
                            </div>

                            <div class="row">
                                <div class="col-7"><strong>Current GPA:</strong></div>
                                <div class="col-5">
                                    <span class="badge bg-primary">
                                        <?php echo $academicSummary['GPA'] ? htmlspecialchars(number_format($academicSummary['GPA'], 2)) : 'N/A'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 