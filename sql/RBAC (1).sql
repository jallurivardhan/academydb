-- Create roles if not already created
CREATE ROLE IF NOT EXISTS AdminRole;
CREATE ROLE IF NOT EXISTS FacultyRole;
CREATE ROLE IF NOT EXISTS StudentRole;

-- AdminRole privileges:
--   - Full control over the database.
--   - Can create, modify, and delete users, roles, and database objects.
--   - Can manage all data but cannot access sensitive information directly.
GRANT ALL PRIVILEGES ON AcademyDB_Extended.* TO AdminRole WITH GRANT OPTION;
-- Sensitive information should be accessed only through secure views (e.g., AdminStudentView, AdminFacultyView).

-- Create initial MySQL users and assign roles
CREATE USER IF NOT EXISTS 'Admin1'@'localhost' IDENTIFIED BY 'AdminPass123!';
CREATE USER IF NOT EXISTS 'Admin2'@'localhost' IDENTIFIED BY 'AdminPass456!';
CREATE USER IF NOT EXISTS 'Stu01'@'localhost' IDENTIFIED BY 'StudentPass123!';
CREATE USER IF NOT EXISTS 'Stu02'@'localhost' IDENTIFIED BY 'StudentPass456!';
CREATE USER IF NOT EXISTS 'Fac01'@'localhost' IDENTIFIED BY 'FacultyPass123!';
CREATE USER IF NOT EXISTS 'Fac02'@'localhost' IDENTIFIED BY 'FacultyPass456!';

GRANT AdminRole TO 'Admin1'@'localhost', 'Admin2'@'localhost';
GRANT StudentRole TO 'Stu01'@'localhost', 'Stu02'@'localhost';
GRANT FacultyRole TO 'Fac01'@'localhost', 'Fac02'@'localhost';

SET DEFAULT ROLE ALL TO
    'Admin1'@'localhost',
    'Admin2'@'localhost',
    'Stu01'@'localhost',
    'Stu02'@'localhost',
    'Fac01'@'localhost',
    'Fac02'@'localhost';

-- Insert initial user credentials into SystemUsers table (assumes SystemUsers table and encryption function fn_encrypt exist)
INSERT IGNORE INTO SystemUsers (UserName, UserPassword)
VALUES
    ('Admin1', fn_encrypt('AdminPass123!')),
    ('Admin2', fn_encrypt('AdminPass456!')),
    ('Stu01', fn_encrypt('StudentPass123!')),
    ('Stu02', fn_encrypt('StudentPass456!')),
    ('Fac01', fn_encrypt('FacultyPass123!')),
    ('Fac02', fn_encrypt('FacultyPass456!'));

DELIMITER //

-- Procedure: Create a New User and assign a role dynamically
CREATE PROCEDURE sp_CreateNewUser(
    IN p_Username VARCHAR(50),
    IN p_Password VARCHAR(100),
    IN p_Role VARCHAR(50)
)
BEGIN
    SET @createUser = CONCAT('CREATE USER IF NOT EXISTS ''', p_Username, '''@''localhost'' IDENTIFIED BY ''', p_Password, '''');
    PREPARE stmt FROM @createUser;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
    
    SET @grantRole = CONCAT('GRANT ', p_Role, ' TO ''', p_Username, '''@''localhost''');
    PREPARE stmt FROM @grantRole;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
    
    SET @setDefault = CONCAT('SET DEFAULT ROLE ALL TO ''', p_Username, '''@''localhost''');
    PREPARE stmt FROM @setDefault;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
    
    SELECT CONCAT('User ', p_Username, ' created and assigned role ', p_Role) AS Message;
END//

-- Procedure: Assign an additional role to an existing user
CREATE PROCEDURE sp_AssignRoleToUser(
    IN p_Username VARCHAR(50),
    IN p_Role VARCHAR(50)
)
BEGIN
    SET @sql = CONCAT('GRANT ', p_Role, ' TO ''', p_Username, '''@''localhost''');
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
    SELECT CONCAT('Role ', p_Role, ' assigned to ', p_Username) AS Message;
END//

