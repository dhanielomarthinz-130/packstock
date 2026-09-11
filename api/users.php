<?php
// api/users.php - User & Operator Management API
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();
$pdo = Database::getConnection();
$action = $_GET['action'] ?? 'operators';

// 1. GET OPERATORS LIST (For task dropdown)
if ($action === 'operators') {
    $stmt = $pdo->query("SELECT id, username, name, role, shift FROM users WHERE role IN ('operator', 'operator_inventory', 'operator_fulfillment') OR role LIKE 'operator%' ORDER BY name ASC");
    $operators = $stmt->fetchAll();
    echo json_encode(['success' => true, 'data' => $operators]);
    exit;
}

// 2. LIST ALL USERS (Admin only - Super Admin hidden from regular Admin)
if ($action === 'list') {
    Auth::requireAdmin();
    $search = trim($_GET['search'] ?? '');
    $roleFilter = trim($_GET['role'] ?? 'all');

    $query = "SELECT id, username, name, role, shift, created_at FROM users WHERE 1=1";
    $params = [];

    // Sembunyikan akun Super Admin secara penuh dari Administrator biasa
    if (!Auth::isSuperAdmin()) {
        $query .= " AND role NOT IN ('superadmin', 'teknisi')";
    }

    if (!empty($search)) {
        $query .= " AND (username LIKE ? OR name LIKE ? OR shift LIKE ?)";
        $term = "%{$search}%";
        $params = [$term, $term, $term];
    }

    if (!empty($roleFilter) && $roleFilter !== 'all') {
        $query .= " AND role = ?";
        $params[] = $roleFilter;
    }

    $query .= " ORDER BY (role = 'superadmin') DESC, (role = 'admin') DESC, name ASC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll();

    // Tandai akun yang masih memakai password bawaan hasil migrasi.
    // Password bawaan tidak pernah kedaluwarsa dengan sendirinya, dan tanpa
    // ditampilkan begini ia bisa bertahan diam-diam sampai bertahun-tahun.
    $passwordBawaan = ['admin' => 'admin123', 'operator1' => 'op123', 'operator2' => 'op123', 'Daniel' => 'Password01'];
    $stmtHash = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $jumlahBawaan = 0;

    foreach ($users as &$u) {
        $u['uses_default_password'] = false;
        $tebakan = $passwordBawaan[$u['username']] ?? null;
        if ($tebakan !== null) {
            $stmtHash->execute([$u['id']]);
            $hash = $stmtHash->fetchColumn();
            if ($hash && password_verify($tebakan, $hash)) {
                $u['uses_default_password'] = true;
                $jumlahBawaan++;
            }
        }
    }
    unset($u);

    echo json_encode([
        'success' => true,
        'data' => $users,
        'default_password_count' => $jumlahBawaan,
        'default_password_warning' => $jumlahBawaan > 0
            ? "{$jumlahBawaan} akun masih memakai password bawaan sistem. Password bawaan diketahui umum — segera ganti sebelum sistem dipakai di lapangan."
            : null
    ]);
    exit;
}

