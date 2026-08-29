<?php
// api/permissions.php - Menu & Role Access Permissions Management API
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
Auth::requireLogin();

$pdo = Database::getConnection();
$action = $_GET['action'] ?? 'get_all';

// Admin and Super Admin can view permissions
if ($action !== 'get_my_permissions') {
    Auth::requireAdmin();
}

// List of all system menus
$MENUS_CATALOG = [
    [
        'key' => 'dashboard',
        'label' => 'Dashboard',
        'icon' => 'dashboard',
        'description' => 'Ringkasan statistik stok, aktivitas operasional, dan grafik harian.'
    ],
    [
        'key' => 'inventory',
        'label' => 'Master Stok Packaging',
        'icon' => 'shelves',
        'description' => 'Katalog stok packaging, rumus stok akhir, kartu stok, dan upload Excel master.'
    ],
    [
        'key' => 'dynamic_count',
        'label' => 'Dynamic Count',
        'icon' => 'checklist',
        'description' => 'Penugasan hitung fisik siklus SKU dinamis dan live counting matrix.'
    ],
    [
        'key' => 'opname',
        'label' => 'Stock Opname & Recount',
        'icon' => 'fact_check',
        'description' => 'Penugasan hitung fisik (1st Count), recount operator, download hasil SO, dan penyesuaian stok (+/-).'
    ],
    [
        'key' => 'adjust',
        'label' => 'Adjustment Opname',
        'icon' => 'tune',
        'description' => 'Penyesuaian selisih stok manual atau upload file Excel hasil opname.'
    ],
    [
        'key' => 'counting_detail',
        'label' => 'Detail Stock Opname',
        'icon' => 'table_rows',
        'description' => 'Log detail riwayat hitung fisik Stock Opname per putaran (1st, 2nd, 3rd count), filter dokumen sesi, dan export Excel.'
    ],
    [
        'key' => 'inbound',
        'label' => 'Barang Masuk (Inbound)',
        'icon' => 'move_to_inbox',
        'description' => 'Pencatatan penerimaan barang masuk supplier, nomor PO, dan draft submission.'
    ],
    [
        'key' => 'outbound',
        'label' => 'Barang Keluar (Outbound)',
        'icon' => 'outbox',
        'description' => 'Pencatatan barang keluar manual dan monitoring pengeluaran material.'
    ],
    [
        'key' => 'tasks',
        'label' => 'Assign Task Operator',
        'icon' => 'assignment',
        'description' => 'Penugasan pengambilan material ke operator (single, multi-row, upload Excel).'
    ],
    [
        'key' => 'mutations',
        'label' => 'Audit Mutasi Stok',
        'icon' => 'history',
        'description' => 'Buku audit trail kronologis pergerakan stok keluar dan masuk.'
    ],
    [
        'key' => 'users',
        'label' => 'Manajemen User & Role',
        'icon' => 'manage_accounts',
        'description' => 'Pendaftaran akun, pengaturan role, dan reset password pengguna.'
    ],
    [
        'key' => 'permissions',
        'label' => 'Hak Akses Menu',
        'icon' => 'lock_person',
        'description' => 'Pengaturan akses menu dan otorisasi hak fitur per role atau user.'
    ],
    [
        'key' => 'field_access',
        'label' => 'Akses Lapangan (Mobile Panel)',
        'icon' => 'smartphone',
        'description' => 'Tautan langsung ke tampilan mobile operator lapangan (Draft Inbound & Task).'
    ]
];

// Helper: resolve user permission
function getResolvedPermissionsForUser($pdo, $userId, $role) {
    global $MENUS_CATALOG;
    
    // Get role-level defaults
    $stmtRole = $pdo->prepare("SELECT menu_key, is_allowed FROM menu_permissions WHERE role = ? AND user_id IS NULL");
    $stmtRole->execute([$role]);
    $rolePerms = $stmtRole->fetchAll(PDO::FETCH_KEY_PAIR);

    // Get user-level overrides
    $stmtUser = $pdo->prepare("SELECT menu_key, is_allowed FROM menu_permissions WHERE user_id = ?");
    $stmtUser->execute([$userId]);
    $userPerms = $stmtUser->fetchAll(PDO::FETCH_KEY_PAIR);

    $resolved = [];
    foreach ($MENUS_CATALOG as $m) {
        $key = $m['key'];
        if (isset($userPerms[$key])) {
            $resolved[$key] = (bool)$userPerms[$key];
        } elseif (isset($rolePerms[$key])) {
            $resolved[$key] = (bool)$rolePerms[$key];
        } else {
            if ($role === 'superadmin') {
                $resolved[$key] = true;
            } elseif ($role === 'admin') {
                $resolved[$key] = ($key !== 'field_access');
            } else {
                $resolved[$key] = ($key === 'field_access');
            }
        }
    }

    return $resolved;
}

// 1. GET MY PERMISSIONS (FOR SIDEBAR DYNAMIC VISIBILITY)
if ($action === 'get_my_permissions') {
    $currentUserId = Auth::id();
    $currentRole   = Auth::role();
    $currentUsername = Auth::username();

    // Super Admin has 100% permissions
    if (Auth::isSuperAdmin()) {
        $allAllowed = [];
        foreach ($MENUS_CATALOG as $m) {
            $allAllowed[$m['key']] = true;
        }
        echo json_encode([
            'success' => true,
            'is_super_admin' => true,
            'permissions' => $allAllowed
        ]);
        exit;
    }

    $perms = getResolvedPermissionsForUser($pdo, $currentUserId, $currentRole);
    echo json_encode([
        'success' => true,
        'is_super_admin' => false,
        'permissions' => $perms
    ]);
    exit;
}