-- Procedure: Revoke a role from a user
CREATE PROCEDURE sp_RemoveUserFromRole(
    IN p_Username VARCHAR(50),
    IN p_Role VARCHAR(50)
)
BEGIN
    SET @sql = CONCAT('REVOKE ', p_Role, ' FROM ''', p_Username, '''@''localhost''');
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
    SELECT CONCAT('Role ', p_Role, ' revoked from ', p_Username) AS Message;
END//

-- Procedure: Delete a user from MySQL
CREATE PROCEDURE sp_DeleteUser(
    IN p_Username VARCHAR(50)
)
BEGIN
    SET @sql = CONCAT('DROP USER IF EXISTS ''', p_Username, '''@''localhost''');
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
    SELECT CONCAT('User ', p_Username, ' deleted.') AS Message;
END//

-- Procedure: Retrieve grants for a given user
CREATE PROCEDURE sp_ShowUserGrants(
    IN p_Username VARCHAR(50)
)
BEGIN
    SET @sql = CONCAT('SHOW GRANTS FOR ''', p_Username, '''@''localhost''');
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
END//

-- Procedure: List all users (from MySQL user table for localhost)
CREATE PROCEDURE sp_ListAllUsers()
BEGIN
    SELECT User, Host FROM mysql.user WHERE Host = 'localhost';
END//

-- Procedure: List all defined roles
CREATE PROCEDURE sp_ListAllRoles()
BEGIN
    SELECT 'AdminRole' AS Role
    UNION ALL
    SELECT 'FacultyRole'
    UNION ALL
    SELECT 'StudentRole';
END//

DELIMITER ;

-- FacultyRole Permissions:

-- Create a secure view for student data that excludes sensitive information.
CREATE OR REPLACE VIEW FacultyStudentView AS
SELECT StudentID, FullName, Contact
FROM Students;

-- Grant FacultyRole permission to view and manage course-related data.
GRANT SELECT, INSERT, UPDATE ON AcademyDB_Extended.Courses TO FacultyRole;
GRANT SELECT, INSERT, UPDATE ON AcademyDB_Extended.Results TO FacultyRole;

-- Grant FacultyRole access to the secure student view.
GRANT SELECT ON FacultyStudentView TO FacultyRole;

-- Revoke delete privileges for FacultyRole on course-related tables.
REVOKE DELETE ON AcademyDB_Extended.Courses FROM FacultyRole;
REVOKE DELETE ON AcademyDB_Extended.Results FROM FacultyRole;

-- FacultyRole Permissions:

-- Create a secure view for student data that excludes sensitive information.
CREATE OR REPLACE VIEW FacultyStudentView AS
SELECT StudentID, FullName, Contact
FROM Students;

-- Grant FacultyRole permission to view and manage course-related data.
GRANT SELECT, INSERT, UPDATE ON AcademyDB_Extended.Courses TO FacultyRole;
GRANT SELECT, INSERT, UPDATE ON AcademyDB_Extended.Results TO FacultyRole;

-- Grant FacultyRole access to the secure student view.
GRANT SELECT ON FacultyStudentView TO FacultyRole;

-- Create a secure view that returns only the current student's non-sensitive data.
CREATE OR REPLACE VIEW StudentSelfView AS
SELECT 
    StudentID,
    FullName,
    Contact,
    Email,
    AdditionalInfo,
    StudentStatus,
    CreatedOn
FROM Students
WHERE StudentID = SUBSTRING_INDEX(USER(), '@', 1);

-- Grant StudentRole the ability to SELECT and UPDATE only on the secure view.
GRANT SELECT, UPDATE ON AcademyDB_Extended.StudentSelfView TO StudentRole;

-- 1. AdminDashboardView:
--    Displays all users, roles, and permissions (simplified example).
CREATE OR REPLACE VIEW AdminDashboardView AS
SELECT 
    su.UserName,
    su.LastLogin,
    su.FailedLoginCount,
    'Role and permissions details managed externally' AS RolePermissions
FROM SystemUsers su;

-- 2. FacultyCourseView:
--    Displays course details along with assigned faculty and a list of students (aggregated).
CREATE OR REPLACE VIEW FacultyCourseView AS
SELECT
    c.CourseCode,
    c.CourseTitle,
    c.Credits,
    c.CourseLevel,
    r.FacultyID,
    f.FullName AS FacultyName,
    GROUP_CONCAT(DISTINCT s.StudentID ORDER BY s.StudentID SEPARATOR ', ') AS AssignedStudents