// 3. GET SINGLE USER (Admin only)
if ($action === 'get') {
    Auth::requireAdmin();
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT id, username, name, role, shift FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $u = $stmt->fetch();

    if ($u) {
        // Cegah admin biasa mengakses detail superadmin
        if (!Auth::isSuperAdmin() && in_array($u['role'], ['superadmin', 'teknisi'], true)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
            exit;
        }
        echo json_encode(['success' => true, 'data' => $u]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pengguna tidak ditemukan']);
    }
    exit;
}

// 4. CREATE NEW USER (Admin only)
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireAdmin();
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $username = trim($input['username'] ?? '');
    $password = trim($input['password'] ?? '');
    $name     = trim($input['name'] ?? '');
    $role     = trim($input['role'] ?? 'operator');
    $shift    = trim($input['shift'] ?? 'Gudang & Logistik');

    if (empty($username) || empty($password) || empty($name)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Username, Password, dan Nama Lengkap wajib diisi!']);
        exit;
    }

    if ($pwError = validatePasswordStrength($password, $username)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $pwError]);
        exit;
    }

    // Larang keras Administrator biasa mendaftarkan user sebagai Super Admin atau Teknisi
    if (($role === 'superadmin' || $role === 'teknisi') && !Auth::isSuperAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Akses ditolak! Anda tidak memiliki wewenang untuk menetapkan role Teknisi atau Super Administrator.']);
        exit;
    }

    if (!in_array($role, ['superadmin', 'admin', 'operator', 'operator_inventory', 'operator_fulfillment', 'teknisi'])) {
        $role = 'operator_inventory';
    }
    if ($role === 'operator') {
        $role = 'operator_inventory';
    }

    // Check duplicate username
    $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmtCheck->execute([$username]);
    if ($stmtCheck->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Username '{$username}' sudah digunakan! Silakan gunakan username lain."]);
        exit;
    }

    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, password, name, role, shift) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$username, $hashedPassword, $name, $role, $shift]);
        $newId = (int)$pdo->lastInsertId();

        Auth::audit('USER_CREATE', $username, "Nama {$name}, role {$role}, shift {$shift}");

        echo json_encode([
            'success' => true,
            'message' => "User {$name} ({$username}) dengan role {$role} berhasil ditambahkan!",
            'id' => $newId
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        apiFail($e, 'Gagal menambahkan user.');
    }
    exit;
}

// 5. UPDATE USER (Admin only)
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireAdmin();
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $id       = (int)($input['id'] ?? 0);
    $username = trim($input['username'] ?? '');
    $name     = trim($input['name'] ?? '');
    $role     = trim($input['role'] ?? 'operator_inventory');
    $shift    = trim($input['shift'] ?? 'Gudang & Logistik');
    $password = trim($input['password'] ?? '');

    if ($id <= 0 || empty($username) || empty($name)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
        exit;
    }

    // Check target user existing role
    // username ikut diambil: tanpa itu penjaga "target adalah akun Daniel" di bawah
    // tidak pernah aktif karena $targetUser['username'] selalu kosong.
    $stmtTarget = $pdo->prepare("SELECT username, name, role FROM users WHERE id = ?");
    $stmtTarget->execute([$id]);
    $targetUser = $stmtTarget->fetch();

    if (!$targetUser) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pengguna tidak ditemukan']);
        exit;
    }

    // If target is superadmin and logged-in user is not superadmin, deny
    if (in_array($targetUser['role'], ['superadmin', 'teknisi'], true) && !Auth::isSuperAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Hanya Super Admin yang dapat mengubah akun Super Admin!']);
        exit;
    }

    if (!in_array($role, ['superadmin', 'admin', 'operator', 'operator_inventory', 'operator_fulfillment', 'teknisi'])) {
        $role = 'operator_inventory';
    }
    if ($role === 'operator') {
        $role = 'operator_inventory';
    }

    // Non-superadmin cannot escalate role to superadmin or teknisi
    if (($role === 'superadmin' || $role === 'teknisi') && !Auth::isSuperAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Akses ditolak! Anda tidak dapat menetapkan role Teknisi atau Super Administrator.']);
        exit;
    }

    // Check duplicate username excluding current
    $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmtCheck->execute([$username, $id]);
    if ($stmtCheck->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Username '{$username}' sudah digunakan oleh akun lain!"]);
        exit;
    }

    if (!empty($password) && ($pwError = validatePasswordStrength($password, $username))) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $pwError]);
        exit;
    }

    try {
        if (!empty($password)) {
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, name = ?, role = ?, shift = ? WHERE id = ?");
            $stmt->execute([$username, $hashedPassword, $name, $role, $shift, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET username = ?, name = ?, role = ?, shift = ? WHERE id = ?");
            $stmt->execute([$username, $name, $role, $shift, $id]);
        }

        $roleLama = $targetUser['role'] ?? '-';
        $detail = "Role {$roleLama} → {$role}, shift {$shift}" . (!empty($password) ? ', password diganti' : '');
        Auth::audit('USER_UPDATE', $username, $detail);

        echo json_encode(['success' => true, 'message' => "Data user {$name} berhasil diperbarui!"]);
    } catch (Exception $e) {
        http_response_code(500);
        apiFail($e, 'Gagal memperbarui user.');
    }
    exit;
}

// 6. DELETE USER (Admin only)
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireAdmin();
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $id = (int)($input['id'] ?? 0);

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID user tidak valid']);
        exit;
    }

    if ($id === Auth::id()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Anda tidak dapat menghapus akun Anda sendiri yang sedang login!']);
        exit;
    }

    // Check if target user is Super Admin
    $stmtCheck = $pdo->prepare("SELECT username, name, role FROM users WHERE id = ?");
    $stmtCheck->execute([$id]);
    $targetUser = $stmtCheck->fetch();

    if (!$targetUser) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pengguna tidak ditemukan']);
        exit;
    }

    if ($targetUser['role'] === 'superadmin' && !Auth::isSuperAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Hanya Super Admin yang dapat menghapus akun Super Admin!']);
        exit;
    }

    // Jangan sampai sistem kehilangan seluruh Super Admin — pintu masuk terakhir
    // ke menu maintenance, reset, dan manajemen user akan tertutup permanen.
    if (in_array($targetUser['role'], ['superadmin', 'teknisi'], true)) {
        $stmtSisa = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role IN ('superadmin', 'teknisi') AND id != ?");
        $stmtSisa->execute([$id]);
        if ((int)$stmtSisa->fetchColumn() === 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Akun Super Admin terakhir tidak dapat dihapus. Buat Super Admin pengganti terlebih dahulu.']);
            exit;
        }
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);

        Auth::audit('USER_DELETE', $targetUser['username'] ?? (string)$id, "Role {$targetUser['role']}, nama {$targetUser['name']}");

        echo json_encode(['success' => true, 'message' => 'User berhasil dihapus']);
    } catch (Exception $e) {
        http_response_code(500);
        apiFail($e, 'Gagal menghapus user.');
    }
    exit;
}

