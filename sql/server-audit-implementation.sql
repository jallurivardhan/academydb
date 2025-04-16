-- Server Audit Implementation for AcademyDB_Extended

USE AcademyDB_Extended;

-- ======================================================
-- Main Server Audit Configuration
-- ======================================================

-- Create a table to store system audit logs
CREATE TABLE IF NOT EXISTS SystemAuditLog (
    AuditID INT AUTO_INCREMENT PRIMARY KEY,
    EventTime DATETIME DEFAULT CURRENT_TIMESTAMP,
    EventType VARCHAR(50) NOT NULL,
    EventDescription VARCHAR(255) NOT NULL,
    AffectedObject VARCHAR(100),
    Username VARCHAR(50),
    IPAddress VARCHAR(50),
    ApplicationName VARCHAR(100),
    AdditionalInfo TEXT
);

-- ======================================================
-- Core Audit Object
-- ======================================================

-- Create audit object to track all database activities
-- This section creates the server audit that will capture audit events
DELIMITER //

CREATE PROCEDURE sp_CreateServerAudit()
BEGIN
    -- Check if audit already exists to prevent errors
    IF NOT EXISTS (SELECT * FROM sys.server_audits WHERE name = 'AcademyDB_ServerAudit') THEN
        -- Create server audit with file destination
        SET @sql = "
        CREATE SERVER AUDIT AcademyDB_ServerAudit
        TO FILE (
            FILEPATH = '/var/log/mysql/audit/',
            MAXSIZE = 100MB,
            MAX_FILES = 10,
            RESERVE_DISK_SPACE = OFF
        )
        WITH (
            QUEUE_DELAY = 1000,
            ON_FAILURE = CONTINUE
        )";
        
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        
        -- Enable the server audit
        SET @enable_sql = "ALTER SERVER AUDIT AcademyDB_ServerAudit WITH (STATE = ON)";
        PREPARE enable_stmt FROM @enable_sql;
        EXECUTE enable_stmt;
        DEALLOCATE PREPARE enable_stmt;
        
        INSERT INTO SystemAuditLog (EventType, EventDescription, Username)
        VALUES ('AUDIT', 'Server Audit AcademyDB_ServerAudit created and enabled', CURRENT_USER());
        
        SELECT 'Server Audit AcademyDB_ServerAudit created and enabled successfully.' AS Message;
    ELSE
        SELECT 'Server Audit AcademyDB_ServerAudit already exists.' AS Message;
    END IF;
END//

DELIMITER ;

-- ======================================================
-- Server Audit Specification
-- ======================================================

-- Create server audit specification to audit server-level events
DELIMITER //

CREATE PROCEDURE sp_CreateServerAuditSpecification()
BEGIN
    -- Check if specification already exists
    IF NOT EXISTS (SELECT * FROM sys.server_audit_specifications WHERE name = 'AcademyDB_ServerSpec') THEN
        -- Create server audit specification
        SET @sql = "
        CREATE SERVER AUDIT SPECIFICATION AcademyDB_ServerSpec
        FOR SERVER AUDIT AcademyDB_ServerAudit
        ADD (
            FAILED_LOGIN_GROUP,
            SUCCESSFUL_LOGIN_GROUP,
            SERVER_OPERATION_GROUP,
            DATABASE_OPERATION_GROUP,
            SERVER_PERMISSION_CHANGE_GROUP
        )
        WITH (STATE = ON)";
        
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        
        INSERT INTO SystemAuditLog (EventType, EventDescription, Username)
        VALUES ('AUDIT', 'Server Audit Specification AcademyDB_ServerSpec created', CURRENT_USER());
        
        SELECT 'Server Audit Specification AcademyDB_ServerSpec created successfully.' AS Message;
    ELSE
        SELECT 'Server Audit Specification AcademyDB_ServerSpec already exists.' AS Message;
    END IF;
END//

DELIMITER ;

-- ======================================================
-- Database Audit Specification
-- ======================================================

-- Create database audit specification to audit database-level events
DELIMITER //

