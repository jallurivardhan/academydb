<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and has FacultyRole
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'FacultyRole') {
    header('Location: ../login.php');
    exit();
}

$facultyId = $_SESSION['user_id'];
$error = '';
$success = '';

try {
    // Get faculty's own data with masking
    $stmt = $pdo->prepare("
        SELECT 
            FacultyID,
            FullName,
            fn_MaskEmail(Email) as Email,
            fn_MaskContact(Contact) as Contact,
            Dept,
            FacultyStatus,
            LastModifiedOn
        FROM Faculty
        WHERE FacultyID = ?
    ");
    $stmt->execute([$facultyId]);
    $facultyData = $stmt->fetch();

} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Faculty Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">Faculty Portal</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="courses.php">My Courses</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="view_sensitive_data.php">My Profile</a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>My Profile</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($facultyData): ?>
            <div class="card">
                <div class="card-header">
                    <h4>Faculty Information</h4>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-3"><strong>Name:</strong></div>
                        <div class="col-md-9"><?php echo htmlspecialchars($facultyData['FullName']); ?></div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3"><strong>Email:</strong></div>
                        <div class="col-md-9"><?php echo htmlspecialchars($facultyData['Email']); ?></div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3"><strong>Contact:</strong></div>
                        <div class="col-md-9"><?php echo htmlspecialchars($facultyData['Contact']); ?></div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3"><strong>Department:</strong></div>
                        <div class="col-md-9"><?php echo htmlspecialchars($facultyData['Dept']); ?></div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3"><strong>Status:</strong></div>
                        <div class="col-md-9">
                            <span class="badge bg-<?php echo $facultyData['FacultyStatus'] === 'Active' ? 'success' : 'warning'; ?>">
                                <?php echo htmlspecialchars($facultyData['FacultyStatus']); ?>
                            </span>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3"><strong>Last Updated:</strong></div>
                        <div class="col-md-9"><?php echo htmlspecialchars($facultyData['LastModifiedOn']); ?></div>
                    </div>
                </div>
            </div>

            <div class="alert alert-info mt-4">
                <h5>Note:</h5>
                <p>For security reasons, some sensitive information is masked. If you need to update your information or view complete details, please contact the administrator.</p>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 