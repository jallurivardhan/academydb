-- Insert test data for SystemUsers
INSERT INTO SystemUsers (UserName, UserPassword, LastLogin) VALUES
('admin', SHA2('admin123', 256), NOW()),
('faculty1', SHA2('faculty123', 256), NOW()),
('student1', SHA2('student123', 256), NOW());

-- Insert test data for Students
INSERT INTO Students (StudentID, FullName, Contact, Email, StudentStatus) VALUES
('S001', 'John Doe', '1234567890', 'john.doe@example.com', 'Active'),
('S002', 'Jane Smith', '0987654321', 'jane.smith@example.com', 'Active');

-- Insert test data for Faculty
INSERT INTO Faculty (FacultyID, FullName, Contact, Email, Dept, FacultyStatus) VALUES
('F001', 'Dr. Robert Brown', '5555555555', 'robert.brown@example.com', 'Computer Science', 'Active'),
('F002', 'Dr. Sarah Wilson', '4444444444', 'sarah.wilson@example.com', 'Mathematics', 'Active');

-- Insert test data for Courses
INSERT INTO Courses (CourseCode, CourseTitle, Credits, CourseLevel) VALUES
('CS101', 'Introduction to Programming', 3, 'Undergraduate'),
('MATH201', 'Advanced Calculus', 4, 'Undergraduate'),
('CS501', 'Database Systems', 3, 'Graduate');

-- Insert test data for Results
INSERT INTO Results (StudentID, FacultyID, CourseCode, AssessmentDate, Grade, ExaminerComments) VALUES
('S001', 'F001', 'CS101', '2024-03-15', 'A', 'Excellent work'),
('S002', 'F002', 'MATH201', '2024-03-15', 'B+', 'Good understanding of concepts');

-- Test Queries
-- 1. Get all active students
SELECT * FROM Students WHERE StudentStatus = 'Active';

-- 2. Get all courses taught by a specific faculty
SELECT c.* 
FROM Courses c
JOIN Results r ON c.CourseCode = r.CourseCode
WHERE r.FacultyID = 'F001'
GROUP BY c.CourseCode;

-- 3. Get student results with course and faculty information
SELECT 
    s.FullName as StudentName,
    c.CourseTitle,
    f.FullName as FacultyName,
    r.Grade,
    r.AssessmentDate
FROM Results r
JOIN Students s ON r.StudentID = s.StudentID
JOIN Courses c ON r.CourseCode = c.CourseCode
JOIN Faculty f ON r.FacultyID = f.FacultyID
ORDER BY r.AssessmentDate DESC; 