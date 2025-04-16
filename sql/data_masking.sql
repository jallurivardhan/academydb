-- Data Masking Techniques for AcademyDB_Extended
-- This file implements both static and dynamic data masking

USE AcademyDB_Extended;

-- ======================================================
-- Static Data Masking Implementation
-- ======================================================

-- Create function to mask email addresses (static masking)
DELIMITER //
CREATE FUNCTION fn_MaskEmail(email VARCHAR(100)) 
RETURNS VARCHAR(100) DETERMINISTIC
BEGIN
    DECLARE username VARCHAR(50);
    DECLARE domain VARCHAR(50);
    DECLARE masked_username VARCHAR(50);
    
    -- Extract username and domain
    SET username = SUBSTRING_INDEX(email, '@', 1);
    SET domain = SUBSTRING_INDEX(email, '@', -1);
    
    -- Create masked username (first 2 chars + asterisks)
    IF LENGTH(username) > 2 THEN
        SET masked_username = CONCAT(LEFT(username, 2), REPEAT('*', LENGTH(username) - 2));
    ELSE
        SET masked_username = username; -- If username is too short, don't mask
    END IF;
    
    RETURN CONCAT(masked_username, '@', domain);
END//
DELIMITER ;

-- Create function to mask contact numbers (static masking)
DELIMITER //
CREATE FUNCTION fn_MaskContact(contact VARCHAR(20)) 
RETURNS VARCHAR(20) DETERMINISTIC
BEGIN
    DECLARE masked_contact VARCHAR(20);
    
    IF contact IS NULL THEN
        RETURN NULL;
    END IF;
    
    -- Show only last 4 digits, mask the rest
    SET masked_contact = CONCAT(REPEAT('*', LENGTH(contact) - 4), RIGHT(contact, 4));
    
    RETURN masked_contact;
END//
DELIMITER ;

-- Create stored procedure to create a masked copy of Students table for testing/development
DELIMITER //
CREATE PROCEDURE sp_CreateMaskedStudentsTable()
BEGIN
    -- Drop the table if it exists
    DROP TABLE IF EXISTS Students_Masked;
    
    -- Create masked copy of Students table
    CREATE TABLE Students_Masked AS
    SELECT 
        StudentID,
        -- Password is completely masked
        UNHEX(SHA2('MASKED_PASSWORD', 256)) AS UserPassword,
        FullName,
        fn_MaskContact(Contact) AS Contact,
        fn_MaskEmail(Email) AS Email,
        -- Additional Info can be replaced with generic text
        'Additional student information masked for privacy' AS AdditionalInfo,
        StudentStatus,
        CreatedByUser,
        CreatedOn,
        LastModifiedBy,
        LastModifiedOn,
        SysStart,
        SysEnd
    FROM Students;
    
    -- Add indexes to maintain performance
    ALTER TABLE Students_Masked ADD PRIMARY KEY (StudentID);
    ALTER TABLE Students_Masked ADD INDEX idx_Students_FullName (FullName);
    ALTER TABLE Students_Masked ADD INDEX idx_Students_Contact (Contact);
    
    SELECT 'Students_Masked table created successfully.' AS Message;
END//
DELIMITER ;

-- Create stored procedure to create a masked copy of Faculty table for testing/development
DELIMITER //
CREATE PROCEDURE sp_CreateMaskedFacultyTable()
BEGIN
    -- Drop the table if it exists
    DROP TABLE IF EXISTS Faculty_Masked;
    
    -- Create masked copy of Faculty table
    CREATE TABLE Faculty_Masked AS
    SELECT 
        FacultyID,
        -- Password is completely masked
        UNHEX(SHA2('MASKED_PASSWORD', 256)) AS UserPassword,
        FullName,
        fn_MaskContact(Contact) AS Contact,
        fn_MaskEmail(Email) AS Email,
        Dept,
        -- Additional Info can be replaced with generic text
        'Additional faculty information masked for privacy' AS AdditionalInfo,
        FacultyStatus,
        CreatedByUser,
        CreatedOn,
        LastModifiedBy,
        LastModifiedOn,
        SysStart,
        SysEnd
    FROM Faculty;
    
    -- Add indexes to maintain performance
    ALTER TABLE Faculty_Masked ADD PRIMARY KEY (FacultyID);
    ALTER TABLE Faculty_Masked ADD INDEX idx_Faculty_FullName (FullName);
    ALTER TABLE Faculty_Masked ADD INDEX idx_Faculty_Contact (Contact);
    
    SELECT 'Faculty_Masked table created successfully.' AS Message;
