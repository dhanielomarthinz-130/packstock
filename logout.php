<?php
// logout.php - Session destruction and redirect to login
require_once __DIR__ . '/includes/auth.php';

Auth::logout();
header("Location: login");
exit;
