<?php
session_start();
require_once '../config/db_connect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'AdminRole') {
    header("Location: ../login.php");
    exit();
}

// Initialize variables
$error = '';
$success = '';

try {
    // Get total counts
    $counts = [];
    
    // Count total students
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM Students WHERE StudentStatus = 'Active'");
    $counts['students'] = $stmt->fetch()['count'];
    
    // Count total faculty
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM Faculty WHERE FacultyStatus = 'Active'");
    $counts['faculty'] = $stmt->fetch()['count'];
    
    // Count total courses
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM Courses");
    $counts['courses'] = $stmt->fetch()['count'];
    
    // Get grade distribution
    $stmt = $pdo->query("
        SELECT Grade, COUNT(*) as count 
        FROM Results 
        GROUP BY Grade 
        ORDER BY Grade
    ");
    $gradeDistribution = $stmt->fetchAll();
    
    // Get course enrollment statistics
    $stmt = $pdo->query("
        SELECT c.CourseCode, c.CourseTitle, COUNT(DISTINCT r.StudentID) as enrollment
        FROM Courses c
        LEFT JOIN Results r ON c.CourseCode = r.CourseCode
        GROUP BY c.CourseCode, c.CourseTitle
        ORDER BY enrollment DESC
    ");
    $courseEnrollments = $stmt->fetchAll();
    
    // Get faculty teaching load
    $stmt = $pdo->query("
        SELECT f.FullName, COUNT(DISTINCT r.CourseCode) as courses_taught, COUNT(r.StudentID) as total_students
        FROM Faculty f
        LEFT JOIN Results r ON f.FacultyID = r.FacultyID
        GROUP BY f.FacultyID, f.FullName
        ORDER BY courses_taught DESC
    ");
    $facultyLoad = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Reports - AcademyDB Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../css/style.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../index.php">AcademyDB Management System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_users.php">Manage Users</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="reports.php">Reports</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="../logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Academic Reports</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Active Students</h5>
                        <h2 class="card-text"><?php echo $counts['students']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Active Faculty</h5>
                        <h2 class="card-text"><?php echo $counts['faculty']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Total Courses</h5>
                        <h2 class="card-text"><?php echo $counts['courses']; ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Grade Distribution Chart -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Grade Distribution</h5>
                        <canvas id="gradeChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Course Enrollments</h5>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Course</th>
                                        <th>Enrollment</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($courseEnrollments as $course): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($course['CourseCode'] . ' - ' . $course['CourseTitle']); ?></td>
                                            <td><?php echo $course['enrollment']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Faculty Teaching Load -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Faculty Teaching Load</h5>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Faculty Name</th>
                                <th>Courses Taught</th>
                                <th>Total Students</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($facultyLoad as $faculty): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($faculty['FullName']); ?></td>
                                    <td><?php echo $faculty['courses_taught']; ?></td>
                                    <td><?php echo $faculty['total_students']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Chart initialization -->
    <script>
        // Grade distribution chart
        const gradeCtx = document.getElementById('gradeChart').getContext('2d');
        new Chart(gradeCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($gradeDistribution, 'Grade')); ?>,
                datasets: [{
                    label: 'Number of Students',
                    data: <?php echo json_encode(array_column($gradeDistribution, 'count')); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    </script>
</body>
</html> 