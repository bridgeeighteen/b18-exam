<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/auth.php';

initSecurity();

unset($_SESSION['admin'], $_SESSION['admin_pending_forum']);
session_regenerate_id(true);

header('Location: index.php');
exit;
