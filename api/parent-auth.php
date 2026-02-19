<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/db.php';

// Handle parent signup verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    if ($action === 'verifySignupCode') {
        $email = trim($input['email'] ?? '');
        $fullName = trim($input['fullName'] ?? '');
        $signupCode = trim($input['signupCode'] ?? '');
        $childCodes = $input['childCodes'] ?? [];

        $stmt = $pdo->prepare('SELECT id, name, email, signup_code, signup_code_used, linked_children FROM parents WHERE email = :email AND name = :name AND signup_code = :code LIMIT 1');
        $stmt->execute([
            'email' => $email,
            'name' => $fullName,
            'code' => $signupCode,
        ]);
        $parentFound = $stmt->fetch();

        if (!$parentFound) {
            echo json_encode([
                'success' => false,
                'error' => 'Invalid parent credentials or signup code. Please check your email, name, and code from Parent Portal.'
            ]);
            exit;
        }
        
        if ((int)$parentFound['signup_code_used'] === 1) {
            echo json_encode([
                'success' => false,
                'error' => 'This signup code has already been used. Each code can only be used once.'
            ]);
            exit;
        }
        
        // Verify child codes exist
        $validChildCodes = [];
        
        foreach ($childCodes as $childCode) {
            $childCode = trim($childCode);
            if (empty($childCode)) continue;
            
            $stmt = $pdo->prepare('SELECT id FROM students WHERE child_code = :code LIMIT 1');
            $stmt->execute(['code' => $childCode]);
            $studentFound = $stmt->fetch();

            if ($studentFound) {
                $validChildCodes[] = $childCode;
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => "Invalid child code: {$childCode}. Please check with your child's teacher."
                ]);
                exit;
            }
        }
        
        if (empty($validChildCodes)) {
            echo json_encode([
                'success' => false,
                'error' => 'At least one valid child code is required.'
            ]);
            exit;
        }
        
        // Mark signup code as used and link children
        $linkedChildrenJson = json_encode($validChildCodes);
        $update = $pdo->prepare('UPDATE parents SET signup_code_used = 1, linked_children = :linked WHERE id = :id');
        $update->execute([
            'linked' => $linkedChildrenJson,
            'id' => $parentFound['id'],
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Signup successful! You are now linked to your child\'s account.',
            'parent' => [
                'email' => $parentFound['email'],
                'name' => $parentFound['name'],
                'linkedChildren' => $validChildCodes
            ]
        ]);
        exit;
    }
    
    if ($action === 'getChildGrades') {
        $parentEmail = trim($input['parentEmail'] ?? '');
        $childCode = trim($input['childCode'] ?? '');

        // Verify parent has access to this child by checking linked_children JSON
        $stmt = $pdo->prepare('SELECT linked_children FROM parents WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $parentEmail]);
        $parent = $stmt->fetch();

        $hasAccess = false;
        if ($parent) {
            $linked = $parent['linked_children'] ? json_decode($parent['linked_children'], true) : [];
            if (is_array($linked) && in_array($childCode, $linked, true)) {
                $hasAccess = true;
            }
        }
        
        if (!$hasAccess) {
            echo json_encode([
                'success' => false,
                'error' => 'You do not have access to this child\'s grades.'
            ]);
            exit;
        }
        
        // Get student grades
        $stmt = $pdo->prepare('SELECT name, grade, section, subjects FROM students WHERE child_code = :code LIMIT 1');
        $stmt->execute(['code' => $childCode]);
        $student = $stmt->fetch();

        if ($student) {
            echo json_encode([
                'success' => true,
                'student' => [
                    'name' => $student['name'] ?? 'Unknown',
                    'grade' => $student['grade'] ?? 'Unknown',
                    'section' => $student['section'] ?? 'Unknown',
                    'subjects' => $student['subjects'] ? json_decode($student['subjects'], true) : []
                ]
            ]);
            exit;
        }

        echo json_encode([
            'success' => false,
            'error' => 'Student not found.'
        ]);
        exit;
    }
}

// Handle GET requests for parent data
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $email = $_GET['email'] ?? '';

    if (empty($email)) {
        echo json_encode(['success' => false, 'error' => 'Email required']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT id, name, email, phone, child_name, child_grade, relationship, signup_code, signup_code_used, linked_children, created_at FROM parents WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $parent = $stmt->fetch();

    if ($parent) {
        echo json_encode([
            'success' => true,
            'parent' => $parent
        ]);
        exit;
    }

    echo json_encode([
        'success' => false,
        'error' => 'Parent not found'
    ]);
}