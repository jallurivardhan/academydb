USE academydb;

CREATE TABLE IF NOT EXISTS Attendance (
    AttendanceID INT PRIMARY KEY AUTO_INCREMENT,
    StudentID VARCHAR(20),
    CourseCode VARCHAR(10),
    AttendanceDate DATE,
    Status ENUM('Present', 'Absent', 'Late', 'Excused'),
    FOREIGN KEY (StudentID) REFERENCES Students(StudentID),
    FOREIGN KEY (CourseCode) REFERENCES Courses(CourseCode),
    UNIQUE KEY unique_attendance (StudentID, CourseCode, AttendanceDate)
); 