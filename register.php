<?php
require_once 'includes/config.php';
// Standalone register page removed - authentication uses the modal
header('Location: ' . BASE_URL . 'index.php?auth=signup');
exit();