<?php
/**
 * ElectroKit API - MariaDB storage for settings and projects
 * Saves data to MariaDB database on Synology server
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// ─── DATABASE CONFIG ─────────────────────────────────────────────────────────

// ВАЖНО: Создайте файл db_config.php с вашими настройками:
// <?php
// define('DB_HOST', 'localhost');
// define('DB_NAME', 'electrokit');
// define('DB_USER', 'electrokit_user');
// define('DB_PASS', 'your_password');

if (file_exists(__DIR__ . '/db_config.php')) {
    require_once __DIR__ . '/db_config.php';
} else {
    // Дефолтные настройки - ИЗМЕНИТЕ ИХ!
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'electrokit');
    define('DB_USER', 'root');
    define('DB_PASS', '');
}

// ─── DATABASE CONNECTION ─────────────────────────────────────────────────────

function getDB() {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            respondError('Database connection failed: ' . $e->getMessage());
        }
    }

    return $pdo;
}

// ─── INITIALIZE DATABASE ─────────────────────────────────────────────────────

function initDatabase() {
    $pdo = getDB();

    // Settings table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            data JSON NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Projects table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS projects (
            id VARCHAR(64) PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            type VARCHAR(50) DEFAULT 'apartment',
            data JSON NOT NULL,
            saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_name (name),
            INDEX idx_type (type),
            INDEX idx_saved (saved_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    respond(['success' => true, 'message' => 'Database initialized']);
}

// ─── ROUTE HANDLER ───────────────────────────────────────────────────────────

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'init':
        initDatabase();
        break;

    case 'getSettings':
        handleGetSettings();
        break;

    case 'saveSettings':
        handleSaveSettings();
        break;

    case 'getProjects':
        handleGetProjects();
        break;

    case 'saveProject':
        handleSaveProject();
        break;

    case 'deleteProject':
        handleDeleteProject();
        break;

    case 'listProjects':
        handleListProjects();
        break;

    default:
        respondError('Invalid action');
}

// ─── SETTINGS ────────────────────────────────────────────────────────────────

function handleGetSettings() {
    $pdo = getDB();

    $stmt = $pdo->query("SELECT data FROM settings ORDER BY id DESC LIMIT 1");
    $row = $stmt->fetch();

    if (!$row) {
        respond(['settings' => null]);
    }

    $settings = json_decode($row['data'], true);
    respond(['settings' => $settings]);
}

function handleSaveSettings() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!isset($data['settings'])) {
        respondError('No settings provided');
    }

    $settings = $data['settings'];
    $settings['_updated'] = date('c');

    $pdo = getDB();

    // Delete old settings (keep only latest)
    $pdo->exec("DELETE FROM settings");

    // Insert new settings
    $stmt = $pdo->prepare("INSERT INTO settings (data) VALUES (:data)");
    $stmt->execute(['data' => json_encode($settings, JSON_UNESCAPED_UNICODE)]);

    respond(['success' => true, 'updated' => $settings['_updated']]);
}

// ─── PROJECTS ────────────────────────────────────────────────────────────────

function handleGetProjects() {
    $pdo = getDB();

    $stmt = $pdo->query("
        SELECT id, name, type, data, saved_at
        FROM projects
        ORDER BY saved_at DESC
    ");

    $projects = [];
    while ($row = $stmt->fetch()) {
        $projectData = json_decode($row['data'], true);
        $projectData['id'] = $row['id'];
        $projectData['name'] = $row['name'];
        $projectData['type'] = $row['type'];
        $projectData['_saved'] = $row['saved_at'];
        $projects[] = $projectData;
    }

    respond(['projects' => $projects]);
}

function handleSaveProject() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!isset($data['project'])) {
        respondError('No project provided');
    }

    $project = $data['project'];

    // Generate project ID if not exists
    if (!isset($project['id']) || empty($project['id'])) {
        $project['id'] = uniqid('proj_', true);
    }

    $id = $project['id'];
    $name = isset($project['name']) ? $project['name'] : 'Без названия';
    $type = isset($project['type']) ? $project['type'] : 'apartment';

    $pdo = getDB();

    // Check if project exists
    $stmt = $pdo->prepare("SELECT id FROM projects WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $exists = $stmt->fetch();

    if ($exists) {
        // Update existing
        $stmt = $pdo->prepare("
            UPDATE projects
            SET name = :name, type = :type, data = :data, saved_at = NOW()
            WHERE id = :id
        ");
    } else {
        // Insert new
        $stmt = $pdo->prepare("
            INSERT INTO projects (id, name, type, data, saved_at, created_at)
            VALUES (:id, :name, :type, :data, NOW(), NOW())
        ");
    }

    $stmt->execute([
        'id' => $id,
        'name' => $name,
        'type' => $type,
        'data' => json_encode($project, JSON_UNESCAPED_UNICODE)
    ]);

    $project['_saved'] = date('c');

    respond(['success' => true, 'project' => $project]);
}

function handleDeleteProject() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!isset($data['id'])) {
        respondError('No project ID provided');
    }

    $pdo = getDB();

    $stmt = $pdo->prepare("DELETE FROM projects WHERE id = :id");
    $stmt->execute(['id' => $data['id']]);

    respond(['success' => true]);
}

function handleListProjects() {
    $pdo = getDB();

    $stmt = $pdo->query("
        SELECT id, name, type, saved_at,
               JSON_LENGTH(JSON_EXTRACT(data, '$.rooms')) as room_count
        FROM projects
        ORDER BY saved_at DESC
    ");

    $list = [];
    while ($row = $stmt->fetch()) {
        $list[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'type' => $row['type'],
            '_saved' => $row['saved_at'],
            'roomCount' => (int)$row['room_count']
        ];
    }

    respond(['projects' => $list]);
}

// ─── HELPERS ─────────────────────────────────────────────────────────────────

function respond($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function respondError($message) {
    http_response_code(400);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}
