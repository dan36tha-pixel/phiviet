<?php
/** Đăng xuất. */
require_once __DIR__ . '/includes/helpers.php';
session_destroy();
header('Location: ./');
exit;
