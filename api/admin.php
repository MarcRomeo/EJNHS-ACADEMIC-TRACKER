<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    // Admin Login
    if ($action === 'login') {
        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            echo json_encode([
                'success' => false,
                'error' => 'Username and password are required'
            ]);
            exit;
        }
        
        $stmt = $pdo->prepare('SELECT username, password, role, name FROM admins WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'admin' => [
                    'username' => $admin['username'],
                    'role' => $admin['role'],
                    'name' => $admin['name']
                ]
            ]);
            exit;
        }

        echo json_encode([
            'success' => false,
            'error' => 'Invalid username or password'
        ]);
        exit;
    }
    
    // Add Student
    if ($action === 'addStudent') {
        $name = trim($input['name'] ?? '');
        $grade = trim($input['grade'] ?? '');
        $section = trim($input['section'] ?? '');
        
        if (empty($name) || empty($grade) || empty($section)) {
            echo json_encode([
                'success' => false,
                'error' => 'Name, grade, and section are required'
            ]);
            exit;
        }
        
        // Generate unique child code
        do {
            $childCode = strtoupper(bin2hex(random_bytes(8))); // 16 character code
            $check = $pdo->prepare('SELECT id FROM students WHERE child_code = :code LIMIT 1');
            $check->execute(['code' => $childCode]);
        } while ($check->fetch());

        $createdAt = date('Y-m-d H:i:s');
        $createdBy = $input['adminUsername'] ?? 'admin';

        $insert = $pdo->prepare('INSERT INTO students (name, grade, section, child_code, subjects, created_at, created_by) VALUES (:name, :grade, :section, :child_code, :subjects, :created_at, :created_by)');
        $insert->execute([
            'name' => $name,
            'grade' => $grade,
            'section' => $section,
            'child_code' => $childCode,
            'subjects' => json_encode([]),
            'created_at' => $createdAt,
            'created_by' => $createdBy,
        ]);

        $studentId = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Student added successfully',
            'student' => [
                'id' => $studentId,
                'name' => $name,
                'grade' => $grade,
                'section' => $section,
                'childCode' => $childCode,
                'subjects' => [],
                'createdAt' => $createdAt,
                'createdBy' => $createdBy,
            ]
        ]);
        exit;
    }
    
    // Get All Students
    if ($action === 'getStudents') {
        $stmt = $pdo->query('SELECT id, name, grade, section, child_code, subjects, created_at, updated_at, created_by FROM students ORDER BY created_at DESC');
        $students = [];
        while ($row = $stmt->fetch()) {
            $students[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'grade' => $row['grade'],
                'section' => $row['section'],
                'childCode' => $row['child_code'],
                'subjects' => $row['subjects'] ? json_decode($row['subjects'], true) : [],
                'createdAt' => $row['created_at'],
                'updatedAt' => $row['updated_at'],
                'createdBy' => $row['created_by'],
            ];
        }

        echo json_encode([
            'success' => true,
            'students' => $students
        ]);
        exit;
    }
    
    // Update Student Grades
    if ($action === 'updateGrades') {
        $studentId = $input['studentId'] ?? '';
        $subjects = $input['subjects'] ?? [];
        
        if (empty($studentId)) {
            echo json_encode([
                'success' => false,
                'error' => 'Student ID is required'
            ]);
            exit;
        }
        
        $stmt = $pdo->prepare('SELECT id FROM students WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $studentId]);
        if (!$stmt->fetch()) {
            echo json_encode([
                'success' => false,
                'error' => 'Student not found'
            ]);
            exit;
        }

        $update = $pdo->prepare('UPDATE students SET subjects = :subjects, updated_at = :updated_at WHERE id = :id');
        $update->execute([
            'subjects' => json_encode($subjects),
            'updated_at' => date('Y-m-d H:i:s'),
            'id' => $studentId,
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Grades updated successfully'
        ]);
        exit;
    }
    
    // Get Messages
    if ($action === 'getMessages') {
        $stmt = $pdo->query('SELECT id, sender_email, sender_name, child_name, teacher_username, subject, content, type, status, timestamp, timestamp_unix FROM messages ORDER BY timestamp_unix DESC');
        $messages = [];
        while ($row = $stmt->fetch()) {
            $messages[] = [
                'id' => $row['id'],
                'sender' => $row['sender_email'],
                'senderName' => $row['sender_name'],
                'childName' => $row['child_name'],
                'teacherUsername' => $row['teacher_username'],
                'subject' => $row['subject'],
                'content' => $row['content'],
                'type' => $row['type'],
                'status' => $row['status'],
                'timestamp' => $row['timestamp'],
                'timestampUnix' => (int)$row['timestamp_unix'],
            ];
        }

        echo json_encode([
            'success' => true,
            'messages' => $messages
        ]);
        exit;
    }
    
    // Delete Student
    if ($action === 'deleteStudent') {
        $studentId = $input['studentId'] ?? '';
        
        if (empty($studentId)) {
            echo json_encode([
                'success' => false,
                'error' => 'Student ID is required'
            ]);
            exit;
        }
        
        $delete = $pdo->prepare('DELETE FROM students WHERE id = :id');
        $delete->execute(['id' => $studentId]);

        echo json_encode([
            'success' => true,
            'message' => 'Student deleted successfully'
        ]);
        exit;
    }
}

echo json_encode([
    'success' => false,
    'error' => 'Invalid request'
]);