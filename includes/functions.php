<?php
function check_login()
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . 'index.php?auth=login');
        exit();
    }
}

function sanitize($data)
{
    return htmlspecialchars(stripslashes(trim($data)));
}
