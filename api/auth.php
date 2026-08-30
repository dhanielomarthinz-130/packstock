<?php
// api/auth.php - Authentication API (Login, Logout, Session check)
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $username = trim($input['username'] ?? '');
    $password = trim($input['password'] ?? '');
    $shift = trim($input['shift'] ?? '');

    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Username dan password wajib diisi!']);
        exit;
    }

    $res = Auth::login($username, $password, $shift);
    echo json_encode($res);
    exit;
}

if ($action === 'logout') {
    Auth::logout();
    echo json_encode(['success' => true, 'message' => 'Berhasil logout']);
    exit;
}

if ($action === 'me') {
    if (Auth::check()) {
        echo json_encode(['success' => true, 'user' => Auth::user()]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Belum login']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update_profile') {
    Auth::requireLogin();
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $name = trim($input['name'] ?? '');
    $currentPassword = trim($input['current_password'] ?? '');
    $newPassword = trim($input['new_password'] ?? '');
    $confirmPassword = trim($input['confirm_password'] ?? '');

    $userId = Auth::id();
    $pdo = Database::getConnection();

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $currentUser = $stmt->fetch();

    if (!$currentUser) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pengguna tidak ditemukan']);
        exit;
    }

    if (empty($name)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nama lengkap tidak boleh kosong!']);
        exit;
    }

    // If user wants to change password
    if (!empty($newPassword)) {
        if (empty($currentPassword)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Password saat ini (lama) wajib diisi untuk verifikasi!']);
            exit;
        }

        // Verify current password
        $isCurrentValid = password_verify($currentPassword, $currentUser['password']);
        if (!$isCurrentValid && $currentPassword === $currentUser['password']) {
            $isCurrentValid = true;
        }

        if (!$isCurrentValid) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Password saat ini (lama) tidak sesuai!']);
            exit;
        }

        if (strlen($newPassword) < 5) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Password baru minimal 5 karakter!']);
            exit;
        }

        if ($newPassword !== $confirmPassword) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Konfirmasi password baru tidak cocok!']);
            exit;
        }

        $newHashed = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmtUpdate = $pdo->prepare("UPDATE users SET name = ?, password = ? WHERE id = ?");
        $stmtUpdate->execute([$name, $newHashed, $userId]);
    } else {
        $stmtUpdate = $pdo->prepare("UPDATE users SET name = ? WHERE id = ?");
        $stmtUpdate->execute([$name, $userId]);
    }

    // Refresh session user name
    $_SESSION['user']['name'] = $name;

    echo json_encode([
        'success' => true,
        'message' => 'Profil dan password berhasil diperbarui!',
        'user' => [
            'id' => $userId,
            'username' => $currentUser['username'],
            'name' => $name,
            'role' => $currentUser['role']
        ]
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Aksi tidak valid']);