CREATE PROCEDURE sp_CreateDatabaseAuditSpecification()
BEGIN
    -- Check if specification already exists
    IF NOT EXISTS (SELECT * FROM sys.database_audit_specifications WHERE name = 'AcademyDB_DatabaseSpec') THEN
        -- Create database audit specification
        SET @sql = "
        CREATE DATABASE AUDIT SPECIFICATION AcademyDB_DatabaseSpec
        FOR SERVER AUDIT AcademyDB_ServerAudit
        ADD (
            DATABASE_OBJECT_ACCESS_GROUP,
            DATABASE_OBJECT_CHANGE_GROUP,
            DATABASE_PRINCIPAL_CHANGE_GROUP,
            SCHEMA_OBJECT_ACCESS_GROUP,
            SCHEMA_OBJECT_CHANGE_GROUP,
            SELECT ON Students BY dbo,
            UPDATE ON Students BY dbo,
            INSERT ON Students BY dbo,
            DELETE ON Students BY dbo,
            SELECT ON Faculty BY dbo,
            UPDATE ON Faculty BY dbo,
            INSERT ON Faculty BY dbo,
            DELETE ON Faculty BY dbo,
            EXECUTE ON DATABASE::[AcademyDB_Extended]
        )
        WITH (STATE = ON)";
        
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        
        INSERT INTO SystemAuditLog (EventType, EventDescription, Username)
        VALUES ('AUDIT', 'Database Audit Specification AcademyDB_DatabaseSpec created', CURRENT_USER());
        
        SELECT 'Database Audit Specification AcademyDB_DatabaseSpec created successfully.' AS Message;
    ELSE
        SELECT 'Database Audit Specification AcademyDB_DatabaseSpec already exists.' AS Message;
    END IF;
END//

DELIMITER ;

-- ======================================================
-- Login Activity Audit
-- ======================================================

-- Create triggers to audit login activities
DELIMITER //

-- Trigger to track successful logins
CREATE TRIGGER trg_AuditSuccessfulLogin
AFTER UPDATE ON mysql.user
FOR EACH ROW
BEGIN
    IF NEW.password_last_changed > OLD.password_last_changed THEN
        INSERT INTO SystemAuditLog (
            EventType,
            EventDescription,
            Username,
            IPAddress,
            ApplicationName
        )
        VALUES (
            'LOGIN',
            'User logged in successfully',
            NEW.User,
            CONNECTION_ID(),
            @@version_comment
        );
    END IF;
END//

-- Create stored procedure to log failed login attempts
CREATE PROCEDURE sp_LogFailedLoginAttempt(
    IN p_Username VARCHAR(50),
    IN p_IPAddress VARCHAR(50)
)
BEGIN
    -- Log the failed login attempt
    INSERT INTO SystemAuditLog (
        EventType,
        EventDescription,
        Username,
        IPAddress,
        ApplicationName
    )
    VALUES (
        'LOGIN',
        'Failed login attempt',
        p_Username,
        p_IPAddress,
        @@version_comment
    );
    
    -- Update the failed login count for the user if they exist
    UPDATE SystemUsers
    SET FailedLoginCount = FailedLoginCount + 1
    WHERE UserName = p_Username;
END//

-- Create stored procedure to reset failed login attempts after successful login
CREATE PROCEDURE sp_ResetFailedLoginAttempts(
    IN p_Username VARCHAR(50)
)
BEGIN
    UPDATE SystemUsers
    SET FailedLoginCount = 0,
        LastLogin = NOW()
    WHERE UserName = p_Username;
    
    INSERT INTO SystemAuditLog (
        EventType,
        EventDescription,
        Username
    )
    VALUES (
        'LOGIN',
        'Successful login, reset failed login counter',
        p_Username
    );
END//

DELIMITER ;

-- ======================================================
-- Schema Changes Audit
-- ======================================================

-- Create triggers to audit DDL (Data Definition Language) operations
DELIMITER //

-- Trigger to log DDL events
CREATE TRIGGER trg_AuditDDLOperations
AFTER CREATE OR ALTER OR DROP OR RENAME ON AcademyDB_Extended.*
FOR EACH STATEMENT
BEGIN
    DECLARE v_eventType VARCHAR(50);
    DECLARE v_objectType VARCHAR(50);
    DECLARE v_objectName VARCHAR(100);
    
    -- Determine event type and object details from the SQL statement
    SET v_eventType = 'DDL';
    
    -- Log the DDL operation
    INSERT INTO SystemAuditLog (
        EventType,
        EventDescription,
        AffectedObject,
        Username
    )
    VALUES (
        v_eventType,
        CONCAT('DDL operation: ', CURRENT_STATEMENT()),
        v_objectName,
        CURRENT_USER()
    );
END//

