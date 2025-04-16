<?php
// Get student's enrolled courses
$stmt = $pdo->prepare("
    SELECT c.*, fn_MaskEmail(f.FullName) as FacultyName
    FROM Courses c
    JOIN Faculty f ON c.FacultyID = f.FacultyID
    JOIN Enrollments e ON c.CourseCode = e.CourseCode
    WHERE e.StudentID = ?
    ORDER BY c.CourseCode
");

$stmt->execute([$studentID]);

$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ... rest of the file ... 