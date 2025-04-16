<?php
session_start();
require_once '../config/database.php';
require_once '../config/security.php';

// Check if user is logged in and has AdminRole
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'AdminRole') {
    logUserActivity($_SESSION['user_id'] ?? 'unknown', 'unauthorized_access', 'Attempted to access admin sensitive data page', 'failed');
    header('Location: ../login.php');
    exit();
}

// Rate limiting check
if (!checkRateLimit($_SERVER['REMOTE_ADDR'], 'admin_sensitive_data', 10, 300)) {
    logUserActivity($_SESSION['user_id'], 'rate_limit_exceeded', 'Too many attempts to access sensitive data', 'failed');
    header('Location: ../error.php?code=429');
    exit();
}

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        logUserActivity($_SESSION['user_id'], 'csrf_validation_failed', 'CSRF token validation failed on sensitive data update', 'failed');
        $error = "Security validation failed. Please try again.";
    } else {
        if (isset($_POST['action'])) {
            try {
                $action = sanitizeInput($_POST['action']);
                
                switch ($action) {
                    case 'update_student':
                        $studentId = sanitizeInput($_POST['student_id']);
                        $ssn = sanitizeInput($_POST['ssn']);
                        $financialInfo = sanitizeInput($_POST['financial_info']);
                        
                        if (!preg_match('/^\d{3}-\d{2}-\d{4}$/', $ssn)) {
                            throw new Exception("Invalid SSN format");
                        }
                        
                        $stmt = $pdo->prepare("CALL sp_UpdateStudentSensitiveData(?, ?, ?)");
                        $stmt->execute([$studentId, $ssn, $financialInfo]);
                        $success = "Student sensitive data updated successfully.";
                        logUserActivity($_SESSION['user_id'], 'update_student_data', "Updated sensitive data for student ID: $studentId", 'success');
                        break;

                    case 'update_faculty':
                        $facultyId = sanitizeInput($_POST['faculty_id']);
                        $ssn = sanitizeInput($_POST['ssn']);
                        $bankInfo = sanitizeInput($_POST['bank_info']);
                        
                        if (!preg_match('/^\d{3}-\d{2}-\d{4}$/', $ssn)) {
                            throw new Exception("Invalid SSN format");
                        }
                        
                        $stmt = $pdo->prepare("CALL sp_UpdateFacultySensitiveData(?, ?, ?)");
                        $stmt->execute([$facultyId, $ssn, $bankInfo]);
                        $success = "Faculty sensitive data updated successfully.";
                        logUserActivity($_SESSION['user_id'], 'update_faculty_data', "Updated sensitive data for faculty ID: $facultyId", 'success');
                        break;

                    case 'view_student':
                        $studentId = sanitizeInput($_POST['student_id']);
                        $stmt = $pdo->prepare("CALL sp_GetStudentSensitiveData(?)");
                        $stmt->execute([$studentId]);
                        $sensitiveData = $stmt->fetch();
                        logUserActivity($_SESSION['user_id'], 'view_student_data', "Viewed sensitive data for student ID: $studentId", 'success');
                        break;
                }
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
                logUserActivity($_SESSION['user_id'], 'error', $e->getMessage(), 'failed');
            }
        }
    }
}