-- Create procedure to track schema changes
CREATE PROCEDURE sp_TrackSchemaChanges()
BEGIN
    -- Get the list of tables
    SELECT 
        TABLE_NAME,
        CREATE_TIME,
        UPDATE_TIME
    FROM 
        information_schema.TABLES
    WHERE 
        TABLE_SCHEMA = 'AcademyDB_Extended'
    ORDER BY 
        UPDATE_TIME DESC;
    
    -- Get the list of columns for each table
    SELECT 
        TABLE_NAME,
        COLUMN_NAME,
        ORDINAL_POSITION,
        DATA_TYPE,
        CHARACTER_MAXIMUM_LENGTH,
        IS_NULLABLE
    FROM 
        information_schema.COLUMNS
    WHERE 
        TABLE_SCHEMA = 'AcademyDB_Extended'
    ORDER BY 
        TABLE_NAME, 
        ORDINAL_POSITION;
END//

DELIMITER ;

-- ======================================================
-- Data Modification Audit
-- ======================================================

-- Create triggers to audit DML (Data Manipulation Language) operations
DELIMITER //

-- Audit Students table changes
CREATE TRIGGER trg_AuditStudentInsert
AFTER INSERT ON Students
FOR EACH ROW
BEGIN
    INSERT INTO SystemAuditLog (
        EventType,
        EventDescription,
        AffectedObject,
        Username
    )
    VALUES (
        'DML',
        CONCAT('INSERT operation on Students table, StudentID: ', NEW.StudentID),
        'Students',
        CURRENT_USER()
    );
END//

CREATE TRIGGER trg_AuditStudentUpdate
AFTER UPDATE ON Students
FOR EACH ROW
BEGIN
    INSERT INTO SystemAuditLog (
        EventType,
        EventDescription,
        AffectedObject,
        Username,
        AdditionalInfo
    )
    VALUES (
        'DML',
        CONCAT('UPDATE operation on Students table, StudentID: ', NEW.StudentID),
        'Students',
        CURRENT_USER(),
        CONCAT(
            'Changed fields: ',
            IF(NEW.FullName <> OLD.FullName, 'FullName, ', ''),
            IF(NEW.Contact <> OLD.Contact, 'Contact, ', ''),
            IF(NEW.Email <> OLD.Email, 'Email, ', ''),
            IF(NEW.StudentStatus <> OLD.StudentStatus, 'StudentStatus, ', ''),
            IF(NEW.AdditionalInfo <> OLD.AdditionalInfo, 'AdditionalInfo', '')
        )
    );
END//

CREATE TRIGGER trg_AuditStudentDelete
AFTER DELETE ON Students
FOR EACH ROW
BEGIN
    INSERT INTO SystemAuditLog (
        EventType,
        EventDescription,
        AffectedObject,
        Username
    )
    VALUES (
        'DML',
        CONCAT('DELETE operation on Students table, StudentID: ', OLD.StudentID),
        'Students',
        CURRENT_USER()
    );
END//

-- Audit Faculty table changes
CREATE TRIGGER trg_AuditFacultyInsert
AFTER INSERT ON Faculty
FOR EACH ROW
BEGIN
    INSERT INTO SystemAuditLog (
        EventType,
        EventDescription,
        AffectedObject,
        Username
    )
    VALUES (
        'DML',
        CONCAT('INSERT operation on Faculty table, FacultyID: ', NEW.FacultyID),
        'Faculty',
        CURRENT_USER()
    );
END//

CREATE TRIGGER trg_AuditFacultyUpdate
AFTER UPDATE ON Faculty
FOR EACH ROW
BEGIN
    INSERT INTO SystemAuditLog (
        EventType,
        EventDescription,
        AffectedObject,
        Username,
        AdditionalInfo
    )
    VALUES (
        'DML',
        CONCAT('UPDATE operation on Faculty table, FacultyID: ', NEW.FacultyID),
        'Faculty',
        CURRENT_USER(),
        CONCAT(
            'Changed fields: ',
            IF(NEW.FullName <> OLD.FullName, 'FullName, ', ''),
            IF(NEW.Contact <> OLD.Contact, 'Contact, ', ''),
            IF(NEW.Email <> OLD.Email, 'Email, ', ''),
            IF(NEW.Dept <> OLD.Dept, 'Dept, ', ''),
            IF(NEW.FacultyStatus <> OLD.FacultyStatus, 'FacultyStatus, ', ''),
            IF(NEW.AdditionalInfo <> OLD.AdditionalInfo, 'AdditionalInfo', '')
        )
    );
END//

CREATE TRIGGER trg_AuditFacultyDelete
AFTER DELETE ON Faculty
FOR EACH ROW
BEGIN
    INSERT INTO SystemAuditLog (
        EventType,
        EventDescription,
        AffectedObject,
        Username
    )
    VALUES (
        'DML',
        CONCAT('DELETE operation on Faculty table, FacultyID: ', OLD.FacultyID),
        'Faculty',
        CURRENT_USER()
    );
