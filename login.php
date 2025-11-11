<<<<<<< HEAD
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
=======
<?php
session_start();
// Pastikan path ini benar
require_once 'php/db_connect.php'; 

// Jika sudah login, arahkan ke index
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: index.php');
    exit();
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password']; // Password asli (plain text)

    if (empty($username) || empty($password)) {
        $error_message = 'Username dan password harus diisi!';
    } else {
        try {
            // Mengambil dari tabel 'admin' dan kolom 'id_user'
            $stmt = $pdo->prepare("SELECT id_user, username, password FROM admin WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            // Perbaikan: Kita trim() password dari DB untuk hapus spasi
            if ($admin && $password === trim($admin['password'])) {
                
                // Login Berhasil!
                $_SESSION['admin_id'] = $admin['id_user']; 
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['logged_in'] = true;

                header('Location: index.php');
                exit();
            } else {
                $error_message = 'Username atau password salah!';
            }
        } catch (PDOException $e) {
            $error_message = 'Error Query: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id" class="h-full bg-gray-100">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Kasir GSG</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>

<body class="flex items-center justify-center min-h-screen p-3 bg-gray-100 text-sm">
    <div class="w-full max-w-sm">
        <div class="bg-white rounded-lg shadow-md p-6 border border-gray-200">
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-12 h-12 bg-blue-600 text-white rounded-full mb-3">
                    <i class="fas fa-user-shield text-lg"></i>
                </div>
                <h2 class="text-lg font-semibold text-gray-800">Login Admin</h2>
                <p class="text-xs text-gray-500">Kasir GSG</p>
            </div>

            <?php if ($error_message): ?>
                <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded text-xs flex items-center gap-1.5">
                    <i class="fas fa-exclamation-circle text-sm"></i>
                    <span><?= htmlspecialchars($error_message) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="space-y-4">
                <div>
                    <label for="username" class="block text-xs font-medium text-gray-600 mb-1.5">
                        <i class="fas fa-user text-xs mr-1"></i> Username
                    </label>
                    <input type="text" id="username" name="username" required
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200 outline-none placeholder-gray-400"
                        placeholder="username">
                </div>

                <div>
                    <label for="password" class="block text-xs font-medium text-gray-600 mb-1.5">
                        <i class="fas fa-lock text-xs mr-1"></i> Password
                    </label>
                    <input type="password" id="password" name="password" required
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:ring-|2 focus:ring-blue-500 focus:border-transparent transition duration-200 outline-none placeholder-gray-400"
                        placeholder="password">
                </div>

                <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm py-2.5 px-4 rounded transition duration-200 flex items-center justify-center gap-1.5 shadow-sm hover:shadow">
                    <i class="fas fa-sign-in-alt text-sm"></i> Login
                </button>
            </form>
        </div>

        <p class="text-center text-xs text-gray-400 mt-5">
            © <?= date('Y') ?> Kantin GSG
        </p>
    </div>
</body>

</html>

>>>>>>> d01dc79 (Initial commit)
