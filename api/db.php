<?php
// MySQL database connection for Academic Tracker
// Reads Railway env vars first, then falls back to local defaults
function env_or_default(string $key, string $default): string {
    $value = getenv($key);
    return ($value === false || $value === '') ? $default : $value;
}

$databaseUrl = getenv('DATABASE_URL');

if ($databaseUrl !== false && $databaseUrl !== '') {
    $parts = parse_url($databaseUrl);
    $DB_HOST = $parts['host'] ?? '127.0.0.1';
    $DB_PORT = isset($parts['port']) ? (string)$parts['port'] : '3306';
    $DB_NAME = isset($parts['path']) ? ltrim($parts['path'], '/') : 'academic_tracker';
    $DB_USER = $parts['user'] ?? 'tracker_user';
    $DB_PASS = $parts['pass'] ?? 'admin';
} else {
    // 127.0.0.1 is used instead of localhost to force TCP (not unix socket)
    $DB_HOST = env_or_default('MYSQLHOST', env_or_default('DB_HOST', '127.0.0.1'));
    $DB_PORT = env_or_default('MYSQLPORT', env_or_default('DB_PORT', '3306'));
    $DB_NAME = env_or_default('MYSQLDATABASE', env_or_default('DB_NAME', 'academic_tracker'));
    $DB_USER = env_or_default('MYSQLUSER', env_or_default('DB_USER', 'tracker_user'));
    $DB_PASS = env_or_default('MYSQLPASSWORD', env_or_default('DB_PASS', 'admin'));
}
$DB_CHARSET = 'utf8mb4';

$dsn = "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset={$DB_CHARSET}";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit;
}

// Create required tables if they do not exist
$schemaStatements = [
    // Admins (includes teachers)
    "CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role VARCHAR(20) NOT NULL,
        name VARCHAR(100) NOT NULL,
        created_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // Students with JSON subjects
    "CREATE TABLE IF NOT EXISTS students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        grade VARCHAR(50) NOT NULL,
        section VARCHAR(50) NOT NULL,
        child_code VARCHAR(32) NOT NULL UNIQUE,
        subjects TEXT NOT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME DEFAULT NULL,
        created_by VARCHAR(50) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // Parents with JSON linked children
    "CREATE TABLE IF NOT EXISTS parents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        phone VARCHAR(50) NOT NULL,
        child_name VARCHAR(150) NOT NULL,
        child_grade VARCHAR(50) NOT NULL,
        relationship VARCHAR(50) NOT NULL,
        password VARCHAR(255) NOT NULL,
        signup_code VARCHAR(20) NOT NULL UNIQUE,
        signup_code_used TINYINT(1) NOT NULL DEFAULT 0,
        linked_children TEXT NOT NULL,
        created_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // Messages from parents to specific teachers/admin
    "CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_email VARCHAR(150) NOT NULL,
        sender_name VARCHAR(150) NOT NULL,
        child_name VARCHAR(150) NOT NULL,
        teacher_username VARCHAR(50) DEFAULT NULL,
        subject VARCHAR(200) NOT NULL,
        content TEXT NOT NULL,
        type VARCHAR(50) NOT NULL,
        status VARCHAR(20) NOT NULL,
        timestamp DATETIME NOT NULL,
        timestamp_unix INT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
];

foreach ($schemaStatements as $sql) {
    $pdo->exec($sql);
}

// Ensure messages table has teacher_username column (for older installs)
try {
    $pdo->exec("ALTER TABLE messages ADD COLUMN IF NOT EXISTS teacher_username VARCHAR(50) DEFAULT NULL AFTER child_name");
} catch (PDOException $e) {
    // Ignore if the column already exists or IF NOT EXISTS is unsupported
}

// Ensure default admin + 5 teacher accounts exist
$stmt = $pdo->query('SELECT COUNT(*) AS cnt FROM admins');
$row = $stmt->fetch();

if ((int)$row['cnt'] === 0) {
    $now = date('Y-m-d H:i:s');

    $admins = [
        [
            'username' => 'admin',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'role' => 'admin',
            'name' => 'System Administrator',
            'created_at' => $now,
        ],
        [
            'username' => 'teacher1',
            'password' => password_hash('teacher123', PASSWORD_DEFAULT),
            'role' => 'teacher',
            'name' => 'Teacher 1',
            'created_at' => $now,
        ],
        [
            'username' => 'teacher2',
            'password' => password_hash('teacher123', PASSWORD_DEFAULT),
            'role' => 'teacher',
            'name' => 'Teacher 2',
            'created_at' => $now,
        ],
        [
            'username' => 'teacher3',
            'password' => password_hash('teacher123', PASSWORD_DEFAULT),
            'role' => 'teacher',
            'name' => 'Teacher 3',
            'created_at' => $now,
        ],
        [
            'username' => 'teacher4',
            'password' => password_hash('teacher123', PASSWORD_DEFAULT),
            'role' => 'teacher',
            'name' => 'Teacher 4',
            'created_at' => $now,
        ],
        [
            'username' => 'teacher5',
            'password' => password_hash('teacher123', PASSWORD_DEFAULT),
            'role' => 'teacher',
            'name' => 'Teacher 5',
            'created_at' => $now,
        ],
    ];

    $insert = $pdo->prepare('INSERT INTO admins (username, password, role, name, created_at) VALUES (:username, :password, :role, :name, :created_at)');
    foreach ($admins as $a) {
        $insert->execute($a);
    }
}
