<?php
session_start();

require_once __DIR__ . '/api/db.php';

$message = "";
$messageType = ""; // "success" or "error"

// --- Handle Sign Up ---
if (isset($_POST['signup'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $childName = trim($_POST['childName'] ?? '');
    $childGrade = trim($_POST['childGrade'] ?? '');
    $relationship = trim($_POST['relationship'] ?? '');
    $password = $_POST['password'] ?? '';
    $childCodeInput = trim($_POST['childCode'] ?? '');

    // Validate
    if (empty($name) || empty($email) || empty($phone) || empty($childName) || empty($childGrade) || empty($relationship) || empty($password) || empty($childCodeInput)) {
        $message = "All fields are required, including your child's code from the teacher!";
        $messageType = "error";
    } elseif (strlen($password) < 6) {
        $message = "Password must be at least 6 characters!";
        $messageType = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format!";
        $messageType = "error";
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare('SELECT id FROM parents WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $existing = $stmt->fetch();

        if ($existing) {
            $message = "Email already registered!";
            $messageType = "error";
        } else {
            // Validate child code against students table
            $stmt = $pdo->prepare('SELECT child_code FROM students WHERE child_code = :code LIMIT 1');
            $stmt->execute(['code' => $childCodeInput]);
            $student = $stmt->fetch();

            if (!$student) {
                $message = "Invalid child code. Please double-check the code from your child's teacher.";
                $messageType = "error";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                // Generate a signup_code internally (not used by parents anymore)
                $uniqueCode = strtoupper(bin2hex(random_bytes(6)));
                $createdAt = date('Y-m-d H:i:s');
                $linkedChildren = json_encode([$childCodeInput]);

                $insert = $pdo->prepare('INSERT INTO parents (name, email, phone, child_name, child_grade, relationship, password, signup_code, signup_code_used, linked_children, created_at) VALUES (:name, :email, :phone, :child_name, :child_grade, :relationship, :password, :signup_code, 1, :linked_children, :created_at)');
                $insert->execute([
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'child_name' => $childName,
                    'child_grade' => $childGrade,
                    'relationship' => $relationship,
                    'password' => $hashedPassword,
                    'signup_code' => $uniqueCode,
                    'linked_children' => $linkedChildren,
                    'created_at' => $createdAt,
                ]);

                $message = "Account created successfully! You can now log in and view your child's grades using this account.";
                $messageType = "success";
            }
        }
    }
}

// --- Handle Login ---
if (isset($_POST['login'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $message = "Email and password are required!";
        $messageType = "error";
    } else {
        $stmt = $pdo->prepare('SELECT * FROM parents WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $parent = $stmt->fetch();

        if ($parent && password_verify($password, $parent['password'])) {
            $_SESSION['parent'] = $parent;
            header("Location: parent-portal.php");
            exit;
        } else {
            $message = "Invalid email or password.";
            $messageType = "error";
        }
    }
}

// --- Handle Message Send ---
if (isset($_POST['send']) && isset($_SESSION['parent'])) {
    $subject = trim($_POST['subject'] ?? '');
    $msgContent = trim($_POST['message'] ?? '');
    $teacherUsername = trim($_POST['teacher'] ?? '');
    
    if (empty($teacherUsername)) {
        $message = "Please select a teacher.";
        $messageType = "error";
    } elseif (empty($subject) || empty($msgContent)) {
        $message = "Subject and message are required!";
        $messageType = "error";
    } else {
        // Optional: verify the selected teacher exists
        $stmt = $pdo->prepare('SELECT username FROM admins WHERE username = :u AND role = "teacher" LIMIT 1');
        $stmt->execute(['u' => $teacherUsername]);
        $teacher = $stmt->fetch();

        if (!$teacher) {
            $message = "Selected teacher does not exist.";
            $messageType = "error";
        } else {
            $timestamp = date('Y-m-d H:i:s');
            $timestampUnix = time();

            $insert = $pdo->prepare('INSERT INTO messages (sender_email, parent_email, sender_role, sender_name, child_name, teacher_username, subject, content, type, status, timestamp, timestamp_unix)
                VALUES (:sender_email, :parent_email, :sender_role, :sender_name, :child_name, :teacher_username, :subject, :content, :type, :status, :timestamp, :timestamp_unix)');
            $insert->execute([
                'sender_email' => $_SESSION['parent']['email'],
                'parent_email' => $_SESSION['parent']['email'],
                'sender_role' => 'parent',
                'sender_name' => $_SESSION['parent']['name'],
                'child_name' => $_SESSION['parent']['child_name'],
                'teacher_username' => $teacherUsername,
                'subject' => $subject,
                'content' => $msgContent,
                'type' => 'parent-to-teacher',
                'status' => 'unread',
                'timestamp' => $timestamp,
                'timestamp_unix' => $timestampUnix,
            ]);
            
            $message = "Message sent to teacher successfully!";
            $messageType = "success";
        }
    }
}

// --- Handle Logout ---
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: parent-portal.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Portal - Emilio Jacinto NHS</title>
    <link rel="icon" type="image/png" href="logo.png"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        .parent-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        .parent-form {
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(130, 178, 255, 0.3);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .parent-form h2 {
            text-align: center;
            margin-bottom: 1.5rem;
            font-size: 1.8rem;
            color: #82b2ff;
        }
        .form-group {
            margin-bottom: 1.2rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #82b2ff;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid rgba(130, 178, 255, 0.3);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
        }

        /* Fix teacher dropdown list readability across browsers */
        .form-group select {
            color-scheme: dark;
            cursor: pointer;
        }
        .form-group select option {
            background: #0b1220;
            color: #ffffff;
        }
        .form-group select:focus {
            outline: none;
            border-color: rgba(130, 178, 255, 0.7);
            box-shadow: 0 0 0 3px rgba(130, 178, 255, 0.18);
        }
        .form-group input::placeholder,
        .form-group textarea::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }
        .button-group {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1.5rem;
        }
        .btn {
            padding: 0.8rem 2rem;
            border: none;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, #4CAF50 40%, #2E7D32 100%);
            color: white;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #66BB6A, #388E3C);
            transform: scale(1.05);
        }
        .btn-secondary {
            background: linear-gradient(135deg, #607D8B, #37474F);
            color: white;
        }
        .btn-secondary:hover {
            background: linear-gradient(135deg, #82b2ff, #263238);
            transform: scale(1.05);
        }
        .info-text {
            font-size: 0.85rem;
            color: #aaa;
            margin-top: 0.3rem;
        }
        .message-box {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-left: 4px solid;
        }
        .message-box.success {
            border-color: #4CAF50;
            background: rgba(76, 175, 80, 0.1);
        }
        .message-box.error {
            border-color: #f44336;
            background: rgba(244, 67, 54, 0.1);
        }
        .welcome-header {
            text-align: center;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(130, 178, 255, 0.3);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .welcome-header h2 {
            color: #82b2ff;
            margin-bottom: 1rem;
        }
        .messages-section {
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(130, 178, 255, 0.3);
            border-radius: 12px;
            padding: 2rem;
        }
        .messages-section h2 {
            color: #82b2ff;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        .message-item {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(130, 178, 255, 0.2);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .message-sender {
            font-weight: 600;
            color: #82b2ff;
            margin-bottom: 0.5rem;
        }
        .message-subject {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        .message-content {
            color: #ddd;
            margin-bottom: 0.5rem;
        }
        .message-time {
            font-size: 0.85rem;
            color: #999;
        }
        .signup-code-box {
            background: rgba(130, 178, 255, 0.1);
            border: 2px solid #82b2ff;
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 1rem;
            text-align: center;
        }
        .signup-code-display {
            background: rgba(0, 0, 0, 0.3);
            padding: 1.5rem;
            border-radius: 8px;
            font-family: monospace;
            font-size: 1.5rem;
            letter-spacing: 3px;
            color: #4CAF50;
            margin: 1rem 0;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <header>
        <img src="logo.png" alt="School Logo" />
        <h1>Emilio Jacinto NHS - Parent Portal</h1>
        <div style="margin-left:auto;">
            <a href="index.html" class="btn btn-secondary" style="text-decoration: none;">
                <i class="fas fa-home"></i> Back to Main
            </a>
        </div>
    </header>

    <main class="parent-container">
        <?php if ($message): ?>
            <div class="message-box <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (!isset($_SESSION['parent'])): ?>
            <!-- Authentication Section -->
            <div id="authSection">
                <?php if (!isset($_POST['showLogin']) && !isset($_GET['showLogin'])): ?>
                    <!-- Sign Up Form -->
                    <div class="parent-form">
                        <h2><i class="fas fa-user-plus"></i> Create Parent Account</h2>
                        <p style="text-align: center; color: #aaa; margin-bottom: 1.5rem;">
                            Register using the Child Code given by your child's teacher.
                        </p>
                        <form method="post">
                            <div class="form-group">
                                <label for="name"><i class="fas fa-user"></i> Full Name *</label>
                                <input type="text" id="name" name="name" placeholder="Enter your full name" required>
                            </div>
                            <div class="form-group">
                                <label for="email"><i class="fas fa-envelope"></i> Email Address *</label>
                                <input type="email" id="email" name="email" placeholder="your.email@example.com" required>
                            </div>
                            <div class="form-group">
                                <label for="phone"><i class="fas fa-phone"></i> Phone Number *</label>
                                <input type="tel" id="phone" name="phone" placeholder="09XX XXX XXXX" required>
                            </div>
                            <div class="form-group">
                                <label for="childName"><i class="fas fa-child"></i> Child's Name *</label>
                                <input type="text" id="childName" name="childName" placeholder="Enter child's full name" required>
                            </div>
                            <div class="form-group">
                                <label for="childGrade"><i class="fas fa-graduation-cap"></i> Child's Grade/Level *</label>
                                <select id="childGrade" name="childGrade" required>
                                    <option value="">Select Grade</option>
                                    <option value="Grade 11">Grade 11</option>
                                    <option value="Grade 12">Grade 12</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="relationship"><i class="fas fa-users"></i> Relationship to Child *</label>
                                <select id="relationship" name="relationship" required>
                                    <option value="">Select Relationship</option>
                                    <option value="Parent">Parent</option>
                                    <option value="Guardian">Guardian</option>
                                    <option value="Caregiver">Caregiver</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="childCode"><i class="fas fa-key"></i> Child Code from Teacher *</label>
                                <input type="text" id="childCode" name="childCode" placeholder="Paste the code given by the teacher" required>
                                <p class="info-text"><i class="fas fa-info-circle"></i> This is the same code your child's teacher generated when adding your child.</p>
                            </div>
                            <div class="form-group">
                                <label for="password"><i class="fas fa-lock"></i> Create Password *</label>
                                <input type="password" id="password" name="password" placeholder="Minimum 6 characters" required>
                                <p class="info-text"><i class="fas fa-info-circle"></i> Password must be at least 6 characters</p>
                            </div>
                            <div class="button-group">
                                <button type="submit" name="signup" class="btn btn-primary">
                                    <i class="fas fa-check"></i> Create Account
                                </button>
                            </div>
                        </form>
                        <div style="text-align: center; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid rgba(130, 178, 255, 0.2);">
                            <p style="color: #aaa; margin-bottom: 0.5rem;">Already have an account?</p>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="showLogin" value="1">
                                <button type="submit" class="btn btn-secondary">
                                    <i class="fas fa-sign-in-alt"></i> Login Instead
                                </button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Login Form -->
                    <div class="parent-form">
                        <h2><i class="fas fa-sign-in-alt"></i> Parent Login</h2>
                        <p style="text-align: center; color: #aaa; margin-bottom: 1.5rem;">
                            Access your dashboard and messaging
                        </p>
                        <form method="post">
                            <div class="form-group">
                                <label for="login_email"><i class="fas fa-envelope"></i> Email Address *</label>
                                <input type="email" id="login_email" name="email" placeholder="your.email@example.com" required>
                            </div>
                            <div class="form-group">
                                <label for="login_password"><i class="fas fa-lock"></i> Password *</label>
                                <input type="password" id="login_password" name="password" placeholder="Enter your password" required>
                            </div>
                            <div class="button-group">
                                <button type="submit" name="login" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt"></i> Login
                                </button>
                            </div>
                        </form>
                        <div style="text-align: center; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid rgba(130, 178, 255, 0.2);">
                            <p style="color: #aaa; margin-bottom: 0.5rem;">Don't have an account?</p>
                            <form method="post" style="display: inline;">
                                <button type="submit" class="btn btn-secondary">
                                    <i class="fas fa-user-plus"></i> Sign Up
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- Parent Dashboard -->
            <div class="welcome-header">
                <h2><i class="fas fa-home"></i> Welcome, <?php echo htmlspecialchars($_SESSION['parent']['name']); ?>!</h2>
                <p style="color: #ddd; margin-bottom: 0.5rem;">
                    <strong><i class="fas fa-child"></i> Child:</strong> <?php echo htmlspecialchars($_SESSION['parent']['child_name']); ?>
                </p>
                <p style="color: #ddd; margin-bottom: 1rem;">
                    <strong><i class="fas fa-graduation-cap"></i> Grade:</strong> <?php echo htmlspecialchars($_SESSION['parent']['child_grade']); ?>
                </p>
                
                
                
                <a href="?logout=1" class="btn btn-secondary" style="margin-top: 1rem; text-decoration: none;">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>

            <!-- Student Grades Section (Admin-Controlled) -->
            <?php
            // Load students data from database based on linked children codes
            $linkedChildren = [];
            if (!empty($_SESSION['parent']['linked_children'])) {
                $decoded = json_decode($_SESSION['parent']['linked_children'], true);
                if (is_array($decoded)) {
                    $linkedChildren = $decoded;
                }
            }

            if (!empty($linkedChildren)):
            ?>
            <div class="parent-form" id="gradesSection">
                <h2><i class="fas fa-chart-line"></i> Your Child's Grades</h2>
                <p style="color: #aaa; margin-bottom: 1.5rem; text-align: center;">
                    Grades are managed and updated by school administrators
                </p>
                <div style="text-align: right; margin-bottom: 1rem;">
                    <button type="button" id="downloadPdfBtn" class="btn btn-secondary">
                        <i class="fas fa-file-pdf"></i> Download Grades as PDF
                    </button>
                </div>
                
                <?php
                $hasGrades = false;
                $exportStudents = [];
                foreach ($linkedChildren as $childCode):
                    // Find student by child code from database
                    $stmt = $pdo->prepare('SELECT name, grade, section, subjects FROM students WHERE child_code = :code LIMIT 1');
                    $stmt->execute(['code' => $childCode]);
                    $student = $stmt->fetch();

                    if ($student):
                            $hasGrades = true;
                            $subjects = $student['subjects'] ? json_decode($student['subjects'], true) : [];
                            
                            // Calculate average final grade across subjects (report-card style)
                            $avgGrade = 0;
                            if (!empty($subjects)) {
                                $total = 0;
                                $count = 0;
                                foreach ($subjects as $sub) {
                                    // Prefer per-subject final if available, otherwise fallback to legacy grade field
                                    $val = isset($sub['final']) ? floatval($sub['final']) : floatval($sub['grade'] ?? 0);
                                    if ($val > 0) {
                                        $total += $val;
                                        $count++;
                                    }
                                }
                                if ($count > 0) {
                                    $avgGrade = $total / $count;
                                }
                            }

                            // Prepare data for PDF export
                            $exportSubjects = [];
                            foreach ($subjects as $subject) {
                                $displayGradeForExport = isset($subject['final']) ? $subject['final'] : ($subject['grade'] ?? '');
                                $writtenActivities = [];
                                $writtenQuizzes = [];
                                if (isset($subject['writtenActivities']) && is_array($subject['writtenActivities'])) {
                                    $writtenActivities = $subject['writtenActivities'];
                                } elseif (isset($subject['activities']) && is_array($subject['activities'])) {
                                    // Backward-compat alias
                                    $writtenActivities = $subject['activities'];
                                }
                                if (isset($subject['writtenQuizzes']) && is_array($subject['writtenQuizzes'])) {
                                    $writtenQuizzes = $subject['writtenQuizzes'];
                                } elseif (isset($subject['quizzes']) && is_array($subject['quizzes'])) {
                                    // Backward-compat alias
                                    $writtenQuizzes = $subject['quizzes'];
                                }
                                $exportSubjects[] = [
                                    'title' => $subject['title'] ?? '',
                                    'grade' => $displayGradeForExport,
                                    'written' => $subject['written'] ?? '',
                                    'writtenActivities' => $writtenActivities,
                                    'writtenQuizzes' => $writtenQuizzes,
                                    'performance' => $subject['performance'] ?? '',
                                    'quarterly' => $subject['quarterly'] ?? '',
                                    'quarterLabel' => $subject['quarterLabel'] ?? ($subject['quarter'] ?? ''),
                                    'final' => $subject['final'] ?? ($subject['grade'] ?? ''),
                                ];
                            }

                            $exportStudents[] = [
                                'name' => $student['name'],
                                'grade' => $student['grade'],
                                'section' => $student['section'],
                                'average' => $avgGrade,
                                'subjects' => $exportSubjects,
                            ];

                            $exportStudentIndex = count($exportStudents) - 1;
                ?>
                
                <div style="background: rgba(0,0,0,0.3); border: 2px solid rgba(130, 178, 255, 0.3); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem;">
                    <!-- Student Header -->
                    <div style="margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid rgba(255,255,255,0.1);">
                        <h3 style="color: #82b2ff; margin: 0 0 0.8rem 0; font-size: 1.4rem;">
                            <i class="fas fa-user-graduate"></i> <?php echo htmlspecialchars($student['name']); ?>
                        </h3>
                        <div style="display: flex; gap: 1.5rem; flex-wrap: wrap;">
                            <span style="color: #ddd;">
                                <i class="fas fa-layer-group"></i> <strong>Grade:</strong> <?php echo htmlspecialchars($student['grade']); ?>
                            </span>
                            <span style="color: #ddd;">
                                <i class="fas fa-door-open"></i> <strong>Section:</strong> <?php echo htmlspecialchars($student['section']); ?>
                            </span>
                            <?php if (!empty($subjects)): ?>
                            <span style="color: #4CAF50;">
                                <i class="fas fa-chart-line"></i> <strong>Average:</strong> <?php echo number_format($avgGrade, 1); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Grades Display -->
                    <?php if (!empty($subjects)): ?>
                    <div>
                        <h4 style="color: #a5d6a7; margin-bottom: 1rem;">
                            <i class="fas fa-book"></i> Subject Grades (<?php echo count($subjects); ?>)
                        </h4>
                        <p style="color:#aaa; font-size:0.9rem; margin-bottom:0.8rem;">Tip: Click a subject to see detailed Activities + Quizzes (Written Works), Performance Tasks, and Quarterly Assessment (if available).</p>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.8rem;">
                               <?php foreach ($subjects as $subIndex => $subject): 
                                $displayGrade = isset($subject['final']) ? $subject['final'] : ($subject['grade'] ?? '');
                                $gradeValue = floatval($displayGrade);
                                $gradeColor = $gradeValue >= 90 ? '#4CAF50' : ($gradeValue >= 80 ? '#8BC34A' : ($gradeValue >= 75 ? '#FFC107' : '#FF5722'));
                                $quarterLabel = $subject['quarterLabel'] ?? ($subject['quarter'] ?? '');
                            ?>
                            <div class="subject-card" 
                                 style="background: rgba(255,255,255,0.05); padding: 1rem; border-radius: 8px; border-left: 4px solid <?php echo $gradeColor; ?>; cursor:pointer;"
                                   data-student-index="<?php echo htmlspecialchars((string)$exportStudentIndex); ?>"
                                   data-subject-index="<?php echo htmlspecialchars((string)$subIndex); ?>"
                                 data-subject-title="<?php echo htmlspecialchars($subject['title'] ?? ''); ?>"
                                 data-final-grade="<?php echo htmlspecialchars($displayGrade); ?>"
                                 data-written="<?php echo htmlspecialchars($subject['written'] ?? ''); ?>"
                                 data-performance="<?php echo htmlspecialchars($subject['performance'] ?? ''); ?>"
                                 data-quarterly="<?php echo htmlspecialchars($subject['quarterly'] ?? ''); ?>"
                                 data-quarter-label="<?php echo htmlspecialchars($quarterLabel); ?>">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <div style="font-size: 0.85rem; color: #aaa; margin-bottom: 0.3rem;">Subject</div>
                                        <div style="font-weight: 600; color: #fff;">
                                            <?php echo htmlspecialchars($subject['title']); ?>
                                            <?php if ($quarterLabel): ?>
                                                <span style="display:inline-block; margin-left:6px; font-size:0.75rem; color:#a5d6a7; background:rgba(165,214,167,0.15); padding:2px 6px; border-radius:10px;">
                                                    <?php echo htmlspecialchars($quarterLabel); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div style="text-align: right;">
                                        <div style="font-size: 0.85rem; color: #aaa; margin-bottom: 0.3rem;">Grade</div>
                                        <div style="font-size: 1.8rem; font-weight: bold; color: <?php echo $gradeColor; ?>;">
                                            <?php echo htmlspecialchars($displayGrade); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <div style="text-align: center; padding: 2rem; background: rgba(255, 193, 7, 0.1); border-radius: 8px; border: 1px dashed #FFC107;">
                        <i class="fas fa-clock" style="font-size: 3rem; color: #FFC107; margin-bottom: 1rem;"></i>
                        <p style="color: #FFC107; margin: 0;">No grades have been entered yet by the administrator.</p>
                        <p style="color: #aaa; margin: 0.5rem 0 0 0; font-size: 0.9rem;">Please check back later or contact your teacher.</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php
                        endif;
                    endforeach;
                
                if (!empty($exportStudents)) {
                    echo '<script>window.parentGradesData = ' . json_encode($exportStudents) . ';</script>';
                }

                if (!$hasGrades):
                ?>
                <div style="text-align: center; padding: 2rem; background: rgba(255, 193, 7, 0.1); border-radius: 8px; border: 1px dashed #FFC107;">
                    <i class="fas fa-info-circle" style="font-size: 3rem; color: #FFC107; margin-bottom: 1rem;"></i>
                    <p style="color: #FFC107; margin: 0;">No linked students found with grades.</p>
                    <p style="color: #aaa; margin: 0.5rem 0 0 0; font-size: 0.9rem;">
                        Make sure you signed up with the correct Child Code from your teacher and that your child's grades have been entered by the school.
                    </p>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Message Section -->
            <div class="parent-form">
                <h2><i class="fas fa-envelope"></i> Send Message to Teacher</h2>
                <p style="color: #aaa; margin-bottom: 1.5rem; text-align: center;">
                    Request your child's grades or ask questions. Messages are sent directly to your selected teacher.
                </p>
                <form method="post">
                    <div class="form-group">
                        <label for="teacher"><i class="fas fa-user-tie"></i> Select Teacher *</label>
                        <select id="teacher" name="teacher" required>
                            <option value="">-- Choose Teacher --</option>
                            <?php
                            // Load teacher accounts (teacher1 - teacher5)
                            $teacherStmt = $pdo->query("SELECT username, name FROM admins WHERE role = 'teacher' ORDER BY username");
                            $teachers = $teacherStmt->fetchAll();
                            foreach ($teachers as $t): ?>
                                <option value="<?php echo htmlspecialchars($t['username']); ?>">
                                    <?php echo htmlspecialchars($t['name'] . ' (' . $t['username'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="subject"><i class="fas fa-tag"></i> Subject *</label>
                        <input type="text" id="subject" name="subject" placeholder="e.g., Grade Request for Quarter 2" required>
                    </div>
                    <div class="form-group">
                        <label for="message"><i class="fas fa-comment"></i> Message *</label>
                        <textarea id="message" name="message" placeholder="Type your message here..." required></textarea>
                    </div>
                    <div class="button-group">
                        <button type="submit" name="send" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Send Message
                        </button>
                    </div>
                </form>
            </div>

            <!-- Messages History -->
            <div class="messages-section">
                <h2><i class="fas fa-history"></i> Your Message History</h2>
                <?php
                $histStmt = $pdo->prepare("SELECT id, sender_email, parent_email, sender_role, sender_name, child_name, teacher_username, subject, content, timestamp, status
                    FROM messages
                    WHERE (parent_email = :email OR sender_email = :email)
                    ORDER BY timestamp_unix DESC");
                $histStmt->execute(['email' => $_SESSION['parent']['email']]);
                $parentMessages = $histStmt->fetchAll();

                if (!$parentMessages):
                ?>
                    <p style="text-align: center; color: #999; padding: 2rem 0;">
                        <i class="fas fa-inbox" style="font-size: 3rem; display: block; margin-bottom: 1rem; opacity: 0.3;"></i>
                        No messages yet. Send your first message above!
                    </p>
                <?php else: ?>
                    <?php foreach ($parentMessages as $msg): ?>
                        <?php
                            $senderRole = $msg['sender_role'] ?? 'parent';
                            $isFromParent = ($senderRole === 'parent');
                            $isFromTeacher = ($senderRole === 'teacher' || $senderRole === 'admin');
                            $toTeacher = $msg['teacher_username'] ?? '';
                            $replyTeacher = $toTeacher;
                            $safeSubject = $msg['subject'] ?? '';
                        ?>
                        <div class="message-item">
                            <div class="message-sender">
                                <i class="fas fa-user-circle"></i>
                                <?php if ($isFromParent): ?>
                                    You
                                <?php else: ?>
                                    <?php echo htmlspecialchars($msg['sender_name'] ?? 'Teacher'); ?>
                                    <span style="margin-left: 0.5rem; font-size: 0.85rem; color: #ccc;">
                                        (<?php echo htmlspecialchars($msg['sender_role'] ?? 'teacher'); ?>)
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="message-subject">
                                <strong><i class="fas fa-tag"></i> Subject:</strong> <?php echo htmlspecialchars($msg['subject']); ?>
                            </div>
                            <?php if (!empty($toTeacher)): ?>
                                <div style="margin-top: 0.4rem; color:#cbd5e1; font-size: 0.9rem;">
                                    <i class="fas fa-user-tie"></i>
                                    <strong>Teacher:</strong> <?php echo htmlspecialchars($toTeacher); ?>
                                </div>
                            <?php endif; ?>
                            <div class="message-content">
                                <?php echo nl2br(htmlspecialchars($msg['content'])); ?>
                            </div>
                            <div class="message-time">
                                <i class="fas fa-clock"></i> Sent: <?php echo htmlspecialchars($msg['timestamp']); ?>
                            </div>

                            <div style="margin-top: 0.8rem; display:flex; gap:10px;">
                                <button type="button" class="btn btn-secondary" style="padding: 0.55rem 0.9rem; border-radius: 10px;" onclick="prefillReply(<?php echo json_encode($replyTeacher); ?>, <?php echo json_encode($safeSubject); ?>)">
                                    <i class="fas fa-reply"></i> Reply
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </main>
</body>
<script>
document.addEventListener('DOMContentLoaded', function () {
    window.prefillReply = function (teacherUsername, subject) {
        const teacherSelect = document.getElementById('teacher');
        const subjectInput = document.getElementById('subject');
        const messageBox = document.getElementById('message');

        if (teacherSelect && teacherUsername) {
            teacherSelect.value = teacherUsername;
        }
        if (subjectInput) {
            const s = String(subject || '').trim();
            subjectInput.value = s ? (s.startsWith('Re:') ? s : ('Re: ' + s)) : 'Re:';
        }
        if (messageBox) {
            messageBox.focus();
        }

        const form = document.querySelector('.parent-form form');
        if (form) {
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    };

    const btn = document.getElementById('downloadPdfBtn');
    if (btn) {
        btn.addEventListener('click', function () {
            if (!window.jspdf || !window.parentGradesData || !window.parentGradesData.length) {
                alert('No grades available to download yet.');
                return;
            }

            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();

            const pageBottomY = 270;
            const leftX = 18;
            const indentX = 26;
            const wrapWidth = 170;

            function ensureSpace(linesNeeded) {
                const needed = (linesNeeded || 1) * 5;
                if (y + needed > pageBottomY) {
                    doc.addPage();
                    y = 20;
                }
            }

            function writeWrapped(text, x, fontSize) {
                const size = fontSize || 11;
                doc.setFontSize(size);
                const lines = doc.splitTextToSize(String(text || ''), wrapWidth);
                ensureSpace(lines.length);
                doc.text(lines, x, y);
                y += lines.length * 5;
            }

            function formatEntryList(entries) {
                if (!Array.isArray(entries) || !entries.length) return [];
                return entries
                    .map(function (e) {
                        const date = (e && e.date) ? String(e.date) : '';
                        const score = (e && (e.score !== undefined && e.score !== null)) ? String(e.score) : '';
                        if (!date && !score) return null;
                        return (date ? date + ': ' : '') + (score ? score : '');
                    })
                    .filter(Boolean);
            }

            function computeAvgFromEntries(entries) {
                if (!Array.isArray(entries) || !entries.length) return null;
                const nums = entries
                    .map(e => parseFloat(e && e.score !== undefined ? e.score : NaN))
                    .filter(v => Number.isFinite(v));
                if (!nums.length) return null;
                const avg = nums.reduce((s, v) => s + v, 0) / nums.length;
                return Math.round(avg * 10) / 10;
            }

            let y = 20;
            doc.setFontSize(16);
            doc.text('Student Grade Report', 105, y, { align: 'center' });
            y += 12;

            window.parentGradesData.forEach(function (stu, index) {
                if (index > 0) {
                    y += 8;
                }

                ensureSpace(4);

                doc.setFontSize(12);
                doc.text('Student: ' + (stu.name || ''), leftX, y); y += 6;
                doc.text('Grade: ' + (stu.grade || '') + '   Section: ' + (stu.section || ''), leftX, y); y += 6;

                if (stu.average && !isNaN(stu.average)) {
                    const avgStr = (Math.round(stu.average * 10) / 10).toFixed(1);
                    doc.text('Average: ' + avgStr, leftX, y); y += 8;
                } else {
                    y += 4;
                }

                doc.setFontSize(11);
                if (stu.subjects && stu.subjects.length) {
                    stu.subjects.forEach(function (sub) {
                        const title = sub.title || 'Subject';
                        const quarterLabel = sub.quarterLabel || '';
                        const finalGrade = sub.final || sub.grade || '';
                        const written = sub.written || '';
                        const performance = sub.performance || '';
                        const quarterly = sub.quarterly || '';

                        const activitiesLines = formatEntryList(sub.writtenActivities);
                        const quizzesLines = formatEntryList(sub.writtenQuizzes);

                        const computedWritten = (written ? null : (() => {
                            const all = [];
                            if (Array.isArray(sub.writtenActivities)) all.push(...sub.writtenActivities);
                            if (Array.isArray(sub.writtenQuizzes)) all.push(...sub.writtenQuizzes);
                            const avg = computeAvgFromEntries(all);
                            return avg === null ? null : String(avg);
                        })());
                        const writtenToShow = written || (computedWritten || '');

                        // Subject header
                        writeWrapped('- ' + title + (quarterLabel ? ' (' + quarterLabel + ')' : '') + ': ' + finalGrade, indentX, 11);

                        // Components
                        if (written || activitiesLines.length || quizzesLines.length) {
                            writeWrapped('  Written Works (Activities + Quizzes): ' + (writtenToShow || 'N/A'), indentX, 10);
                        }

                        if (activitiesLines.length) {
                            writeWrapped('    Activities:', indentX, 10);
                            activitiesLines.forEach(function (ln) {
                                writeWrapped('      - ' + ln, indentX, 10);
                            });
                        }
                        if (quizzesLines.length) {
                            writeWrapped('    Quizzes:', indentX, 10);
                            quizzesLines.forEach(function (ln) {
                                writeWrapped('      - ' + ln, indentX, 10);
                            });
                        }

                        if (performance) {
                            writeWrapped('  Performance Tasks (PETA): ' + performance, indentX, 10);
                        }
                        if (quarterly) {
                            writeWrapped('  Quarterly Exam: ' + quarterly, indentX, 10);
                        }

                        y += 2;
                    });
                } else {
                    ensureSpace(1);
                    doc.text('- No subject grades entered yet', indentX, y);
                    y += 5;
                }
            });

            doc.save('grades.pdf');
        });
    }

    // Subject detail modal for per-subject breakdown
    const subjectCards = document.querySelectorAll('.subject-card');
    subjectCards.forEach(function (card) {
        card.addEventListener('click', function () {
            const title = card.getAttribute('data-subject-title') || 'Subject';
            const quarterLabel = card.getAttribute('data-quarter-label') || '';
            const finalGrade = card.getAttribute('data-final-grade') || '';

            // Prefer full data from window.parentGradesData via indexes (includes Activities/Quizzes lists)
            const stuIdx = parseInt(card.getAttribute('data-student-index') || '', 10);
            const subIdx = parseInt(card.getAttribute('data-subject-index') || '', 10);
            const subjectData = (
                Number.isFinite(stuIdx) && Number.isFinite(subIdx) &&
                window.parentGradesData && window.parentGradesData[stuIdx] &&
                window.parentGradesData[stuIdx].subjects && window.parentGradesData[stuIdx].subjects[subIdx]
            ) ? window.parentGradesData[stuIdx].subjects[subIdx] : null;

            const written = (subjectData && subjectData.written) ? subjectData.written : (card.getAttribute('data-written') || '');
            const performance = (subjectData && subjectData.performance) ? subjectData.performance : (card.getAttribute('data-performance') || '');
            const quarterly = (subjectData && subjectData.quarterly) ? subjectData.quarterly : (card.getAttribute('data-quarterly') || '');
            const activities = (subjectData && Array.isArray(subjectData.writtenActivities)) ? subjectData.writtenActivities : [];
            const quizzes = (subjectData && Array.isArray(subjectData.writtenQuizzes)) ? subjectData.writtenQuizzes : [];

            function computeAvg(list) {
                if (!Array.isArray(list) || !list.length) return null;
                const nums = list
                    .map(e => parseFloat(e && e.score !== undefined ? e.score : NaN))
                    .filter(v => Number.isFinite(v));
                if (!nums.length) return null;
                const avg = nums.reduce((s, v) => s + v, 0) / nums.length;
                return Math.round(avg * 10) / 10;
            }

            const computedWritten = (written ? null : (() => {
                const avg = computeAvg([].concat(activities || [], quizzes || []));
                return avg === null ? null : String(avg);
            })());
            const writtenToShow = written || (computedWritten || '');

            function renderEntryList(list) {
                if (!Array.isArray(list) || !list.length) return '<p style="color:#9aa0a6; margin:0.25rem 0;">No entries yet.</p>';
                return `
                    <div style="margin-top:0.4rem; display:grid; gap:6px;">
                        ${list.map(e => {
                            const d = (e && e.date) ? String(e.date) : '';
                            const s = (e && (e.score !== undefined && e.score !== null)) ? String(e.score) : '';
                            const label = (d ? `<span style=\"color:#bbb;\">${d}</span>` : '<span style="color:#bbb;">—</span>');
                            const score = (s ? `<span style=\"color:#fff; font-weight:600;\">${s}</span>` : '<span style="color:#fff; font-weight:600;">—</span>');
                            return `
                                <div style="display:flex; justify-content:space-between; gap:12px; padding:8px 10px; border:1px solid rgba(255,255,255,0.12); border-radius:8px; background: rgba(255,255,255,0.04);">
                                    ${label}
                                    ${score}
                                </div>
                            `;
                        }).join('')}
                    </div>
                `;
            }

            const overlay = document.createElement('div');
            overlay.style.position = 'fixed';
            overlay.style.top = '0';
            overlay.style.left = '0';
            overlay.style.right = '0';
            overlay.style.bottom = '0';
            overlay.style.background = 'rgba(0,0,0,0.7)';
            overlay.style.display = 'flex';
            overlay.style.alignItems = 'center';
            overlay.style.justifyContent = 'center';
            overlay.style.zIndex = '9999';

            const box = document.createElement('div');
            box.style.background = 'rgba(0,0,0,0.9)';
            box.style.border = '1px solid rgba(130,178,255,0.4)';
            box.style.borderRadius = '10px';
            box.style.padding = '1.5rem';
            box.style.maxWidth = '400px';
            box.style.width = '90%';

            box.innerHTML = `
                <h3 style="color:#82b2ff; margin-top:0; margin-bottom:0.75rem;">
                    <i class="fas fa-book"></i> ${title}
                </h3>
                ${quarterLabel ? `<p style="color:#a5d6a7; margin:0.25rem 0;"><strong>Quarter:</strong> ${quarterLabel}</p>` : ''}
                <p style="color:#ddd; margin:0.25rem 0;"><strong>Final Grade:</strong> ${finalGrade || 'N/A'}</p>
                <div style="margin-top:0.75rem;">
                    <p style="color:#a5d6a7; margin:0.25rem 0;"><strong>Written Works (30%):</strong> ${(writtenToShow || 'Not available')}</p>
                    <div style="margin-top:0.6rem;">
                        <p style="color:#a5d6a7; margin:0.25rem 0;"><strong>Activities</strong></p>
                        ${renderEntryList(activities)}
                    </div>
                    <div style="margin-top:0.8rem;">
                        <p style="color:#a5d6a7; margin:0.25rem 0;"><strong>Quizzes</strong></p>
                        ${renderEntryList(quizzes)}
                    </div>
                    <div style="margin-top:0.9rem;">
                        <p style="color:#a5d6a7; margin:0.25rem 0;"><strong>PETA / Performance Tasks (50%):</strong> ${performance || 'Not available'}</p>
                        <p style="color:#a5d6a7; margin:0.25rem 0;"><strong>Quarterly Exam (20%):</strong> ${quarterly || 'Not available'}</p>
                    </div>
                </div>
                <button style="margin-top:1rem; padding:0.5rem 1rem; border:none; border-radius:6px; background:#607D8B; color:#fff; cursor:pointer; width:100%;">
                    Close
                </button>
            `;

            box.querySelector('button').addEventListener('click', function () {
                document.body.removeChild(overlay);
            });

            overlay.appendChild(box);
            document.body.appendChild(overlay);
        });
    });
});
</script>
</html>
