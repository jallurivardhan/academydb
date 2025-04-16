<?php
require_once '../config/database.php';
require_once '../config/security.php';

// Start session and check admin role
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'AdminRole') {
    header('Location: ../login.php');
    exit();
}

include '../includes/admin_header.php';
?>

<div class="container mt-4">
    <div class="card mb-4">
        <div class="card-body">
            <h2>Welcome, admin!</h2>
            <p class="text-muted">Role: AdminRole</p>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h3>Students</h3>
                    <h1>4</h1>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h3>Faculty</h3>
                    <h1>3</h1>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h3>Courses</h3>
                    <h1>5</h1>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h3>Results</h3>
                    <h1>14</h1>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-3 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h3>Manage Users</h3>
                    <p>Add, edit, or remove users from the system.</p>
                    <a href="manage_users.php" class="btn btn-primary">Manage Users</a>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h3>System Reports</h3>
                    <p>View system-wide reports and statistics.</p>
                    <a href="reports.php" class="btn btn-primary">View Reports</a>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h3>Database Management</h3>
                    <p>Manage database tables and settings.</p>
                    <a href="database.php" class="btn btn-primary">Manage Database</a>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h3>Security Settings</h3>
                    <p>Configure system security and view security logs.</p>
                    <a href="security.php" class="btn btn-primary">Manage Security</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?> 