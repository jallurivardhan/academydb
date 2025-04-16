<?php
require_once __DIR__ . '/config/database.php';

try {
    // Read and execute the SQL file
    $sql = file_get_contents(__DIR__ . '/sql/security_tables.sql');
    $pdo->exec($sql);
    
    echo "Security tables created successfully!\n";
    echo "You can now access the security management page at: http://localhost:8000/admin/security_management.php\n";
} catch (PDOException $e) {
    echo "Error setting up security tables: " . $e->getMessage() . "\n";
} 