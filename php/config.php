<?php
/**
 * config.php
 * Central configuration — reads from environment variables so the same
 * code works locally (via .env or server config) and on any cloud host
 * (Render, Railway, AWS, Heroku, etc.).
 *
 * Set these environment variables on your hosting dashboard:
 *
 *  MYSQL_HOST      e.g. mysql-xxx.aivencloud.com
 *  MYSQL_PORT      e.g. 3306
 *  MYSQL_USER      e.g. avnadmin
 *  MYSQL_PASS      e.g. your-password
 *  MYSQL_DB        e.g. guvi_auth
 *
 *  MONGO_URI       e.g. mongodb+srv://user:pass@cluster.mongodb.net
 *  MONGO_DB        e.g. guvi_profiles
 *  MONGO_COL       e.g. profiles
 *
 *  REDIS_HOST      e.g. xxx.upstash.io
 *  REDIS_PORT      e.g. 6379
 *  REDIS_PASS      e.g. your-redis-password
 *
 * For local development create a file called .env.php in this folder
 * and define the same constants there (see .env.php.example).
 */

/* ── Load local .env.php if it exists (local dev only) ─────────────────── */
$localEnv = __DIR__ . '/.env.php';
if (file_exists($localEnv)) {
    require_once $localEnv;
}

/* ── MySQL ──────────────────────────────────────────────────────────────── */
define('MYSQL_HOST', getenv('MYSQL_HOST') ?: 'localhost');
define('MYSQL_PORT', (int)(getenv('MYSQL_PORT') ?: 3306));
define('MYSQL_USER', getenv('MYSQL_USER') ?: 'root');
define('MYSQL_PASS', getenv('MYSQL_PASS') ?: '');
define('MYSQL_DB',   getenv('MYSQL_DB')   ?: 'guvi_auth');

/* ── MongoDB ────────────────────────────────────────────────────────────── */
define('MONGO_URI', getenv('MONGO_URI') ?: 'mongodb://localhost:27017');
define('MONGO_DB',  getenv('MONGO_DB')  ?: 'guvi_profiles');
define('MONGO_COL', getenv('MONGO_COL') ?: 'profiles');

/* ── Redis ──────────────────────────────────────────────────────────────── */
define('REDIS_HOST', getenv('REDIS_HOST') ?: '127.0.0.1');
define('REDIS_PORT', (int)(getenv('REDIS_PORT') ?: 6379));
define('REDIS_PASS', getenv('REDIS_PASS') ?: '');
define('SESSION_TTL', 3600);

/* ── Helper: MySQL connection ───────────────────────────────────────────── */
function getMysqlConnection(): mysqli {
    $conn = new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASS, MYSQL_DB, MYSQL_PORT);
    if ($conn->connect_error) {
        sendJson(['success' => false, 'message' => 'DB connection failed.'], 500);
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

/* ── Helper: MongoDB collection ─────────────────────────────────────────── */
function getMongoCollection(): MongoDB\Collection {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        sendJson(['success' => false, 'message' => 'Run: composer install'], 500);
    }
    require_once $autoload;
    $client = new MongoDB\Client(MONGO_URI);
    return $client->{MONGO_DB}->{MONGO_COL};
}

/* ── Helper: Redis connection ───────────────────────────────────────────── */
function getRedisConnection(): Predis\Client {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        sendJson(['success' => false, 'message' => 'Run: composer install'], 500);
    }
    require_once $autoload;

    $params = [
        'scheme' => 'tcp',
        'host'   => REDIS_HOST,
        'port'   => REDIS_PORT,
    ];
    if (REDIS_PASS !== '') {
        $params['password'] = REDIS_PASS;
        // Upstash requires TLS
        if (str_contains(REDIS_HOST, 'upstash.io')) {
            $params['scheme'] = 'tls';
        }
    }
    return new Predis\Client($params);
}

/* ── Helper: send JSON response ─────────────────────────────────────────── */
function sendJson(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/* ── Helper: extract Bearer token ───────────────────────────────────────── */
function getSessionToken(): ?string {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (str_starts_with($header, 'Bearer ')) {
        return trim(substr($header, 7));
    }
    return $_POST['session_token'] ?? $_GET['session_token'] ?? null;
}

/* ── Helper: verify Redis session → returns user_id or null ─────────────── */
function verifySession(): ?string {
    $token = getSessionToken();
    if (!$token) return null;
    $redis  = getRedisConnection();
    $userId = $redis->get('session:' . $token);
    return $userId ? (string) $userId : null;
}