FROM Courses c
LEFT JOIN Results r ON c.CourseCode = r.CourseCode
LEFT JOIN Faculty f ON r.FacultyID = f.FacultyID
LEFT JOIN Students s ON r.StudentID = s.StudentID
GROUP BY c.CourseCode, r.FacultyID, f.FullName;

-- 3. StudentSelfView:
--    Displays a student's own record (excluding sensitive information).
CREATE OR REPLACE VIEW StudentSelfView AS
SELECT 
    StudentID,
    FullName,
    Contact,
    Email,
    AdditionalInfo,
    StudentStatus,
    CreatedOn
FROM Students
WHERE StudentID = SUBSTRING_INDEX(USER(), '@', 1);

-- 4. CourseResultsView:
--    Displays results for all students in a course.
CREATE OR REPLACE VIEW CourseResultsView AS
SELECT 
    r.ResultID,
    r.StudentID,
    r.FacultyID,
    r.CourseCode,
    r.AssessmentDate,
    r.Grade,
    r.ExaminerComments
FROM Results r;

-- 5. StudentResultsView:
--    Displays a student's own results.
CREATE OR REPLACE VIEW StudentResultsView AS
SELECT 
    r.ResultID,
    r.CourseCode,
    r.AssessmentDate,
    r.Grade,
    r.ExaminerComments
FROM Results r
WHERE r.StudentID = SUBSTRING_INDEX(USER(), '@', 1);

-- 6. FacultyResultsView:
--    Displays results for courses taught by the current faculty member.
CREATE OR REPLACE VIEW FacultyResultsView AS
SELECT 
    r.ResultID,
    r.StudentID,
    r.CourseCode,
    r.AssessmentDate,
    r.Grade,
    r.ExaminerComments
FROM Results r
WHERE r.FacultyID = SUBSTRING_INDEX(USER(), '@', 1);

-- 7. StudentAttendanceView:
--    Displays a student's own attendance records.
--    (Assumes an Attendance table exists with StudentID, CourseCode, AttendanceDate, Status.)
CREATE OR REPLACE VIEW StudentAttendanceView AS
SELECT 
    AttendanceID,
    CourseCode,
    AttendanceDate,
    Status
FROM Attendance
WHERE StudentID = SUBSTRING_INDEX(USER(), '@', 1);

-- ======================================================
-- Grant Statements for View Access
-- ======================================================
-- Only accessible to the AdminRole.
GRANT SELECT ON AcademyDB_Extended.AdminDashboardView TO AdminRole;

-- Only accessible to the FacultyRole.
GRANT SELECT ON AcademyDB_Extended.FacultyCourseView TO FacultyRole;

-- Only accessible to the StudentRole.
GRANT SELECT ON AcademyDB_Extended.StudentSelfView TO StudentRole;

-- Accessible to both AdminRole and FacultyRole.
GRANT SELECT ON AcademyDB_Extended.CourseResultsView TO AdminRole, FacultyRole;

-- Only accessible to the StudentRole.
GRANT SELECT ON AcademyDB_Extended.StudentResultsView TO StudentRole;

-- Only accessible to the FacultyRole.
GRANT SELECT ON AcademyDB_Extended.FacultyResultsView TO FacultyRole;

-- Only accessible to the StudentRole.
GRANT SELECT ON AcademyDB_Extended.StudentAttendanceView TO StudentRole;

-- StudentRole Permissions

-- Secure view: StudentSelfView shows only the current student's non-sensitive record.
CREATE OR REPLACE VIEW StudentSelfView AS
SELECT 
    StudentID,
    FullName,
    Contact,
    Email,
    AdditionalInfo,
    StudentStatus,
    CreatedOn
FROM Students
WHERE StudentID = SUBSTRING_INDEX(USER(), '@', 1);

-- Secure view: StudentResultsView shows only the current student's results.
CREATE OR REPLACE VIEW StudentResultsView AS
SELECT 
    ResultID,
    CourseCode,
    AssessmentDate,
    Grade,
    ExaminerComments
