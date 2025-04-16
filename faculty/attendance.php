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
$students = [];
$courses = [];
$selectedCourse = $_GET['course'] ?? '';
$selectedDate = $_GET['date'] ?? date('Y-m-d');

try {
    // Create Attendance table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS Attendance (
            AttendanceID INT PRIMARY KEY AUTO_INCREMENT,
            StudentID VARCHAR(20),
            CourseCode VARCHAR(10),
            AttendanceDate DATE,
            Status ENUM('Present', 'Absent', 'Late', 'Excused'),
            FOREIGN KEY (StudentID) REFERENCES Students(StudentID),
            FOREIGN KEY (CourseCode) REFERENCES Courses(CourseCode),
            UNIQUE KEY unique_attendance (StudentID, CourseCode, AttendanceDate)
        )
    ");

    // Get faculty's courses with improved query
    $stmt = $pdo->prepare("
        SELECT DISTINCT c.CourseCode, c.CourseTitle
        FROM Courses c
        WHERE c.CourseCode IN (
            SELECT DISTINCT CourseCode 
            FROM Results 
            WHERE FacultyID = ?
        )
        ORDER BY c.CourseCode
    ");
    $stmt->execute([$userId]);
    $courses = $stmt->fetchAll();

    // If a course is selected, get its students
    if ($selectedCourse) {
        $stmt = $pdo->prepare("
            SELECT s.StudentID, s.FullName,
                   (SELECT Status 
                    FROM Attendance 
                    WHERE StudentID = s.StudentID 
                    AND CourseCode = ? 
                    AND AttendanceDate = ?) as Status
            FROM Students s
            JOIN Results r ON s.StudentID = r.StudentID
            WHERE r.CourseCode = ? AND r.FacultyID = ?
            ORDER BY s.FullName
        ");
        $stmt->execute([$selectedCourse, $selectedDate, $selectedCourse, $userId]);
        $students = $stmt->fetchAll();
    }

    // Handle attendance submissions
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] == 'mark_attendance') {
            $attendanceData = $_POST['attendance'] ?? [];
            $date = $_POST['date'] ?? '';
            $courseCode = $_POST['course_code'] ?? '';

            if (empty($date) || empty($courseCode) || empty($attendanceData)) {
                $error = 'Missing required attendance data.';
            } else {
                // Begin transaction
                $pdo->beginTransaction();
                try {
                    // Delete existing attendance records for this date and course
                    $stmt = $pdo->prepare("
                        DELETE FROM Attendance 
                        WHERE CourseCode = ? AND AttendanceDate = ?
                    ");
                    $stmt->execute([$courseCode, $date]);

                    // Insert new attendance records
                    $stmt = $pdo->prepare("
                        INSERT INTO Attendance (StudentID, CourseCode, AttendanceDate, Status)
                        VALUES (?, ?, ?, ?)
                    ");
                    foreach ($attendanceData as $studentId => $status) {
                        $stmt->execute([$studentId, $courseCode, $date, $status]);
                    }

                    $pdo->commit();
                    $success = 'Attendance marked successfully.';

                    // Refresh student list
                    $stmt = $pdo->prepare("
                        SELECT s.StudentID, s.FullName,
                               (SELECT Status 
                                FROM Attendance 
                                WHERE StudentID = s.StudentID 
                                AND CourseCode = ? 
                                AND AttendanceDate = ?) as Status
                        FROM Students s
                        JOIN Results r ON s.StudentID = r.StudentID
                        WHERE r.CourseCode = ? AND r.FacultyID = ?
                        ORDER BY s.FullName
                    ");
                    $stmt->execute([$selectedCourse, $selectedDate, $selectedCourse, $userId]);
                    $students = $stmt->fetchAll();

                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Error marking attendance: ' . $e->getMessage();
                }
            }
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
    <title>Attendance Management - AcademyDB Management System</title>
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
                        <a class="nav-link" href="my_courses.php">My Courses</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="grades.php">Grades</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="attendance.php">Attendance</a>
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
        <h2>Attendance Management</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Course and Date Selection -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="attendance.php" class="row g-3">
                    <div class="col-md-4">
                        <label for="course" class="form-label">Select Course</label>
                        <select class="form-select" id="course" name="course" required>
                            <option value="">Choose a course...</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo htmlspecialchars($course['CourseCode']); ?>"
                                        <?php echo $selectedCourse === $course['CourseCode'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['CourseCode'] . ' - ' . $course['CourseTitle']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="date" class="form-label">Select Date</label>
                        <input type="date" class="form-control" id="date" name="date" 
                               value="<?php echo htmlspecialchars($selectedDate); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary d-block">Load Attendance</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Attendance Form -->
        <?php if ($selectedCourse && $selectedDate): ?>
            <?php if (empty($students)): ?>
                <div class="alert alert-info">No students found for this course.</div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="attendance.php?course=<?php echo urlencode($selectedCourse); ?>&date=<?php echo urlencode($selectedDate); ?>">
                            <input type="hidden" name="action" value="mark_attendance">
                            <input type="hidden" name="course_code" value="<?php echo htmlspecialchars($selectedCourse); ?>">
                            <input type="hidden" name="date" value="<?php echo htmlspecialchars($selectedDate); ?>">
                            
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Student Name</th>
                                            <th>Attendance Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $student): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($student['FullName']); ?></td>
                                                <td>
                                                    <select class="form-select" name="attendance[<?php echo $student['StudentID']; ?>]" required>
                                                        <option value="Present" <?php echo $student['Status'] === 'Present' ? 'selected' : ''; ?>>Present</option>
                                                        <option value="Absent" <?php echo $student['Status'] === 'Absent' ? 'selected' : ''; ?>>Absent</option>
                                                        <option value="Late" <?php echo $student['Status'] === 'Late' ? 'selected' : ''; ?>>Late</option>
                                                        <option value="Excused" <?php echo $student['Status'] === 'Excused' ? 'selected' : ''; ?>>Excused</option>
                                                    </select>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <button type="submit" class="btn btn-primary">Save Attendance</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 