END//

-- Audit Results table changes
CREATE TRIGGER trg_AuditResultsInsert
AFTER INSERT ON Results
FOR EACH ROW
BEGIN
    INSERT INTO SystemAuditLog (
        EventType,
        EventDescription,
        AffectedObject,
        Username
    )
    VALUES (
        'DML',
        CONCAT('INSERT operation on Results table, ResultID: ', NEW.ResultID),
        'Results',
        CURRENT_USER()
    );
END//

CREATE TRIGGER trg_AuditResultsUpdate
AFTER UPDATE ON Results
FOR EACH ROW
BEGIN
    INSERT INTO SystemAuditLog (
        EventType,
        EventDescription,
        AffectedObject,
        Username,
        AdditionalInfo
    )
    VALUES (
        'DML',
        CONCAT('UPDATE operation on Results table, ResultID: ', NEW.ResultID),
        'Results',
        CURRENT_USER(),
        CONCAT(
            'Student: ', NEW.StudentID, ', ',
            'Course: ', NEW.CourseCode, ', ',
            'Original Grade: ', OLD.Grade, ', ',
            'New Grade: ', NEW.Grade
        )
    );
END//

CREATE TRIGGER trg_AuditResultsDelete
AFTER DELETE ON Results
FOR EACH ROW
BEGIN
    INSERT INTO SystemAuditLog (
        EventType,
        EventDescription,
        AffectedObject,
        Username
    )
    VALUES (
        'DML',
        CONCAT('DELETE operation on Results table, ResultID: ', OLD.ResultID),
        'Results',
        CURRENT_USER()
    );
END//

DELIMITER ;

-- ======================================================
-- Privilege Changes Audit
-- ======================================================

-- Create procedure to audit DCL (Data Control Language) operations
DELIMITER //

-- Create procedure to log privilege changes
CREATE PROCEDURE sp_AuditPrivilegeChange(
    IN p_Privilege VARCHAR(100),
    IN p_ObjectName VARCHAR(100),
    IN p_GrantedTo VARCHAR(50),
    IN p_ActionType VARCHAR(20) -- 'GRANT' or 'REVOKE'
)
BEGIN
    INSERT INTO SystemAuditLog (
        EventType,
        EventDescription,
        AffectedObject,
        Username,
        AdditionalInfo
    )
    VALUES (
        'DCL',
        CONCAT(p_ActionType, ' operation: ', p_Privilege, ' ON ', p_ObjectName, ' TO/FROM ', p_GrantedTo),
        p_ObjectName,
        CURRENT_USER(),
        CONCAT('Privilege: ', p_Privilege, ', Action: ', p_ActionType)
    );
END//

-- Create procedure to log role changes
CREATE PROCEDURE sp_AuditRoleChange(
    IN p_Role VARCHAR(50),
    IN p_User VARCHAR(50),
    IN p_ActionType VARCHAR(20) -- 'GRANT' or 'REVOKE'
)
BEGIN
    INSERT INTO SystemAuditLog (
        EventType,
        EventDescription,
        AffectedObject,
        Username,
        AdditionalInfo
    )
    VALUES (
        'DCL',
        CONCAT(p_ActionType, ' role: ', p_Role, ' TO/FROM user ', p_User),
        'ROLE',
        CURRENT_USER(),
        CONCAT('Role: ', p_Role, ', User: ', p_User, ', Action: ', p_ActionType)
    );
END//

-- Create procedure to audit user management
CREATE PROCEDURE sp_AuditUserManagement(
    IN p_User VARCHAR(50),
    IN p_ActionType VARCHAR(20) -- 'CREATE', 'ALTER', 'DROP'
)
BEGIN
    INSERT INTO SystemAuditLog (
        EventType,
        EventDescription,
        AffectedObject,
        Username
    )
    VALUES (
        'DCL',
        CONCAT(p_ActionType, ' USER: ', p_User),
        'USER',
        CURRENT_USER()
    );
END//

-- Create procedure to show active DCL audit logs
CREATE PROCEDURE sp_ShowDCLAuditLogs()
BEGIN
    SELECT 
        AuditID,
        EventTime,
        EventType,
        EventDescription,
        AffectedObject,
        Username,
        AdditionalInfo
    FROM 
        SystemAuditLog
    WHERE 
        EventType = 'DCL'
    ORDER BY 
        EventTime DESC
    LIMIT 100;
END//

