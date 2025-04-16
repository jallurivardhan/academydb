<?php
// Include the database connection file
require_once __DIR__ . '/db_connect.php';

// Make the PDO connection available globally
global $pdo;

// Function to get database connection
function getDbConnection() {
    global $pdo;
    return $pdo;
}

// Function to execute a query and return all results
function executeQuery($query, $params = []) {
    global $pdo;
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to execute a query and return a single result
function executeQuerySingle($query, $params = []) {
    global $pdo;
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to execute a non-query (INSERT, UPDATE, DELETE)
function executeNonQuery($query, $params = []) {
    global $pdo;
    $stmt = $pdo->prepare($query);
    return $stmt->execute($params);
} 