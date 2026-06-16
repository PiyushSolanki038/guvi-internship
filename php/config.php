<?php
/**
 * config.php
 * Central configuration file for all database connections:
 *  - MySQL  : stores registration credentials
 *  - MongoDB: stores user profile details
 *  - Redis  : stores backend session tokens
 *
 * IMPORTANT: Adjust the constants below to match your local environment.
 */

/* ─────────────────────────────────────────────
   MySQL Configuration
   Used for: user registration & login lookup
───────────────────────────────────────────── */
define('MYSQL_HOST', 'localhost');
define('MYSQL_PORT', 3306);
define('MYSQL_USER', 'root');
define('MYSQL_PASS', '');          // change to your MySQL password
define('MYSQL_DB',   'guvi_auth');

/* ─────────────────────────────────────────────
   MongoDB Configuration
   Used for: storing & updating user profile details
───────────────────────────────────────────── */
define('MONGO_URI', 'mongodb://localhost:27017');
define('MONGO_DB',  'guvi_profiles');
define('MONGO_COL', 'profiles');

/* ─────────────────────────────────────────────
   Redis Configuration
   Used for: backend session token storage
───────────────────────────────────────────── */
define('REDIS_HOST', '127.0.0.1');
define('REDIS_PORT', 6379);
define('REDIS_PASS', '');          // leave empty if no Redis password
define('SESSION_TTL', 3600);       // session lifetime in seconds (1 hour)

/* ─────────────────────────────────────────────
   Helper: Get MySQL connection (MySQLi)
───────────────────────────────────────────── */
function getMysqlConnection(): mysqli {
    $conn = new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASS, MYSQL_DB, MYSQL_PORT);
    if ($conn->connect_error) {
        http_response_code(500);
        die(json_encode(['success' => false, 'message' => 'MySQL connection failed: ' . $conn->connect_error]));
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

/* ─────────────────────────────────────────────
   Helper: Get MongoDB collection
   Requires: mongodb/mongodb composer package
   Install : composer require mongodb/mongodb
───────────────────────────────────────────── */
function getMongoCollection(): MongoDB\Collection {
    // Autoload Composer dependencies (MongoDB driver)
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        http_response_code(500);
        die(json_encode(['success' => false, 'message' => 'Composer autoload not found. Run: composer require mongodb/mongodb']));
    }
    require_once $autoload;

    $client = new MongoDB\Client(MONGO_URI);
    return $client->{MONGO_DB}->{MONGO_COL};
}

/* ─────────────────────────────────────────────
   Helper: Get Redis connection
   Requires: predis/predis composer package OR
             phpredis PHP extension
   Install : composer require predis/predis
───────────────────────────────────────────── */
function getRedisConnection(): Predis\Client {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        http_response_code(500);
        die(json_encode(['success' => false, 'message' => 'Composer autoload not found. Run: composer require predis/predis']));
    }
    require_once $autoload;

    $params = [
        'scheme' => 'tcp',
        'host'   => REDIS_HOST,
        'port'   => REDIS_PORT,
    ];
    if (REDIS_PASS !== '') {
        $params['password'] = REDIS_PASS;
    }
    return new Predis\Client($params);
}

/* ─────────────────────────────────────────────
   Helper: Shared JSON response sender
───────────────────────────────────────────── */
function sendJson(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/* ─────────────────────────────────────────────
   Helper: Validate & return Bearer token from
           Authorization header or POST body
───────────────────────────────────────────── */
function getSessionToken(): ?string {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (str_starts_with($header, 'Bearer ')) {
        return trim(substr($header, 7));
    }
    return $_POST['session_token'] ?? $_GET['session_token'] ?? null;
}

/* ─────────────────────────────────────────────
   Helper: Verify Redis session token →
           returns user_id string or null
───────────────────────────────────────────── */
function verifySession(): ?string {
    $token = getSessionToken();
    if (!$token) return null;

    $redis  = getRedisConnection();
    $userId = $redis->get('session:' . $token);
    return $userId ? (string) $userId : null;
}
