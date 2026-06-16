<?php
/**
 * login.php
 * Authenticates a registered user.
 *
 * Method : POST
 * Params : email, password
 * Storage: MySQL lookup (Prepared Statement)
 *          Redis session token storage
 * Returns: JSON { success, message, session_token, username }
 *
 * The session_token is stored in:
 *  - Redis  (backend)  → key "session:<token>" = user_id  (TTL: 1 hour)
 *  - Browser localStorage (frontend) — handled in login.js
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/config.php';

// ── 1. Read POST body ───────────────────────────────────────────────────────
$input    = json_decode(file_get_contents('php://input'), true) ?? [];
$email    = trim($input['email']    ?? '');
$password = trim($input['password'] ?? '');

// ── 2. Basic validation ─────────────────────────────────────────────────────
if ($email === '' || $password === '') {
    sendJson(['success' => false, 'message' => 'Email and password are required.'], 400);
}

// ── 3. Fetch user from MySQL (Prepared Statement) ───────────────────────────
$conn = getMysqlConnection();
$stmt = $conn->prepare('SELECT id, username, password_hash FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->bind_result($userId, $username, $passwordHash);
$stmt->fetch();
$stmt->close();
$conn->close();

// ── 4. Verify credentials ───────────────────────────────────────────────────
if (!$userId || !password_verify($password, $passwordHash)) {
    sendJson(['success' => false, 'message' => 'Invalid email or password.'], 401);
}

// ── 5. Generate a cryptographically secure session token ────────────────────
$sessionToken = bin2hex(random_bytes(32)); // 64-character hex string

// ── 6. Store token in Redis (backend session store) ─────────────────────────
$redis = getRedisConnection();
$redis->setex('session:' . $sessionToken, SESSION_TTL, (string) $userId);

// ── 7. Return token to frontend (will be saved in localStorage) ─────────────
sendJson([
    'success'       => true,
    'message'       => 'Login successful.',
    'session_token' => $sessionToken,
    'username'      => $username,
    'user_id'       => (string) $userId,
]);
