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
            background: rgba(255, 255, 255, 0.92);
            color: #111827;
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
        }
        .form-group input::placeholder,
        .form-group textarea::placeholder {
            color: #6b7280;
        }
        .form-group select option {
            background: #ffffff;
            color: #111827;
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
                foreach ($linkedChildren as $childIndex => $childCode):
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
                            foreach ($subjects as $subIndex => $subject) {
                                $displayGradeForExport = isset($subject['final']) ? $subject['final'] : ($subject['grade'] ?? '');

                                $writtenActivities = [];
                                if (!empty($subject['writtenActivities']) && is_array($subject['writtenActivities'])) {
                                    $writtenActivities = $subject['writtenActivities'];
                                } elseif (!empty($subject['activities']) && is_array($subject['activities'])) {
                                    $writtenActivities = $subject['activities'];
                                }

                                $writtenQuizzes = [];
                                if (!empty($subject['writtenQuizzes']) && is_array($subject['writtenQuizzes'])) {
                                    $writtenQuizzes = $subject['writtenQuizzes'];
                                } elseif (!empty($subject['quizzes']) && is_array($subject['quizzes'])) {
                                    $writtenQuizzes = $subject['quizzes'];
                                }

                                $performancePetaTasks = [];
                                if (!empty($subject['performancePetaTasks']) && is_array($subject['performancePetaTasks'])) {
                                    $performancePetaTasks = $subject['performancePetaTasks'];
                                } elseif (!empty($subject['performancePeta']) && is_array($subject['performancePeta'])) {
                                    $performancePetaTasks = $subject['performancePeta'];
                                }

                                $performanceOtherTasks = [];
                                if (!empty($subject['performanceOtherTasks']) && is_array($subject['performanceOtherTasks'])) {
                                    $performanceOtherTasks = $subject['performanceOtherTasks'];
                                } elseif (!empty($subject['performanceOther']) && is_array($subject['performanceOther'])) {
                                    $performanceOtherTasks = $subject['performanceOther'];
                                }

                                $performanceTasks = [];
                                if (!empty($subject['performanceTasks']) && is_array($subject['performanceTasks'])) {
                                    $performanceTasks = $subject['performanceTasks'];
                                }

                                $quarterLabel = $subject['quarterLabel'] ?? ($subject['quarter'] ?? '');

                                $exportSubjects[] = [
                                    'title' => $subject['title'] ?? '',
                                    'grade' => $displayGradeForExport,
                                    'written' => $subject['written'] ?? '',
                                    'performance' => $subject['performance'] ?? '',
                                    'quarterly' => $subject['quarterly'] ?? '',
                                    'quarterLabel' => $quarterLabel,
                                    'writtenActivities' => $writtenActivities,
                                    'writtenQuizzes' => $writtenQuizzes,
                                    'performancePetaTasks' => $performancePetaTasks,
                                    'performanceOtherTasks' => $performanceOtherTasks,
                                    'performanceTasks' => $performanceTasks,
                                ];
                            }

                            $exportStudents[] = [
                                'name' => $student['name'],
                                'grade' => $student['grade'],
                                'section' => $student['section'],
                                'average' => $avgGrade,
                                'subjects' => $exportSubjects,
                            ];
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
                                <i class="fas fa-chart-line"></i> <strong>General Average:</strong> <?php echo number_format($avgGrade, 0); ?>
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
                                 data-student-index="<?php echo (int)$childIndex; ?>"
                                 data-subject-index="<?php echo (int)$subIndex; ?>"
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
                    echo '<script>window.parentGradesData = ' . json_encode($exportStudents) . '; window.parentGradesDataDetailed = window.parentGradesData;</script>';
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

        <?php endif; ?>
    </main>
</body>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('downloadPdfBtn');
    if (btn) {
        btn.addEventListener('click', function () {
            if (!window.jspdf || !window.parentGradesData || !window.parentGradesData.length) {
                alert('No grades available to download yet.');
                return;
            }

            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();

            function addWrappedText(text, x, y, maxWidth, lineHeight) {
                const lines = doc.splitTextToSize(String(text || ''), maxWidth);
                lines.forEach(function (ln) {
                    if (y > 270) {
                        doc.addPage();
                        y = 20;
                    }
                    doc.text(ln, x, y);
                    y += lineHeight;
                });
                return y;
            }

            function normalizeEntries(list) {
                if (!Array.isArray(list)) return [];
                return list
                    .map(function (e) {
                        if (!e || typeof e !== 'object') return null;
                        return {
                            title: (e.title || '').toString(),
                            date: (e.date || '').toString(),
                            score: (e.score ?? '').toString(),
                            over: (e.over ?? (e.total ?? '')).toString()
                        };
                    })
                    .filter(Boolean)
                    .filter(function (e) {
                        return (e.title || e.date || String(e.score).trim() !== '' || String(e.over).trim() !== '');
                    });
            }

            function addEntriesSection(label, entries, x, y) {
                const list = normalizeEntries(entries);
                doc.setFontSize(10);
                y = addWrappedText(label + ':', x, y, 170, 4.5);
                if (!list.length) {
                    y = addWrappedText('  - None', x, y, 170, 4.5);
                    return y;
                }
                list.forEach(function (e) {
                    const line = '  - ' + (e.title || 'Entry') + (e.date ? (' (' + e.date + ')') : '') +
                        (String(e.score).trim() !== '' || String(e.over).trim() !== '' ? ('  ' + (e.score || '0') + '/' + (e.over || '0')) : '');
                    y = addWrappedText(line, x, y, 170, 4.5);
                });
                return y;
            }

            let y = 20;
            doc.setFontSize(16);
            doc.text('Student Grade Report', 105, y, { align: 'center' });
            y += 12;

            window.parentGradesData.forEach(function (stu, index) {
                if (index > 0) {
                    y += 8;
                }

                if (y > 270) {
                    doc.addPage();
                    y = 20;
                }

                doc.setFontSize(12);
                doc.text('Student: ' + (stu.name || ''), 20, y); y += 6;
                doc.text('Grade: ' + (stu.grade || '') + '   Section: ' + (stu.section || ''), 20, y); y += 6;

                if (stu.average && !isNaN(stu.average)) {
                    const avgStr = String(Math.round(stu.average));
                    doc.text('General Average: ' + avgStr, 20, y); y += 8;
                } else {
                    y += 4;
                }

                doc.setFontSize(11);
                if (stu.subjects && stu.subjects.length) {
                    stu.subjects.forEach(function (sub) {
                        if (y > 270) {
                            doc.addPage();
                            y = 20;
                        }
                        const title = sub.title || 'Subject';
                        const grade = sub.grade || '';
                        const w = sub.written || '';
                        const p = sub.performance || '';
                        const q = sub.quarterly || '';

                        const quarterLabel = sub.quarterLabel || '';
                        const activities = sub.writtenActivities || [];
                        const quizzes = sub.writtenQuizzes || [];
                        const peta = sub.performancePetaTasks || [];
                        const otherPerf = sub.performanceOtherTasks || [];
                        const legacyPerf = sub.performanceTasks || [];

                        const headParts = [];
                        if (quarterLabel) headParts.push(quarterLabel);
                        if (grade) headParts.push('Final: ' + grade);
                        let head = '- ' + title;
                        if (headParts.length) head += ' (' + headParts.join(' | ') + ')';
                        y = addWrappedText(head, 25, y, 175, 5);

                        const parts = [];
                        if (w) parts.push('Written Works: ' + w);
                        if (p) parts.push('Performance Tasks: ' + p);
                        if (q) parts.push('Quarterly Assessment: ' + q);
                        if (parts.length) {
                            y = addWrappedText('  ' + parts.join('  |  '), 30, y, 170, 4.8);
                        }

                        y = addEntriesSection('  Activities', activities, 30, y);
                        y = addEntriesSection('  Quizzes', quizzes, 30, y);

                        const hasSplitPerf = (Array.isArray(peta) && peta.length) || (Array.isArray(otherPerf) && otherPerf.length);
                        if (hasSplitPerf) {
                            y = addEntriesSection('  PETA', peta, 30, y);
                            y = addEntriesSection('  Other Performance Tasks', otherPerf, 30, y);
                        } else {
                            y = addEntriesSection('  Performance Tasks', legacyPerf, 30, y);
                        }

                        y += 2;
                    });
                } else {
                    if (y > 270) {
                        doc.addPage();
                        y = 20;
                    }
                    doc.text('- No subject grades entered yet', 30, y);
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
            const sIdx = parseInt(card.getAttribute('data-student-index') || '0', 10);
            const subIdx = parseInt(card.getAttribute('data-subject-index') || '0', 10);
            const data = (window.parentGradesDataDetailed && window.parentGradesDataDetailed[sIdx] && window.parentGradesDataDetailed[sIdx].subjects)
                ? window.parentGradesDataDetailed[sIdx].subjects[subIdx]
                : null;

            const title = (data && data.title) ? data.title : (card.getAttribute('data-subject-title') || 'Subject');
            const finalGrade = (data && data.grade) ? data.grade : (card.getAttribute('data-final-grade') || '');
            const written = (data && data.written) ? data.written : (card.getAttribute('data-written') || '');
            const performance = (data && data.performance) ? data.performance : (card.getAttribute('data-performance') || '');
            const quarterly = (data && data.quarterly) ? data.quarterly : (card.getAttribute('data-quarterly') || '');
            const quarterLabel = (data && data.quarterLabel) ? data.quarterLabel : (card.getAttribute('data-quarter-label') || '');

            const activities = data && Array.isArray(data.writtenActivities) ? data.writtenActivities : [];
            const quizzes = data && Array.isArray(data.writtenQuizzes) ? data.writtenQuizzes : [];
            const peta = data && Array.isArray(data.performancePetaTasks) ? data.performancePetaTasks : [];
            const otherPerf = data && Array.isArray(data.performanceOtherTasks) ? data.performanceOtherTasks : [];
            const legacyPerf = data && Array.isArray(data.performanceTasks) ? data.performanceTasks : [];

            function escapeHtml(str) {
                return String(str ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            function entriesTable(rows) {
                const list = Array.isArray(rows) ? rows : [];
                if (!list.length) {
                    return '<div style="color:#aaa; font-size:0.9rem;">No entries.</div>';
                }
                return `
                    <div style="overflow:auto; border:1px solid rgba(255,255,255,0.1); border-radius:8px;">
                        <table style="width:100%; border-collapse:collapse; font-size:0.92rem;">
                            <thead>
                                <tr style="background: rgba(255,255,255,0.06);">
                                    <th style="text-align:left; padding:8px; color:#ddd;">Title</th>
                                    <th style="text-align:left; padding:8px; color:#ddd; white-space:nowrap;">Date</th>
                                    <th style="text-align:right; padding:8px; color:#ddd; white-space:nowrap;">Score</th>
                                    <th style="text-align:right; padding:8px; color:#ddd; white-space:nowrap;">Over</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${list.map(function (e) {
                                    const title = escapeHtml(e && e.title ? e.title : '');
                                    const date = escapeHtml(e && e.date ? e.date : '');
                                    const score = escapeHtml(e && (e.score ?? '') !== undefined ? e.score : '');
                                    const over = escapeHtml(e && (e.over ?? (e.total ?? '')) !== undefined ? (e.over ?? (e.total ?? '')) : '');
                                    return `
                                        <tr style="border-top:1px solid rgba(255,255,255,0.08);">
                                            <td style="padding:8px; color:#fff; min-width:180px;">${title}</td>
                                            <td style="padding:8px; color:#ddd; white-space:nowrap;">${date}</td>
                                            <td style="padding:8px; color:#ddd; text-align:right; white-space:nowrap;">${score}</td>
                                            <td style="padding:8px; color:#ddd; text-align:right; white-space:nowrap;">${over}</td>
                                        </tr>
                                    `;
                                }).join('')}
                            </tbody>
                        </table>
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
            box.style.maxWidth = '980px';
            box.style.width = '94%';
            box.style.maxHeight = '85vh';
            box.style.overflow = 'auto';

            box.innerHTML = `
                <h3 style="color:#82b2ff; margin-top:0; margin-bottom:0.75rem;">
                    <i class="fas fa-book"></i> ${title}
                </h3>
                ${quarterLabel ? `<p style="color:#a5d6a7; margin:0.25rem 0;"><strong>Quarter:</strong> ${quarterLabel}</p>` : ''}
                <p style="color:#ddd; margin:0.25rem 0;"><strong>Final Grade:</strong> ${finalGrade || 'N/A'}</p>
                <div style="margin-top:0.75rem;">
                    <p style="color:#a5d6a7; margin:0.25rem 0;"><strong>Written Works (30%) (Activities + Quizzes):</strong> ${written || 'Not available'}</p>
                    <p style="color:#a5d6a7; margin:0.25rem 0;"><strong>Performance Tasks (50%):</strong> ${performance || 'Not available'}</p>
                    <p style="color:#a5d6a7; margin:0.25rem 0;"><strong>Quarterly Assessment (20%):</strong> ${quarterly || 'Not available'}</p>
                </div>

                <div style="margin-top:1rem; display:grid; gap:12px;">
                    <div>
                        <div style="color:#a5d6a7; font-weight:700; margin-bottom:6px;">Activities</div>
                        ${entriesTable(activities)}
                    </div>
                    <div>
                        <div style="color:#a5d6a7; font-weight:700; margin-bottom:6px;">Quizzes</div>
                        ${entriesTable(quizzes)}
                    </div>
                    <div>
                        <div style="color:#a5d6a7; font-weight:700; margin-bottom:6px;">PETA</div>
                        ${entriesTable(peta.length || otherPerf.length ? peta : legacyPerf)}
                    </div>
                    ${peta.length || otherPerf.length ? `
                        <div>
                            <div style="color:#a5d6a7; font-weight:700; margin-bottom:6px;">Other Performance Tasks</div>
                            ${entriesTable(otherPerf)}
                        </div>
                    ` : ''}
                </div>

                <button style="margin-top:1rem; padding:0.5rem 1rem; border:none; border-radius:6px; background:#607D8B; color:#fff; cursor:pointer; width:100%;">
                    Close
                </button>
            `;

            box.querySelector('button').addEventListener('click', function () {
                document.body.removeChild(overlay);
            });

            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) {
                    document.body.removeChild(overlay);
                }
            });

            overlay.appendChild(box);
            document.body.appendChild(overlay);
        });
    });
});
</script>
</html>
