
USE AcademyDB_Extended;

-- Updated SystemUsers table with additional columns
CREATE TABLE IF NOT EXISTS SystemUsers (
    UserName         VARCHAR(50) PRIMARY KEY,
    UserPassword     VARBINARY(1000),
    LastLogin        DATETIME DEFAULT NULL,  -- Last successful login time
    FailedLoginCount INT DEFAULT 0           -- Counter for failed login attempts
);

DELIMITER //

-- Trigger: Synchronize Student changes (INSERT) with SystemUsers
CREATE TRIGGER IF NOT EXISTS trg_StuUserSync
AFTER INSERT ON Students
FOR EACH ROW
BEGIN
    INSERT INTO SystemUsers (UserName, UserPassword)
    VALUES (NEW.StudentID, NEW.UserPassword)
    ON DUPLICATE KEY UPDATE UserPassword = NEW.UserPassword;
END//

-- Trigger: Synchronize Student deletions
CREATE TRIGGER IF NOT EXISTS trg_StuUserSync_Delete
AFTER DELETE ON Students
FOR EACH ROW
BEGIN
    DELETE FROM SystemUsers WHERE UserName = OLD.StudentID;
END//

-- Trigger: Synchronize Student updates (password changes, etc.) with SystemUsers
CREATE TRIGGER IF NOT EXISTS trg_StuUserSync_Update
AFTER UPDATE ON Students
FOR EACH ROW
BEGIN
    UPDATE SystemUsers
    SET UserPassword = NEW.UserPassword
    WHERE UserName = NEW.StudentID;
END//

-- Trigger: Synchronize Faculty changes (INSERT) with SystemUsers
CREATE TRIGGER IF NOT EXISTS trg_FacultyUserSync
AFTER INSERT ON Faculty
FOR EACH ROW
BEGIN
    INSERT INTO SystemUsers (UserName, UserPassword)
    VALUES (NEW.FacultyID, NEW.UserPassword)
    ON DUPLICATE KEY UPDATE UserPassword = NEW.UserPassword;
END//

-- Trigger: Synchronize Faculty deletions
CREATE TRIGGER IF NOT EXISTS trg_FacultyUserSync_Delete
AFTER DELETE ON Faculty
FOR EACH ROW
BEGIN
    DELETE FROM SystemUsers WHERE UserName = OLD.FacultyID;
END//

-- Trigger: Synchronize Faculty updates (password changes, etc.) with SystemUsers
CREATE TRIGGER IF NOT EXISTS trg_FacultyUserSync_Update
AFTER UPDATE ON Faculty
FOR EACH ROW
BEGIN
    UPDATE SystemUsers
    SET UserPassword = NEW.UserPassword
    WHERE UserName = NEW.FacultyID;
END//

-- Additional triggers: automatically update LastModifiedOn/LastModifiedBy on Students & Faculty
CREATE TRIGGER IF NOT EXISTS trg_Students_UpdateTimestamps
BEFORE UPDATE ON Students
FOR EACH ROW
BEGIN
    SET NEW.LastModifiedOn = NOW();
    SET NEW.LastModifiedBy = SUBSTRING_INDEX(USER(), '@', 1);
END//

CREATE TRIGGER IF NOT EXISTS trg_Faculty_UpdateTimestamps
BEFORE UPDATE ON Faculty
FOR EACH ROW
BEGIN
    SET NEW.LastModifiedOn = NOW();
    SET NEW.LastModifiedBy = SUBSTRING_INDEX(USER(), '@', 1);
END//

DELIMITER ;







-- Combined view of user details
CREATE OR REPLACE VIEW UserDetailedView AS
SELECT 
    s.StudentID AS UserID,
    s.FullName,
    s.Email,
    s.Contact,
    'Student' AS UserType,
    su.LastLogin,
    su.FailedLoginCount
FROM Students s
JOIN SystemUsers su ON s.StudentID = su.UserName
UNION ALL
SELECT 
    f.FacultyID AS UserID,
    f.FullName,
    f.Email,
    f.Contact,
    'Faculty' AS UserType,
    su.LastLogin,
    su.FailedLoginCount
FROM Faculty f
JOIN SystemUsers su ON f.FacultyID = su.UserName;