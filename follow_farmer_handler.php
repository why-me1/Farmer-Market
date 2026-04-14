<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/discovery.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Login required', 'login_required' => true]);
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';
$farmer_id = (int)($_POST['farmer_id'] ?? $_GET['farmer_id'] ?? 0);

if ($farmer_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid farmer']);
    exit();
}

if ($user_id === $farmer_id) {
    echo json_encode(['success' => false, 'message' => 'You cannot follow yourself']);
    exit();
}

$stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'farmer' LIMIT 1");
$stmt->bind_param("i", $farmer_id);
$stmt->execute();
$farmer_exists = $stmt->get_result()->num_rows > 0;
$stmt->close();

if (!$farmer_exists) {
    echo json_encode(['success' => false, 'message' => 'Farmer not found']);
    exit();
}

$result = discoveryToggleFarmerFollow($user_id, $farmer_id);

echo json_encode([
    'success' => true,
    'following' => $result['following'],
    'followers' => $result['followers'],
    'message' => $result['following'] ? 'Farmer added to favorites' : 'Farmer removed from favorites'
]);
