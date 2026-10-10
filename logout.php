<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

logout_user();
set_flash('info', 'You have been safely signed out.');
header("Location: " . BASE_URL . "login.php");
exit;
