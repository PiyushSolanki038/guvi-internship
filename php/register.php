<?php
/**
 * register.php
 * Handles new user registration.
 *
 * Method : POST
 * Params : username, email, password
 * Storage: MySQL (credentials) — uses Prepared Statements only
 * Returns: JSON { success, message }
 */

// Allow cross-origin requests from same origin (AJAX calls from HTML pages)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/config.php';

// ── 1. Read & sanitize raw POST input ──────────────────────────────────────
$input    = json_decode(file_get_contents('php://input'), true) ?? [];
$username = trim($input['username'] ?? '');
$email    = trim($input['email']    ?? '');
$password = trim($input['password'] ?? '');

// ── 2. Server-side validation ───────────────────────────────────────────────
if ($username === '' || $email === '' || $password === '') {
    sendJson(['success' => false, 'message' => 'All fields are required.'], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJson(['success' => false, 'message' => 'Invalid email address.'], 400);
}

if (strlen($password) < 6) {
    sendJson(['success' => false, 'message' => 'Password must be at least 6 characters.'], 400);
}

// ── 3. Connect to MySQL ─────────────────────────────────────────────────────
$conn = getMysqlConnection();

// ── 4. Check if email already registered (Prepared Statement) ───────────────
$checkStmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
$checkStmt->bind_param('s', $email);
$checkStmt->execute();
$checkStmt->store_result();

if ($checkStmt->num_rows > 0) {
    $checkStmt->close();
    $conn->close();
    sendJson(['success' => false, 'message' => 'Email is already registered.'], 409);
}
$checkStmt->close();

// ── 5. Hash the password (never store plain-text) ───────────────────────────
$passwordHash = password_hash($password, PASSWORD_BCRYPT);

// ── 6. Insert new user (Prepared Statement) ─────────────────────────────────
$insertStmt = $conn->prepare(
    'INSERT INTO users (username, email, password_hash, created_at) VALUES (?, ?, ?, NOW())'
);
$insertStmt->bind_param('sss', $username, $email, $passwordHash);

if (!$insertStmt->execute()) {
    $insertStmt->close();
    $conn->close();
    sendJson(['success' => false, 'message' => 'Registration failed. Please try again.'], 500);
}

$insertStmt->close();
$conn->close();

// ── 7. Success response ─────────────────────────────────────────────────────
sendJson(['success' => true, 'message' => 'Registration successful! Please log in.']);
