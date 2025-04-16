<?php
// PHP script to connect to MySQL and execute SQL scripts
$host = 'localhost';
$username = 'root';
$password = '#Vardhan123';
$maxAttempts = 1;
$attempt = 0;
$connected = false;

echo "Attempting to connect to MySQL with different passwords...\n";

try {
    $pdo = new PDO("mysql:host=$host;dbname=academydb_extended", $username, $password);
    // Set the PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $connected = true;
    echo "Successfully connected to MySQL with password: " . ($password ? '****' : 'empty') . "\n";
    
    // Get MySQL version
    $result = $pdo->query("SELECT VERSION()");
    $row = $result->fetch();
    echo "MySQL Version: " . $row[0] . "\n";
    
    // Execute Database Creation script
    echo "Executing Database Creation script...\n";
    $sql = file_get_contents("Database Creation (1).sql");
    $pdo->exec($sql);
    echo "Database Creation script executed successfully!\n";
    
    // Execute RBAC script
    echo "Executing RBAC script...\n";
    $sql = file_get_contents("RBAC (1).sql");
    $pdo->exec($sql);
    echo "RBAC script executed successfully!\n";
    
    echo "All scripts executed successfully!\n";
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}

if (!$connected) {
    echo "Failed to connect to MySQL after trying all passwords.\n";
    echo "Please check your MySQL installation and credentials.\n";
}
?> 