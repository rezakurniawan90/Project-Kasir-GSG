<?php
session_start();

header('Content-Type: application/json');

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    echo json_encode([
        'success' => true,
        'logged_in' => true,
        'username' => $_SESSION['admin_username'] ?? 'Admin'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'logged_in' => false
    ]);
}
?>
