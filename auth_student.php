<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* 🚫 Disable browser caching completely */
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

/* 🔐 Authentication check */
if (empty($_SESSION['user_id'])) {
    header("Location: login_form.html");
    exit;
}