FROM Results
WHERE StudentID = SUBSTRING_INDEX(USER(), '@', 1);

-- Secure view: StudentAttendanceView shows only the current student's attendance records.
-- (Assumes an Attendance table exists with AttendanceID, CourseCode, AttendanceDate, Status, and StudentID columns.)
CREATE OR REPLACE VIEW StudentAttendanceView AS
SELECT 
    AttendanceID,
    CourseCode,
    AttendanceDate,
    Status
FROM Attendance
WHERE StudentID = SUBSTRING_INDEX(USER(), '@', 1);

-- Grant StudentRole read and update privileges on their own record (StudentSelfView)
GRANT SELECT, UPDATE ON AcademyDB_Extended.StudentSelfView TO StudentRole;

-- Grant StudentRole read-only privileges on their results and attendance views.
GRANT SELECT ON AcademyDB_Extended.StudentResultsView TO StudentRole;
GRANT SELECT ON AcademyDB_Extended.StudentAttendanceView TO StudentRole;

-- Procedures for Role-Based Operations

-- 1. CreateStudent: For AdminRole only
DELIMITER //
CREATE PROCEDURE sp_CreateStudent(
    IN p_StudentID VARCHAR(6),
    IN p_Password VARCHAR(100),
    IN p_FullName VARCHAR(100),
    IN p_Contact VARCHAR(20),
    IN p_Email VARCHAR(100),
    IN p_AdditionalInfo VARCHAR(255)
)
BEGIN
    INSERT INTO Students 
      (StudentID, UserPassword, FullName, Contact, Email, AdditionalInfo, CreatedByUser, CreatedOn)
    VALUES 
      (p_StudentID, fn_encrypt(p_Password), p_FullName, p_Contact, p_Email, p_AdditionalInfo, SUBSTRING_INDEX(USER(), '@', 1), NOW());
    SELECT CONCAT('Student ', p_StudentID, ' created successfully.') AS Message;
END//
DELIMITER ;

GRANT EXECUTE ON PROCEDURE AcademyDB_Extended.sp_CreateStudent TO AdminRole;


-- 2. UpdateStudentInfo: For StudentRole only (students update their own record)
DELIMITER //
CREATE PROCEDURE sp_UpdateStudentInfo(
    IN p_Contact VARCHAR(20),
    IN p_Email VARCHAR(100),
    IN p_AdditionalInfo VARCHAR(255)
)
BEGIN
    DECLARE currUser VARCHAR(50);
    SET currUser = SUBSTRING_INDEX(USER(), '@', 1);
    UPDATE Students 
    SET Contact = COALESCE(p_Contact, Contact),
        Email = COALESCE(p_Email, Email),
        AdditionalInfo = COALESCE(p_AdditionalInfo, AdditionalInfo),
        LastModifiedBy = currUser,
        LastModifiedOn = NOW()
    WHERE StudentID = currUser;
    SELECT CONCAT('Student ', currUser, ' information updated successfully.') AS Message;
END//
DELIMITER ;

GRANT EXECUTE ON PROCEDURE AcademyDB_Extended.sp_UpdateStudentInfo TO StudentRole;


-- 3. UpdateCourseResults: For FacultyRole only (faculty update results for their own courses)
DELIMITER //
CREATE PROCEDURE sp_UpdateCourseResults(
    IN p_ResultID INT,
    IN p_Grade VARCHAR(2),
    IN p_ExaminerComments VARCHAR(255)
)
BEGIN
    DECLARE currFaculty VARCHAR(50);
    SET currFaculty = SUBSTRING_INDEX(USER(), '@', 1);
    IF EXISTS(SELECT 1 FROM Results WHERE ResultID = p_ResultID AND FacultyID = currFaculty) THEN
        UPDATE Results
        SET Grade = p_Grade,
            ExaminerComments = p_ExaminerComments,
            LastModifiedBy = currFaculty,
            LastModifiedOn = NOW()
        WHERE ResultID = p_ResultID;
        SELECT CONCAT('Result ', p_ResultID, ' updated successfully by ', currFaculty) AS Message;
    ELSE
        SELECT 'Error: You are not authorized to update this result.' AS Message;
    END IF;