// 2. GET ALL PERMISSION CONFIGURATIONS (ROLES + USERS + MATRIX)
if ($action === 'get_all') {
    // Fetch all users
    $stmtUsers = $pdo->query("SELECT id, username, name, role, shift FROM users ORDER BY (role = 'superadmin') DESC, (role = 'admin') DESC, name ASC");
    $users = $stmtUsers->fetchAll();

    // Fetch role permissions
    $stmtRolePerms = $pdo->query("SELECT role, menu_key, is_allowed FROM menu_permissions WHERE user_id IS NULL");
    $rolePermsRaw = $stmtRolePerms->fetchAll();
    $rolePerms = [];
    foreach ($rolePermsRaw as $rp) {
        $rolePerms[$rp['role']][$rp['menu_key']] = (bool)$rp['is_allowed'];
    }

    // Fetch user specific permissions
    $stmtUserPerms = $pdo->query("SELECT user_id, menu_key, is_allowed FROM menu_permissions WHERE user_id IS NOT NULL");
    $userPermsRaw = $stmtUserPerms->fetchAll();
    $userPerms = [];
    foreach ($userPermsRaw as $up) {
        $userPerms[$up['user_id']][$up['menu_key']] = (bool)$up['is_allowed'];
    }

    // Calculate resolved permissions for each user
    $resolvedUsers = [];
    foreach ($users as $u) {
        $isSuper = ($u['role'] === 'superadmin' || $u['username'] === 'Daniel');
        $resolved = $isSuper ? array_fill_keys(array_column($MENUS_CATALOG, 'key'), true) : getResolvedPermissionsForUser($pdo, $u['id'], $u['role']);
        $hasCustom = isset($userPerms[$u['id']]);

        $resolvedUsers[] = [
            'id' => (int)$u['id'],
            'username' => $u['username'],
            'name' => $u['name'],
            'role' => $u['role'],
            'shift' => $u['shift'],
            'is_super_admin' => $isSuper,
            'has_custom_override' => $hasCustom,
            'permissions' => $resolved
        ];
    }

    echo json_encode([
        'success' => true,
        'catalog' => $MENUS_CATALOG,
        'roles' => ['superadmin', 'admin', 'operator'],
        'role_permissions' => $rolePerms,
        'users' => $resolvedUsers
    ]);
    exit;
}

// 3. SAVE ROLE PERMISSIONS
if ($action === 'save_role') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $role = trim($input['role'] ?? '');
    $permissions = $input['permissions'] ?? [];

    if (!in_array($role, ['superadmin', 'admin', 'operator'])) {
        echo json_encode(['success' => false, 'message' => 'Role tidak valid!']);
        exit;
    }

    $stmtUpsert = $pdo->prepare("
        INSERT INTO menu_permissions (role, user_id, menu_key, is_allowed)
        VALUES (?, NULL, ?, ?)
        ON DUPLICATE KEY UPDATE is_allowed = VALUES(is_allowed), updated_at = NOW()
    ");

    $pdo->beginTransaction();
    foreach ($permissions as $menuKey => $isAllowed) {
        $val = $isAllowed ? 1 : 0;
        $stmtUpsert->execute([$role, $menuKey, $val]);
    }
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => "Hak akses untuk role '{$role}' berhasil diperbarui!"
    ]);
    exit;
}

// 4. SAVE USER SPECIFIC OVERRIDE PERMISSIONS
if ($action === 'save_user') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $userId = (int)($input['user_id'] ?? 0);
    $permissions = $input['permissions'] ?? [];

    $stmtUser = $pdo->prepare("SELECT id, username, role, name FROM users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $user = $stmtUser->fetch();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Pengguna tidak ditemukan!']);
        exit;
    }

    if ($user['username'] === 'Daniel') {
        echo json_encode(['success' => false, 'message' => 'Hak akses Super Admin Daniel tidak dapat dibatasi.']);
        exit;
    }

    $stmtUpsert = $pdo->prepare("
        INSERT INTO menu_permissions (role, user_id, menu_key, is_allowed)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE is_allowed = VALUES(is_allowed), updated_at = NOW()
    ");

    $pdo->beginTransaction();
    foreach ($permissions as $menuKey => $isAllowed) {
        $val = $isAllowed ? 1 : 0;
        $stmtUpsert->execute([$user['role'], $userId, $menuKey, $val]);
    }
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => "Hak akses khusus untuk pengguna '{$user['name']}' berhasil disimpan!"
    ]);
    exit;
}

// 5. RESET USER PERMISSION (REVERT TO ROLE DEFAULT)
if ($action === 'reset_user') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $userId = (int)($input['user_id'] ?? 0);

    $stmtDel = $pdo->prepare("DELETE FROM menu_permissions WHERE user_id = ?");
    $stmtDel->execute([$userId]);

    echo json_encode([
        'success' => true,
        'message' => 'Hak akses pengguna berhasil dikembalikan ke standar Role.'
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Aksi tidak valid!']);
