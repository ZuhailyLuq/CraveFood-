<?php
session_start();
include('db.php');
include('achievement_helpers.php');

header('Content-Type: application/json');

if (!isset($_SESSION['UserID'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit();
}

$userId = (int)$_SESSION['UserID'];
$action = $_POST['action'] ?? '';

if ($action === 'claim') {
    $achievementId = (int)($_POST['achievement_id'] ?? 0);
    $result = claimAchievement($conn, $userId, $achievementId);
    echo json_encode($result);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']);
