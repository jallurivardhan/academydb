<?php
// Check if user is logged in and has AdminRole
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'AdminRole') {
    header('Location: ../login.php');
    exit();
}
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="../admin/dashboard.php">Admin Panel</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="adminNavbar">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="../admin/dashboard.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../admin/manage_users.php">Manage Users</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../admin/manage_courses.php">Manage Courses</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../admin/manage_sensitive_data.php">Sensitive Data</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../admin/security_management.php">Security</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../admin/database.php">Database</a>
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