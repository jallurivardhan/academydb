
-- Create Database and set active database
CREATE DATABASE IF NOT EXISTS AcademyDB_Extended;
USE AcademyDB_Extended;

-- Students Table Creation
CREATE TABLE IF NOT EXISTS Students (
    StudentID      VARCHAR(6)    PRIMARY KEY,
    UserPassword   VARBINARY(1000),
    FullName       VARCHAR(100)  NOT NULL,
    Contact        VARCHAR(20),
    Email          VARCHAR(100)  UNIQUE,                  
    AdditionalInfo VARCHAR(255),
    StudentStatus  ENUM('Active', 'Inactive') DEFAULT 'Active',
    CreatedByUser  VARCHAR(50),
    CreatedOn      DATETIME      DEFAULT CURRENT_TIMESTAMP,
    LastModifiedBy VARCHAR(50),                              
    LastModifiedOn DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
    SysStart       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    SysEnd         TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_Students_FullName (FullName),                   
    INDEX idx_Students_Contact (Contact)
);

-- Admin Table Creation
CREATE TABLE IF NOT EXISTS Admin (
    AdminID        VARCHAR(6)    PRIMARY KEY,
    UserPassword   VARBINARY(1000),
    FullName       VARCHAR(100)  NOT NULL,
    Contact        VARCHAR(20),
    Email          VARCHAR(100)  UNIQUE,
    AdditionalInfo VARCHAR(255),
    AdminStatus    ENUM('Active', 'Inactive') DEFAULT 'Active',
    CreatedByUser  VARCHAR(50),
    CreatedOn      DATETIME      DEFAULT CURRENT_TIMESTAMP,
    LastModifiedBy VARCHAR(50),
    LastModifiedOn DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    SysStart       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    SysEnd         TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_Admin_FullName (FullName),
    INDEX idx_Admin_Contact (Contact)
);

-- Admin History Table Creation
CREATE TABLE IF NOT EXISTS Admin_History LIKE Admin;


-- Faculty Table Creation
CREATE TABLE IF NOT EXISTS Faculty (
    FacultyID      VARCHAR(6)    PRIMARY KEY,
    UserPassword   VARBINARY(1000),
    FullName       VARCHAR(100)  NOT NULL,
    Contact        VARCHAR(20),
    Email          VARCHAR(100)  UNIQUE,                 
    Dept           VARCHAR(30),
    AdditionalInfo VARCHAR(255),
    FacultyStatus  ENUM('Active', 'Inactive') DEFAULT 'Active', 
    CreatedByUser  VARCHAR(50),
    CreatedOn      DATETIME      DEFAULT CURRENT_TIMESTAMP,
    LastModifiedBy VARCHAR(50),                           
    LastModifiedOn DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
    SysStart       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    SysEnd         TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_Faculty_FullName (FullName),
    INDEX idx_Faculty_Contact (Contact)
);

-- Courses Table Creation
CREATE TABLE IF NOT EXISTS Courses (
    CourseCode   VARCHAR(5)    PRIMARY KEY,
    CourseTitle  VARCHAR(30),
    Credits      INT DEFAULT 3,                                 
    CourseLevel  ENUM('Undergraduate', 'Graduate') DEFAULT 'Undergraduate', 
    SysStart     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    SysEnd       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_Courses_Title (CourseTitle)
);

-- Results Table Creation
CREATE TABLE IF NOT EXISTS Results (
    ResultID         INT          PRIMARY KEY AUTO_INCREMENT,
    StudentID        VARCHAR(6),
    FacultyID        VARCHAR(6),
    CourseCode       VARCHAR(5),
    AssessmentDate   DATE,
    Grade            VARCHAR(2),
    ExaminerComments VARCHAR(255),                             
    CreatedByUser    VARCHAR(50),                             
    CreatedOn        DATETIME     DEFAULT CURRENT_TIMESTAMP,    
    LastModifiedBy   VARCHAR(50),                             
    LastModifiedOn   DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    SysStart         TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    SysEnd           TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (StudentID) REFERENCES Students(StudentID),
    FOREIGN KEY (FacultyID) REFERENCES Faculty(FacultyID),
    FOREIGN KEY (CourseCode) REFERENCES Courses(CourseCode),
    INDEX idx_Results_AssessmentDate (AssessmentDate)
);

-- History Tables Creation
CREATE TABLE IF NOT EXISTS Students_History LIKE Students;
CREATE TABLE IF NOT EXISTS Faculty_History LIKE Faculty;
CREATE TABLE IF NOT EXISTS Courses_History LIKE Courses;
CREATE TABLE IF NOT EXISTS Results_History LIKE Results;
