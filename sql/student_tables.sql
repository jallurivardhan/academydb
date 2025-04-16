-- Course Materials Table
CREATE TABLE IF NOT EXISTS CourseMaterials (
    MaterialID INT PRIMARY KEY AUTO_INCREMENT,
    CourseID INT NOT NULL,
    Title VARCHAR(255) NOT NULL,
    Description TEXT,
    FilePath VARCHAR(255) NOT NULL,
    FileType VARCHAR(50),
    FileSize INT,
    UploadedBy INT NOT NULL,
    UploadDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (CourseID) REFERENCES Courses(CourseID),
    FOREIGN KEY (UploadedBy) REFERENCES Faculty(FacultyID)
);

-- Assignments Table
CREATE TABLE IF NOT EXISTS Assignments (
    AssignmentID INT PRIMARY KEY AUTO_INCREMENT,
    CourseID INT NOT NULL,
    Title VARCHAR(255) NOT NULL,
    Description TEXT,
    DueDate DATETIME NOT NULL,
    TotalPoints INT DEFAULT 100,
    CreatedBy INT NOT NULL,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (CourseID) REFERENCES Courses(CourseID),
    FOREIGN KEY (CreatedBy) REFERENCES Faculty(FacultyID)
);

-- Assignment Submissions Table
CREATE TABLE IF NOT EXISTS AssignmentSubmissions (
    SubmissionID INT PRIMARY KEY AUTO_INCREMENT,
    AssignmentID INT NOT NULL,
    StudentID INT NOT NULL,
    SubmissionText TEXT,
    FilePath VARCHAR(255),
    SubmissionDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    Grade DECIMAL(5,2),
    Feedback TEXT,
    GradedBy INT,
    GradedAt TIMESTAMP NULL,
    FOREIGN KEY (AssignmentID) REFERENCES Assignments(AssignmentID),
    FOREIGN KEY (StudentID) REFERENCES Students(StudentID),
    FOREIGN KEY (GradedBy) REFERENCES Faculty(FacultyID)
);

-- Create directories for uploads if they don't exist
CREATE TABLE IF NOT EXISTS SystemSettings (
    SettingID INT PRIMARY KEY AUTO_INCREMENT,
    SettingName VARCHAR(50) UNIQUE NOT NULL,
    SettingValue TEXT,
    LastUpdated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default upload paths
INSERT INTO SystemSettings (SettingName, SettingValue) VALUES
('MATERIALS_UPLOAD_PATH', '../uploads/materials/'),
('ASSIGNMENTS_UPLOAD_PATH', '../uploads/assignments/'),
('MAX_UPLOAD_SIZE', '5242880')  -- 5MB in bytes
ON DUPLICATE KEY UPDATE SettingValue = VALUES(SettingValue); 