END//
DELIMITER ;

GRANT EXECUTE ON PROCEDURE AcademyDB_Extended.sp_UpdateCourseResults TO FacultyRole;


-- 4. GenerateCourseReport: For AdminRole only
DELIMITER //
CREATE PROCEDURE sp_GenerateCourseReport(
    IN p_CourseCode VARCHAR(5)
)
BEGIN
    SELECT c.CourseCode, c.CourseTitle, c.Credits, c.CourseLevel,
           COUNT(r.ResultID) AS TotalResults,
           AVG(CASE 
                    WHEN r.Grade = 'A' THEN 4 
                    WHEN r.Grade = 'B' THEN 3 
                    WHEN r.Grade = 'C' THEN 2 
                    WHEN r.Grade = 'D' THEN 1 
                    ELSE 0 
               END) AS AverageScore
    FROM Courses c
    LEFT JOIN Results r ON c.CourseCode = r.CourseCode
    WHERE c.CourseCode = p_CourseCode
    GROUP BY c.CourseCode, c.CourseTitle, c.Credits, c.CourseLevel;
END//
DELIMITER ;

GRANT EXECUTE ON PROCEDURE AcademyDB_Extended.sp_GenerateCourseReport TO AdminRole;


-- 5. MarkAttendance: For FacultyRole only
-- Assumes an Attendance table exists with columns: AttendanceID (AUTO_INCREMENT), StudentID, CourseCode, AttendanceDate, Status, and MarkedBy.
DELIMITER //
CREATE PROCEDURE sp_MarkAttendance(
    IN p_StudentID VARCHAR(6),
    IN p_CourseCode VARCHAR(5),
    IN p_AttendanceDate DATE,
    IN p_Status VARCHAR(20)
)
BEGIN
    DECLARE currFaculty VARCHAR(50);
    SET currFaculty = SUBSTRING_INDEX(USER(), '@', 1);
    INSERT INTO Attendance (StudentID, CourseCode, AttendanceDate, Status, MarkedBy)
    VALUES (p_StudentID, p_CourseCode, p_AttendanceDate, p_Status, currFaculty);
    SELECT CONCAT('Attendance marked for student ', p_StudentID, ' in course ', p_CourseCode, '.') AS Message;
END//
DELIMITER ;

GRANT EXECUTE ON PROCEDURE AcademyDB_Extended.sp_MarkAttendance TO FacultyRole;

-- 1. PreventSensitiveDataModification for Students:
DELIMITER //
CREATE TRIGGER trg_PreventSensitiveDataModification_Students
BEFORE UPDATE ON Students
FOR EACH ROW
BEGIN
    IF NEW.UserPassword <> OLD.UserPassword THEN
       IF CURRENT_ROLE() <> 'AdminRole' THEN
           SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Modification of sensitive data (UserPassword) is allowed only for AdminRole.';
       END IF;
    END IF;
END//
DELIMITER ;

-- For Faculty:
DELIMITER //
CREATE TRIGGER trg_PreventSensitiveDataModification_Faculty
BEFORE UPDATE ON Faculty
FOR EACH ROW
BEGIN
    IF NEW.UserPassword <> OLD.UserPassword THEN
       IF CURRENT_ROLE() <> 'AdminRole' THEN
           SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Modification of sensitive data (UserPassword) is allowed only for AdminRole.';
       END IF;
    END IF;
END//
DELIMITER ;

-- 2. ValidateCourseResults: Ensure grade is between A and F.
DELIMITER //
CREATE TRIGGER trg_ValidateCourseResults_Insert
BEFORE INSERT ON Results
FOR EACH ROW
BEGIN
    IF NEW.Grade NOT IN ('A','B','C','D','E','F') THEN
         SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid grade entered. Grade must be between A and F.';
    END IF;
END//
DELIMITER ;

DELIMITER //
CREATE TRIGGER trg_ValidateCourseResults_Update
BEFORE UPDATE ON Results
FOR EACH ROW
BEGIN
    IF NEW.Grade NOT IN ('A','B','C','D','E','F') THEN
         SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid grade entered. Grade must be between A and F.';
    END IF;
