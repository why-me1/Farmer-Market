<?php
session_start();
require_once __DIR__ . '/includes/config.php';
// Standalone login page removed - use the modal on the main site
$tab = (isset($_GET['register']) && $_GET['register'] === 'success') ? 'login' : ($_GET['tab'] ?? 'login');
header('Location: ' . BASE_URL . 'index.php?auth=' . ($tab === 'signup' ? 'signup' : 'login'));
exit();
