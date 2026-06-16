<?php
/**
 * profile.php
 * Handles reading and updating user profile details.
 *
 * GET  → fetch profile   (session_token required)
 * POST → update profile  (session_token required)
 *
 * Session validation: Redis token lookup
 * Profile storage   : MongoDB (age, dob, contact, bio, avatar_url, address)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

require_once __DIR__ . '/config.php';

// ── 1. Validate session via Redis ───────────────────────────────────────────
$userId = verifySession();
if (!$userId) {
    sendJson(['success' => false, 'message' => 'Unauthorized. Please log in.'], 401);
}

$method = $_SERVER['REQUEST_METHOD'];

// ──────────────────────────────────────────────────────────────────────────────
// GET  →  Fetch profile from MongoDB
// ──────────────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $collection = getMongoCollection();

    // Find profile document by user_id (string match)
    $profile = $collection->findOne(['user_id' => $userId]);

    if (!$profile) {
        // Return empty profile skeleton if not yet created
        sendJson([
            'success' => true,
            'profile' => [
                'user_id' => $userId,
                'age'     => '',
                'dob'     => '',
                'contact' => '',
                'address' => '',
                'bio'     => '',
                'avatar'  => '',
            ],
        ]);
    }

    // Convert MongoDB document to plain PHP array
    $profileArray = [
        'user_id' => $userId,
        'age'     => $profile['age']     ?? '',
        'dob'     => $profile['dob']     ?? '',
        'contact' => $profile['contact'] ?? '',
        'address' => $profile['address'] ?? '',
        'bio'     => $profile['bio']     ?? '',
        'avatar'  => $profile['avatar']  ?? '',
    ];

    sendJson(['success' => true, 'profile' => $profileArray]);
}

// ──────────────────────────────────────────────────────────────────────────────
// POST →  Update / insert profile in MongoDB
// ──────────────────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    // Validate & sanitize each profile field
    $age     = trim($input['age']     ?? '');
    $dob     = trim($input['dob']     ?? '');
    $contact = trim($input['contact'] ?? '');
    $address = trim($input['address'] ?? '');
    $bio     = trim($input['bio']     ?? '');
    $avatar  = trim($input['avatar']  ?? '');

    // Basic field validation
    if ($age !== '' && (!is_numeric($age) || (int)$age < 1 || (int)$age > 120)) {
        sendJson(['success' => false, 'message' => 'Enter a valid age (1–120).'], 400);
    }

    if ($contact !== '' && !preg_match('/^\+?[\d\s\-]{7,15}$/', $contact)) {
        sendJson(['success' => false, 'message' => 'Enter a valid contact number.'], 400);
    }

    // Build update document
    $updateDoc = [
        'user_id'    => $userId,
        'age'        => $age,
        'dob'        => $dob,
        'contact'    => $contact,
        'address'    => $address,
        'bio'        => $bio,
        'avatar'     => $avatar,
        'updated_at' => new MongoDB\BSON\UTCDateTime(),
    ];

    $collection = getMongoCollection();

    // Upsert: update if exists, insert if not
    $result = $collection->updateOne(
        ['user_id' => $userId],
        ['$set'    => $updateDoc],
        ['upsert'  => true]
    );

    sendJson(['success' => true, 'message' => 'Profile updated successfully.']);
}

// Method not supported
sendJson(['success' => false, 'message' => 'Method not allowed.'], 405);
