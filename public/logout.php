<?php
require_once __DIR__ . '/../src/Auth/AuthService.php';

$auth = new AuthService();
$auth->logout();
header("Location: /login.php");
exit;
