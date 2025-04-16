<?php
try {
    $host = 'localhost';
    $dbname = 'academydb_extended';
    $username = 'root';
    $password = '#Vardhan123';
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO($dsn, $username, $password, $options);
    $conn = $pdo; // For backward compatibility

} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Function to get user role
function getUserRole($pdo, $userId) {
    try {
        // Check if user is an admin
        $stmt = $pdo->prepare("SELECT 1 FROM Admin WHERE AdminID = ?");
        $stmt->execute([$userId]);
        if ($stmt->rowCount() > 0) {
            return 'AdminRole';
        }
        
        // Check if user is a faculty
        $stmt = $pdo->prepare("SELECT 1 FROM Faculty WHERE FacultyID = ?");
        $stmt->execute([$userId]);
        if ($stmt->rowCount() > 0) {
            return 'FacultyRole';
        }
        
        // Check if user is a student
        $stmt = $pdo->prepare("SELECT 1 FROM Students WHERE StudentID = ?");
        $stmt->execute([$userId]);
        if ($stmt->rowCount() > 0) {
            return 'StudentRole';
        }
        
        return null;
    } catch(PDOException $e) {
        return null;
    }
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to check if user has required role
function hasRole($requiredRole) {
    if (!isLoggedIn()) {
        return false;
    }
    
    return $_SESSION['user_role'] === $requiredRole;
}

// Function to redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

// Function to redirect if not authorized
function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        header("Location: unauthorized.php");
        exit();
    }
}
?> 