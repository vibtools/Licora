<?php
require_once '../includes/auth.php';

$auth = new Auth();
$auth->adminLogout();

header("Location: login.php");
exit();
?>