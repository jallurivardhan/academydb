<?php
session_start();
require_once '../config/db_connect.php';

// Check if user is logged in and is faculty
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'FacultyRole') {
    header("Location: ../login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$error = '';
$success = '';
$courses = [];

try {
    // Get faculty's courses with a LEFT JOIN to include all courses assigned to the faculty
    $stmt = $pdo->prepare("
        SELECT DISTINCT c.*, 
            (SELECT COUNT(DISTINCT r.StudentID) 
             FROM Results r 
             WHERE r.CourseCode = c.CourseCode 
             AND r.FacultyID = ?) as enrolled_students
        FROM Courses c
        LEFT JOIN Results r ON c.CourseCode = r.CourseCode AND r.FacultyID = ?
        WHERE c.CourseCode IN (
            SELECT DISTINCT CourseCode 
            FROM Results 
            WHERE FacultyID = ?
        )
        ORDER BY c.CourseCode
    ");
    $stmt->execute([$userId, $userId, $userId]);
    $courses = $stmt->fetchAll();

    // If no courses are found, insert some test courses and assign them to the faculty
    if (empty($courses)) {
        // Begin transaction
        $pdo->beginTransaction();
        
        try {
            // Insert test courses if they don't exist
            $testCourses = [
                ['CS101', 'Introduction to Computer Science', 3, 'Undergraduate'],
                ['CS201', 'Data Structures', 3, 'Undergraduate'],
                ['CS301', 'Database Systems', 3, 'Undergraduate']
            ];
            
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO Courses (CourseCode, CourseTitle, Credits, CourseLevel)
                VALUES (?, ?, ?, ?)
            ");
            
            foreach ($testCourses as $course) {
                $stmt->execute($course);
            }
            
            // First, get up to 5 student IDs
            $stmt = $pdo->prepare("SELECT StudentID FROM Students ORDER BY StudentID LIMIT 5");
            $stmt->execute();
            $studentIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Then assign courses to faculty for each student
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO Results (StudentID, CourseCode, FacultyID, Grade)
                VALUES (?, ?, ?, NULL)
            ");
            
            foreach ($studentIds as $studentId) {
                foreach (['CS101', 'CS201', 'CS301'] as $courseCode) {
                    $stmt->execute([$studentId, $courseCode, $userId]);
                }
            }
            
            $pdo->commit();
            
            // Fetch courses again
            $stmt = $pdo->prepare("
                SELECT DISTINCT c.*, 
                    (SELECT COUNT(DISTINCT r.StudentID) 
                     FROM Results r 
                     WHERE r.CourseCode = c.CourseCode 
                     AND r.FacultyID = ?) as enrolled_students
                FROM Courses c
                LEFT JOIN Results r ON c.CourseCode = r.CourseCode AND r.FacultyID = ?
                WHERE c.CourseCode IN (
                    SELECT DISTINCT CourseCode 
                    FROM Results 
                    WHERE FacultyID = ?
                )
                ORDER BY c.CourseCode
            ");
            $stmt->execute([$userId, $userId, $userId]);
            $courses = $stmt->fetchAll();
            
            $success = 'Test courses have been added and assigned to your account.';
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error setting up test courses: ' . $e->getMessage();
        }
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
    <title>My Courses - AcademyDB Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../css/style.css" rel="stylesheet">
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
                        <a class="nav-link active" href="my_courses.php">My Courses</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="grades.php">Grades</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="attendance.php">Attendance</a>
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
        <h2>My Courses</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if (empty($courses)): ?>
            <div class="alert alert-info">You are not currently assigned to any courses.</div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($courses as $course): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($course['CourseCode']); ?></h5>
                                <h6 class="card-subtitle mb-2 text-muted"><?php echo htmlspecialchars($course['CourseTitle']); ?></h6>
                                <p class="card-text">
                                    <strong>Credits:</strong> <?php echo htmlspecialchars($course['Credits']); ?><br>
                                    <strong>Level:</strong> <?php echo htmlspecialchars($course['CourseLevel']); ?><br>
                                    <strong>Enrolled Students:</strong> <?php echo htmlspecialchars($course['enrolled_students']); ?>
                                </p>
                                <div class="btn-group">
                                    <a href="grades.php?course=<?php echo urlencode($course['CourseCode']); ?>" class="btn btn-primary">Manage Grades</a>
                                    <a href="attendance.php?course=<?php echo urlencode($course['CourseCode']); ?>" class="btn btn-secondary">Attendance</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 