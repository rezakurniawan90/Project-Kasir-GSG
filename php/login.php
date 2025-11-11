<?php
require_once 'db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        echo json_encode([
            'success' => false,
            'message' => 'Username dan password harus diisi!'
        ]);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id_user AS id, username, password FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        
        if ($admin && password_verify($password, $admin['password'])) {
            // Start session
            session_start();
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['logged_in'] = true;
            
            echo json_encode([
                'success' => true,
                'message' => 'Login berhasil!',
                'username' => $admin['username']
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Username atau password salah!'
            ]);
        }
        
    } catch(PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
}
?>
