<?php
// api/handovers.php - Shift Handover & Tasks Serah Terima API
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();
$pdo = Database::getConnection();
$action = $_GET['action'] ?? 'list';
$currentUser = Auth::user();

// 1. LIST HANDOVERS
if ($action === 'list') {
    try {
        $today = date('Y-m-d');
        $isAdmin = in_array($currentUser['role'] ?? '', ['admin', 'superadmin', 'teknisi']);
        $showAll = isset($_GET['all']) && $_GET['all'] === '1';

        if ($isAdmin || $showAll) {
            $stmt = $pdo->query("
                SELECT h.*, 
                       COALESCE(NULLIF(h.from_shift, ''), u1.shift) as from_user_shift,
                       COALESCE(NULLIF(h.receiver_shift, ''), u2.shift) as receiver_user_shift,
                       u1.name as from_user_name,
                       u2.name as received_by_name
                FROM handovers h
                LEFT JOIN users u1 ON h.from_user_id = u1.id
                LEFT JOIN users u2 ON h.received_by = u2.id
                ORDER BY h.id DESC
            ");
        } else {
            $stmt = $pdo->prepare("
                SELECT h.*, 
                       COALESCE(NULLIF(h.from_shift, ''), u1.shift) as from_user_shift,
                       COALESCE(NULLIF(h.receiver_shift, ''), u2.shift) as receiver_user_shift,
                       u1.name as from_user_name,
                       u2.name as received_by_name
                FROM handovers h
                LEFT JOIN users u1 ON h.from_user_id = u1.id
                LEFT JOIN users u2 ON h.received_by = u2.id
                WHERE h.status = 'PENDING' OR DATE(h.created_at) = ?
                ORDER BY h.id DESC
            ");
            $stmt->execute([$today]);
        }
        $handovers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $handovers]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// 2. SUBMIT HANDOVER
if ($action === 'submit') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
        exit;
    }

    $toShift = trim($_POST['to_shift'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (empty($toShift)) {
        echo json_encode(['success' => false, 'message' => 'Shift tujuan harus diisi.']);
        exit;
    }

    $fromShift = trim($_POST['from_shift'] ?? '') ?: ($currentUser['shift'] ?? 'Shift 1 (Pagi 08:00 - 16:00)');
    if (!empty($fromShift) && $fromShift !== ($currentUser['shift'] ?? '')) {
        $stmtUp = $pdo->prepare("UPDATE users SET shift = ? WHERE id = ?");
        $stmtUp->execute([$fromShift, $currentUser['id']]);
        if (isset($_SESSION['user'])) {
            $_SESSION['user']['shift'] = $fromShift;
        }
    }

    // Handle File Uploads (Multiple)
    $photoPaths = [];
    if (isset($_FILES['photos'])) {
        $files = $_FILES['photos'];
        $fileCount = is_array($files['name']) ? count($files['name']) : 0;
        
        for ($i = 0; $i < $fileCount; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $fileTmpPath = $files['tmp_name'][$i];
                $fileName = $files['name'][$i];
                $fileNameCmps = explode(".", $fileName);
                $fileExtension = strtolower(end($fileNameCmps));
                
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                if (in_array($fileExtension, $allowedExtensions)) {
                    $uploadFileDir = __DIR__ . '/../uploads/handovers/';
                    if (!is_dir($uploadFileDir)) {
                        mkdir($uploadFileDir, 0777, true);
                    }
                    $newFileName = 'handover_' . time() . '_' . md5(uniqid() . $i) . '.' . $fileExtension;
                    $dest_path = $uploadFileDir . $newFileName;
                    
                    if (move_uploaded_file($fileTmpPath, $dest_path)) {
                        $photoPaths[] = 'uploads/handovers/' . $newFileName;
                    }
                }
            }
        }
    }

    $photoPathValue = !empty($photoPaths) ? json_encode($photoPaths) : null;

    // Generate Handover No
    $handoverNo = 'HND-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

    try {
        $stmt = $pdo->prepare("
            INSERT INTO handovers (handover_no, from_user_id, from_shift, to_shift, notes, photo_path, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'PENDING', ?)
        ");
        $stmt->execute([
            $handoverNo,
            $currentUser['id'],
            $fromShift,
            $toShift,
            $notes,
            $photoPathValue,
            date('Y-m-d H:i:s')
        ]);
        echo json_encode([
            'success' => true,
            'message' => 'Serah terima pekerjaan berhasil diserahkan.',
            'handover_no' => $handoverNo
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// 3. RECEIVE / ACCEPT HANDOVER (Mark as Done)
if ($action === 'receive') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID handover tidak valid.']);
        exit;
    }

    $receiverShift = $currentUser['shift'] ?? 'Shift 2 (Siang 16:00 - 00:00)';

    try {
        $stmt = $pdo->prepare("
            UPDATE handovers 
            SET status = 'RECEIVED', received_by = ?, receiver_shift = ?, received_at = ?
            WHERE id = ? AND status = 'PENDING'
        ");
        $stmt->execute([
            $currentUser['id'],
            $receiverShift,
            date('Y-m-d H:i:s'),
            $id
        ]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Tugas handover berhasil diterima & diselesaikan.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Handover sudah pernah diterima sebelumnya atau tidak ditemukan.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// 4. MARK AS SHARED
if ($action === 'mark_shared') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
        exit;
    }
    
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID handover tidak valid.']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE handovers SET is_shared = 1 WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Handover marked as shared.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action not found.']);
