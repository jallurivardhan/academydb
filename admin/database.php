<?php
session_start();
require_once '../config/db_connect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'AdminRole') {
    header("Location: ../login.php");
    exit();
}

// Initialize variables
$error = '';
$success = '';
$tables = [];
$dbInfo = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'backup') {
            try {
                // Create backup directory if it doesn't exist
                $backupDir = __DIR__ . '/../backups';
                if (!file_exists($backupDir)) {
                    mkdir($backupDir, 0777, true);
                }

                // Generate backup filename with timestamp
                $timestamp = date('Y-m-d_H-i-s');
                $backupFile = $backupDir . "/backup_" . $timestamp . ".sql";

                // Build the mysqldump command with proper path
                $mysqldump = '"C:\Program Files\MySQL\MySQL Server 9.0\bin\mysqldump.exe"';
                $command = sprintf(
                    '%s --user=%s --password=%s --host=%s %s > %s',
                    $mysqldump,
                    escapeshellarg('root'),
                    escapeshellarg('#Vardhan123'),
                    escapeshellarg('localhost'),
                    escapeshellarg('academydb_extended'),
                    escapeshellarg($backupFile)
                );

                // Execute the backup command
                $output = [];
                $returnVar = 0;
                exec($command . " 2>&1", $output, $returnVar);

                if ($returnVar === 0) {
                    $success = "Database backup created successfully!";
                } else {
                    $error = "Failed to create database backup. Error: " . implode("\n", $output);
                }
            } catch (Exception $e) {
                $error = "Error creating backup: " . $e->getMessage();
            }
        }
    }
}

// Get database information
try {
    // Get MySQL version
    $stmt = $pdo->query("SELECT VERSION() as version");
    $dbInfo['version'] = $stmt->fetch()['version'];

    // Get database size
    $stmt = $pdo->query("
        SELECT 
            table_schema as 'Database',
            ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as 'Size (MB)'
        FROM information_schema.tables
        WHERE table_schema = 'academydb_extended'
        GROUP BY table_schema
    ");
    $dbInfo['size'] = $stmt->fetch()['Size (MB)'];

    // Get table information
    $stmt = $pdo->query("
        SELECT 
            table_name as 'Table',
            table_rows as 'Rows',
            ROUND((data_length + index_length) / 1024 / 1024, 2) as 'Size (MB)',
            CREATE_TIME as 'Created',
            UPDATE_TIME as 'Last Updated'
        FROM information_schema.tables
        WHERE table_schema = 'academydb_extended'
        ORDER BY table_name
    ");
    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Get backup files
$backupDir = __DIR__ . '/../backups';
$backupFiles = [];
if (file_exists($backupDir)) {
    $files = glob($backupDir . "/*.sql");
    foreach ($files as $file) {
        $backupFiles[] = [
            'name' => basename($file),
            'size' => round(filesize($file) / 1024 / 1024, 2), // Size in MB
            'date' => date("Y-m-d H:i:s", filemtime($file))
        ];
    }
    // Sort by date, newest first
    usort($backupFiles, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Management - AcademyDB Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../css/style.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../index.php">AcademyDB Management System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_users.php">Manage Users</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="database.php">Database</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="security.php">Security</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="../logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Database Management</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Database Information -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Database Information</h5>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item">
                                <strong>MySQL Version:</strong> <?php echo htmlspecialchars($dbInfo['version']); ?>
                            </li>
                            <li class="list-group-item">
                                <strong>Database Size:</strong> <?php echo htmlspecialchars($dbInfo['size']); ?> MB
                            </li>
                            <li class="list-group-item">
                                <strong>Total Tables:</strong> <?php echo count($tables); ?>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Database Actions</h5>
                        <form method="POST" action="database.php" class="mb-3">
                            <input type="hidden" name="action" value="backup">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-download me-2"></i>Create Backup
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table Information -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Table Information</h5>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Table Name</th>
                                <th>Rows</th>
                                <th>Size (MB)</th>
                                <th>Created</th>
                                <th>Last Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tables as $table): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($table['Table']); ?></td>
                                    <td><?php echo htmlspecialchars($table['Rows']); ?></td>
                                    <td><?php echo htmlspecialchars($table['Size (MB)']); ?></td>
                                    <td><?php echo htmlspecialchars($table['Created']); ?></td>
                                    <td><?php echo htmlspecialchars($table['Last Updated'] ?? 'N/A'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Backup History -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Backup History</h5>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Filename</th>
                                <th>Size (MB)</th>
                                <th>Created Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($backupFiles)): ?>
                                <tr>
                                    <td colspan="3" class="text-center">No backup files found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($backupFiles as $backup): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($backup['name']); ?></td>
                                        <td><?php echo htmlspecialchars($backup['size']); ?></td>
                                        <td><?php echo htmlspecialchars($backup['date']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 