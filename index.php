<?php
// index.php - Main Router & Entrypoint
require_once __DIR__ . '/includes/auth.php';

if (Auth::check()) {
    if (Auth::isAdmin()) {
        header("Location: admin/");
        exit;
    } else {
        header("Location: operator/");
        exit;
    }
}

// If not logged in, show login page
header("Location: login");
exit;