END//
DELIMITER ;

-- ======================================================
-- Dynamic Data Masking Implementation
-- ======================================================

-- Create Views using dynamic masking for different roles

-- AdminStudentsMaskedView - Admins see full data except for passwords
CREATE OR REPLACE VIEW AdminStudentsMaskedView AS
SELECT 
    StudentID,
    'PASSWORD_HIDDEN' AS UserPassword, -- Even admins don't see the actual password
    FullName,
    Contact,
    Email,
    AdditionalInfo,
    StudentStatus,
    CreatedByUser,
    CreatedOn,
    LastModifiedBy,
    LastModifiedOn,
    SysStart,
    SysEnd
FROM Students;

-- FacultyStudentsMaskedView - Faculty see limited student data
CREATE OR REPLACE VIEW FacultyStudentsMaskedView AS
SELECT 
    StudentID,
    FullName,
    CASE 
        WHEN CURRENT_ROLE() = 'FacultyRole' THEN fn_MaskContact(Contact)
        ELSE Contact 
    END AS Contact,
    CASE 
        WHEN CURRENT_ROLE() = 'FacultyRole' THEN fn_MaskEmail(Email)
        ELSE Email 
    END AS Email,
    'Additional information hidden' AS AdditionalInfo,
    StudentStatus,
    CreatedOn
FROM Students;

-- StudentSelfMaskedView - Students see their own data except password
CREATE OR REPLACE VIEW StudentSelfMaskedView AS
SELECT 
    StudentID,
    'PASSWORD_HIDDEN' AS UserPassword,
    FullName,
    Contact,
    Email,
    AdditionalInfo,
    StudentStatus,
    CreatedOn,
    LastModifiedOn
FROM Students
WHERE StudentID = SUBSTRING_INDEX(USER(), '@', 1);

-- Grant permissions for masked views
GRANT SELECT ON AdminStudentsMaskedView TO AdminRole;
GRANT SELECT ON FacultyStudentsMaskedView TO FacultyRole;
GRANT SELECT ON StudentSelfMaskedView TO StudentRole;

-- Create procedure to demonstrate dynamic masking capabilities
DELIMITER //
CREATE PROCEDURE sp_DemonstrateDynamicMasking()
BEGIN
    DECLARE current_role VARCHAR(50);
    SET current_role = CURRENT_ROLE();
    
    SELECT CONCAT('Current role: ', IFNULL(current_role, 'No specific role')) AS CurrentRole;
    
    -- Show what data is visible based on current role
    IF current_role = 'AdminRole' THEN
        SELECT 'Admin can see most data except actual passwords' AS AccessLevel;
        SELECT * FROM AdminStudentsMaskedView LIMIT 5;
    ELSEIF current_role = 'FacultyRole' THEN
        SELECT 'Faculty can see limited student data with masked contact information' AS AccessLevel;
        SELECT * FROM FacultyStudentsMaskedView LIMIT 5;
    ELSEIF current_role = 'StudentRole' THEN
        SELECT 'Student can see only their own data' AS AccessLevel;
        SELECT * FROM StudentSelfMaskedView;
    ELSE
        SELECT 'Unknown role or insufficient privileges' AS AccessLevel;
    END IF;
END//
DELIMITER ;
