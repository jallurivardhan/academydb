-- Create SystemUsers table
CREATE TABLE IF NOT EXISTS SystemUsers (
    UserID INT AUTO_INCREMENT PRIMARY KEY,
    UserName VARCHAR(50) UNIQUE NOT NULL,
    UserPassword VARCHAR(255) NOT NULL,
    LastLogin DATETIME,
    FailedLoginCount INT DEFAULT 0,
    CreatedOn DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Create Students table
CREATE TABLE IF NOT EXISTS Students (
    StudentID VARCHAR(20) PRIMARY KEY,
    FullName VARCHAR(100) NOT NULL,
    Contact VARCHAR(20),
    Email VARCHAR(100),
    AdditionalInfo TEXT,
    StudentStatus ENUM('Active', 'Inactive', 'Suspended') DEFAULT 'Active',
    CreatedOn DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Create Faculty table
CREATE TABLE IF NOT EXISTS Faculty (
    FacultyID VARCHAR(20) PRIMARY KEY,
    FullName VARCHAR(100) NOT NULL,
    Contact VARCHAR(20),
    Email VARCHAR(100),
    Department VARCHAR(50),
    Status ENUM('Active', 'Inactive') DEFAULT 'Active',
    CreatedOn DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Create Courses table
CREATE TABLE IF NOT EXISTS Courses (
    CourseCode VARCHAR(20) PRIMARY KEY,
    CourseTitle VARCHAR(100) NOT NULL,
    Credits INT NOT NULL,
    CourseLevel VARCHAR(20),
    Description TEXT,
    CreatedOn DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Create Results table
CREATE TABLE IF NOT EXISTS Results (
    ResultID INT AUTO_INCREMENT PRIMARY KEY,
    StudentID VARCHAR(20),
    FacultyID VARCHAR(20),
    CourseCode VARCHAR(20),
    AssessmentDate DATETIME,
    Grade VARCHAR(2),
    ExaminerComments TEXT,
    CreatedOn DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (StudentID) REFERENCES Students(StudentID),
    FOREIGN KEY (FacultyID) REFERENCES Faculty(FacultyID),
    FOREIGN KEY (CourseCode) REFERENCES Courses(CourseCode)
);

-- Create encryption function
DELIMITER //
CREATE FUNCTION fn_encrypt(password VARCHAR(100))
RETURNS VARCHAR(255)
DETERMINISTIC
BEGIN
    RETURN SHA2(password, 256);
END //
DELIMITER ; 