// Fetch students and faculty for dropdowns
try {
    $stmt = $pdo->query("SELECT StudentID, FullName FROM Students ORDER BY FullName");
    $students = $stmt->fetchAll();

    $stmt = $pdo->query("SELECT FacultyID, FullName FROM Faculty ORDER BY FullName");
    $faculty = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    logUserActivity($_SESSION['user_id'], 'database_error', $e->getMessage(), 'failed');
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Sensitive Data - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/admin_navbar.php'; ?>

    <div class="container mt-4">
        <h2>Manage Sensitive Data</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="row">
            <!-- Student Sensitive Data Section -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4>Student Sensitive Data</h4>
                    </div>
                    <div class="card-body">
                        <form method="post" class="mb-4" onsubmit="return validateForm(this);">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="update_student">
                            
                            <div class="mb-3">
                                <label for="student_id" class="form-label">Select Student</label>
                                <select class="form-select" id="student_id" name="student_id" required>
                                    <option value="">Choose...</option>
                                    <?php foreach ($students as $student): ?>
                                        <option value="<?php echo htmlspecialchars($student['StudentID']); ?>">
                                            <?php echo htmlspecialchars($student['FullName']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="student_ssn" class="form-label">SSN</label>
                                <input type="text" class="form-control" id="student_ssn" name="ssn" 
                                       pattern="\d{3}-\d{2}-\d{4}" placeholder="XXX-XX-XXXX" required
                                       maxlength="11">
                                <div class="form-text">Format: XXX-XX-XXXX</div>
                            </div>

                            <div class="mb-3">
                                <label for="student_financial" class="form-label">Financial Information</label>
                                <textarea class="form-control" id="student_financial" name="financial_info" 
                                          rows="3" required maxlength="1000"></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary">Update Student Data</button>
                        </form>

                        <hr>

                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="view_student">
                            
                            <div class="mb-3">
                                <label for="view_student_id" class="form-label">View Student Data</label>
                                <select class="form-select" id="view_student_id" name="student_id" required>
                                    <option value="">Choose...</option>
                                    <?php foreach ($students as $student): ?>
                                        <option value="<?php echo htmlspecialchars($student['StudentID']); ?>">
                                            <?php echo htmlspecialchars($student['FullName']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-secondary">View Data</button>
                        </form>

                        <?php if (isset($sensitiveData)): ?>
                            <div class="mt-3">
                                <h5>Sensitive Data:</h5>
                                <p><strong>SSN:</strong> <?php echo htmlspecialchars($sensitiveData['SSN']); ?></p>
                                <p><strong>Financial Info:</strong> <?php echo htmlspecialchars($sensitiveData['FinancialInfo']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Faculty Sensitive Data Section -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4>Faculty Sensitive Data</h4>
                    </div>
                    <div class="card-body">
                        <form method="post" onsubmit="return validateForm(this);">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="update_faculty">
                            
                            <div class="mb-3">
                                <label for="faculty_id" class="form-label">Select Faculty</label>
                                <select class="form-select" id="faculty_id" name="faculty_id" required>
                                    <option value="">Choose...</option>
                                    <?php foreach ($faculty as $f): ?>
                                        <option value="<?php echo htmlspecialchars($f['FacultyID']); ?>">
                                            <?php echo htmlspecialchars($f['FullName']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="faculty_ssn" class="form-label">SSN</label>
                                <input type="text" class="form-control" id="faculty_ssn" name="ssn" 
                                       pattern="\d{3}-\d{2}-\d{4}" placeholder="XXX-XX-XXXX" required
                                       maxlength="11">
                                <div class="form-text">Format: XXX-XX-XXXX</div>
                            </div>

                            <div class="mb-3">
                                <label for="faculty_bank" class="form-label">Bank Information</label>
                                <textarea class="form-control" id="faculty_bank" name="bank_info" 
                                          rows="3" required maxlength="1000"></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary">Update Faculty Data</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Client-side validation
        function validateForm(form) {
            const ssnInput = form.querySelector('input[name="ssn"]');
            const ssnPattern = /^\d{3}-\d{2}-\d{4}$/;
            
            if (!ssnPattern.test(ssnInput.value)) {
                alert('Please enter a valid SSN in the format XXX-XX-XXXX');
                return false;
            }
            
            return true;
        }

        // Auto-format SSN input
        document.querySelectorAll('input[pattern]').forEach(input => {
            input.addEventListener('input', function() {
                let value = this.value.replace(/\D/g, '');
                if (value.length >= 3) {
                    value = value.substr(0, 3) + '-' + value.substr(3);
                }
                if (value.length >= 6) {
                    value = value.substr(0, 6) + '-' + value.substr(6);
                }
                this.value = value.substr(0, 11);
            });
        });

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html> 