// 7. GET MY PROFILE (Logged-in user)
if ($action === 'my_profile') {
    $myId = Auth::id();
    $stmt = $pdo->prepare("SELECT id, username, name, role, shift, created_at FROM users WHERE id = ?");
    $stmt->execute([$myId]);
    $u = $stmt->fetch();

    if ($u) {
        echo json_encode(['success' => true, 'data' => $u]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pengguna tidak ditemukan']);
    }
    exit;
}

// 8. UPDATE MY PASSWORD (Logged-in user)
if ($action === 'update_my_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $myId = Auth::id();

    $oldPassword = trim($input['old_password'] ?? '');
    $newPassword = trim($input['new_password'] ?? '');

    if (empty($oldPassword) || empty($newPassword)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Password lama dan password baru wajib diisi!']);
        exit;
    }

    if ($pwError = validatePasswordStrength($newPassword, Auth::username() ?? '')) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $pwError]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$myId]);
    $userRow = $stmt->fetch();

    if (!$userRow || !password_verify($oldPassword, $userRow['password'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Password lama yang Anda masukkan tidak sesuai!']);
        exit;
    }

    $newHashed = password_hash($newPassword, PASSWORD_BCRYPT);
    $stmtUpdate = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmtUpdate->execute([$newHashed, $myId]);

    Auth::audit('PASSWORD_CHANGE_SELF', Auth::username());

    echo json_encode(['success' => true, 'message' => 'Password Anda berhasil diperbarui!']);
    exit;
}

// 9. UPDATE MY ACTIVE SHIFT (Self-service rolling shift by operator / any user)
if ($action === 'update_my_shift') {
    $myId = (int)Auth::id();
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $newShift = trim($input['shift'] ?? '');

    if (empty($newShift)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Pilih shift kerja aktif terlebih dahulu!']);
        exit;
    }

    $stmtUpdate = $pdo->prepare("UPDATE users SET shift = ? WHERE id = ?");
    $stmtUpdate->execute([$newShift, $myId]);

    // Update Session
    if (isset($_SESSION['user'])) {
        $_SESSION['user']['shift'] = $newShift;
    }

    echo json_encode([
        'success' => true, 
        'message' => 'Shift aktif berhasil diperbarui ke ' . $newShift,
        'shift' => $newShift
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Aksi user tidak valid']);

