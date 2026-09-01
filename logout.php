<?php
// logout.php - Session destruction and redirect to login
require_once __DIR__ . '/includes/auth.php';

Auth::logout();
$timeout = isset($_GET['timeout']) ? '?timeout=1' : '';
header("Location: login" . $timeout);
exit;

