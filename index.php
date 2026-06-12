<?php
require_once 'config.php';
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('admin.php');
    } else {
        redirect('system.php');
    }
} else {
    redirect('auth.php?action=login');
}
?>