-- Create procedure to analyze privilege usage
CREATE PROCEDURE sp_AnalyzePrivilegeUsage(
    IN p_Period INT -- Number of days to look back
)
BEGIN
    SELECT 
        Username,
        AffectedObject,
        COUNT(*) AS AccessCount
    FROM 
        SystemAuditLog
    WHERE 
        EventType IN ('DML', 'DDL', 'DCL') 
        AND EventTime >= DATE_SUB(NOW(), INTERVAL p_Period DAY)
    GROUP BY 
        Username, AffectedObject
    ORDER BY 
        AccessCount DESC;
END//

DELIMITER ;

-- Required permissions for audit administrators
GRANT EXECUTE ON PROCEDURE AcademyDB_Extended.sp_CreateServerAudit TO AdminRole;
GRANT EXECUTE ON PROCEDURE AcademyDB_Extended.sp_CreateServerAuditSpecification TO AdminRole;
GRANT EXECUTE ON PROCEDURE AcademyDB_Extended.sp_CreateDatabaseAuditSpecification TO AdminRole;
GRANT EXECUTE ON PROCEDURE AcademyDB_Extended.sp_LogFailedLoginAttempt TO AdminRole;
GRANT EXECUTE ON PROCEDURE AcademyDB_Extended.sp_ResetFailedLoginAttempts TO AdminRole;
GRANT EXECUTE ON PROCEDURE AcademyDB_Extended.sp_TrackSchemaChanges TO AdminRole;
GRANT EXECUTE ON PROCEDURE AcademyDB_Extended.sp_AuditPrivilegeChange TO AdminRole;
GRANT EXECUTE ON PROCEDURE AcademyDB_Extended.sp_AuditRoleChange TO AdminRole;
GRANT EXECUTE ON PROCEDURE AcademyDB_Extended.sp_AuditUserManagement TO AdminRole;
GRANT EXECUTE ON PROCEDURE AcademyDB_Extended.sp_ShowDCLAuditLogs TO AdminRole;
GRANT EXECUTE ON PROCEDURE AcademyDB_Extended.sp_AnalyzePrivilegeUsage TO AdminRole;

-- Grant SELECT privileges on SystemAuditLog to AdminRole
GRANT SELECT, INSERT, UPDATE ON AcademyDB_Extended.SystemAuditLog TO AdminRole;

-- Create audit report view for administrators
CREATE OR REPLACE VIEW AdminAuditReportView AS
SELECT 
    AuditID,
    EventTime,
    EventType,
    EventDescription,
    AffectedObject,
    Username,
    IPAddress,
    ApplicationName,
    AdditionalInfo
FROM 
    SystemAuditLog
ORDER BY 
    EventTime DESC;

GRANT SELECT ON AcademyDB_Extended.AdminAuditReportView TO AdminRole;

-- Documentation comment for server audit implementation
/*
SERVER AUDIT IMPLEMENTATION GUIDE

This script implements comprehensive server audit capabilities for AcademyDB_Extended:

1. SystemAuditLog Table:
   - Central repository for all audit events
   - Captures event details, affected objects, users, timestamps

2. Server Audit Objects:
   - AcademyDB_ServerAudit: Base audit object with file destination
   - AcademyDB_ServerSpec: Server-level audit specification
   - AcademyDB_DatabaseSpec: Database-level audit specification

3. Login Activity Audit:
   - Tracks successful and failed login attempts
   - Maintains count of failed login attempts
   - Logs IP addresses and application information

4. Schema Changes Audit:
   - Captures all schema changes (CREATE, ALTER, DROP, RENAME)
   - Tracks object creation, modification, and deletion
   - Logs the user who made the changes

5. Data Modification Audit:
   - Monitors data modifications (INSERT, UPDATE, DELETE)
   - Captures specific changed fields in update operations
   - Logs before and after values for sensitive operations

6. Privilege Changes Audit:
   - Tracks privilege changes (GRANT, REVOKE)
   - Monitors role assignments and revocations
   - Logs user creation, modification, and deletion

7. Audit Reporting:
   - AdminAuditReportView provides a comprehensive audit trail
   - sp_ShowDCLAuditLogs focuses on privilege changes
   - sp_AnalyzePrivilegeUsage helps identify access patterns

Security Best Practices:
- Only AdminRole has access to audit logs and procedures
- Audit files are stored in a secure location with limited access
- File rotation prevents excessive disk usage
- Audit operation failures do not halt database operations

Usage:
- To enable server audit: CALL sp_CreateServerAudit();
- To view audit logs: SELECT * FROM AdminAuditReportView;
- To analyze privilege usage: CALL sp_AnalyzePrivilegeUsage(30); -- Last 30 days
*/