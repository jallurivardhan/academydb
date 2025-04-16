<?php
// Check if user is logged in and has StudentRole
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'StudentRole') {
    header('Location: ../login.php');
    exit();
}

// Get current page for active link
$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="../student/dashboard.php">Student Portal</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#studentNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="studentNavbar">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>" 
                       href="../student/dashboard.php">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'course_registration.php' ? 'active' : ''; ?>" 
                       href="../student/course_registration.php">
                        <i class="fas fa-book-reader"></i> Course Registration
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'my_courses.php' ? 'active' : ''; ?>" 
                       href="../student/my_courses.php">
                        <i class="fas fa-graduation-cap"></i> My Courses
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'assignments.php' ? 'active' : ''; ?>" 
                       href="../student/assignments.php">
                        <i class="fas fa-tasks"></i> Assignments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'course_materials.php' ? 'active' : ''; ?>" 
                       href="../student/course_materials.php">
                        <i class="fas fa-file-alt"></i> Course Materials
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'grades.php' ? 'active' : ''; ?>" 
                       href="../student/grades.php">
                        <i class="fas fa-chart-line"></i> Grades
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'attendance.php' ? 'active' : ''; ?>" 
                       href="../student/attendance.php">
                        <i class="fas fa-calendar-check"></i> Attendance
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['username'] ?? 'Student'); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item <?php echo $current_page === 'profile.php' ? 'active' : ''; ?>" 
                               href="../student/profile.php">
                                <i class="fas fa-id-card"></i> My Profile
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?php echo $current_page === 'security_settings.php' ? 'active' : ''; ?>" 
                               href="../student/security_settings.php">
                                <i class="fas fa-shield-alt"></i> Security Settings
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="../logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav> 