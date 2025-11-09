<?php
function check_login()
{
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}

function sanitize($data)
{
    return htmlspecialchars(stripslashes(trim($data)));
}