END//
DELIMITER ;

-- 3. PreventUnauthorizedDeletion: Only AdminRole can delete records.
-- For Students:
DELIMITER //
CREATE TRIGGER trg_PreventUnauthorizedDeletion_Students
BEFORE DELETE ON Students
FOR EACH ROW
BEGIN
    IF CURRENT_ROLE() <> 'AdminRole' THEN
       SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only AdminRole can delete records from critical tables (Students).';
    END IF;
END//
DELIMITER ;

-- For Faculty:
DELIMITER //
CREATE TRIGGER trg_PreventUnauthorizedDeletion_Faculty
BEFORE DELETE ON Faculty
FOR EACH ROW
BEGIN
    IF CURRENT_ROLE() <> 'AdminRole' THEN
       SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only AdminRole can delete records from critical tables (Faculty).';
    END IF;
END//
DELIMITER ;

-- For Courses:
DELIMITER //
CREATE TRIGGER trg_PreventUnauthorizedDeletion_Courses
BEFORE DELETE ON Courses
FOR EACH ROW
BEGIN
    IF CURRENT_ROLE() <> 'AdminRole' THEN
       SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only AdminRole can delete records from critical tables (Courses).';
    END IF;
END//
DELIMITER ;

-- For Results:
DELIMITER //
CREATE TRIGGER trg_PreventUnauthorizedDeletion_Results
BEFORE DELETE ON Results
FOR EACH ROW
BEGIN
    IF CURRENT_ROLE() <> 'AdminRole' THEN
       SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only AdminRole can delete records from critical tables (Results).';
    END IF;
END//
DELIMITER ;

-- AdminRole: History Tables SELECT
GRANT SELECT ON AcademyDB_Extended.Students_History TO AdminRole;
GRANT SELECT ON AcademyDB_Extended.Admin_History TO AdminRole;
GRANT SELECT ON AcademyDB_Extended.Faculty_History TO AdminRole;
GRANT SELECT ON AcademyDB_Extended.Courses_History TO AdminRole;
GRANT SELECT ON AcademyDB_Extended.Results_History TO AdminRole;

-- AdminRole: Indexes & Foreign Keys (using ALTER, CREATE, DROP)
GRANT ALTER, CREATE, DROP ON AcademyDB_Extended.* TO AdminRole;

-- AdminRole: Backup and Restore operations (typical privileges)
GRANT SELECT, LOCK TABLES, SHOW VIEW, EVENT ON AcademyDB_Extended.* TO AdminRole;

-- Create secure view for Students_History (non-sensitive columns only)
CREATE OR REPLACE VIEW Faculty_Students_History_View AS
SELECT 
    StudentID,
    FullName,
    Contact,
    CreatedOn,
    LastModifiedOn
FROM Students_History;
GRANT SELECT ON AcademyDB_Extended.Faculty_Students_History_View TO FacultyRole;

-- Create secure view for Faculty_History (only own record)
CREATE OR REPLACE VIEW Faculty_History_View AS
SELECT *
FROM Faculty_History
WHERE FacultyID = SUBSTRING_INDEX(USER(), '@', 1);
GRANT SELECT ON AcademyDB_Extended.Faculty_History_View TO FacultyRole;

-- Grant direct SELECT on Courses_History and Results_History
GRANT SELECT ON AcademyDB_Extended.Courses_History TO FacultyRole;
GRANT SELECT ON AcademyDB_Extended.Results_History TO FacultyRole;

-- Create secure view for Students_History for the current student
CREATE OR REPLACE VIEW Student_History_View AS
SELECT 
    StudentID,
    FullName,
    Contact,
    CreatedOn,
    LastModifiedOn
FROM Students_History
WHERE StudentID = SUBSTRING_INDEX(USER(), '@', 1);
GRANT SELECT ON AcademyDB_Extended.Student_History_View TO StudentRole;

-- Create secure view for Results_History for the current student
CREATE OR REPLACE VIEW Student_Results_History_View AS
SELECT *
FROM Results_History
WHERE StudentID = SUBSTRING_INDEX(USER(), '@', 1);
GRANT SELECT ON AcademyDB_Extended.Student_Results_History_View TO StudentRole;


