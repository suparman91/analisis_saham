<?php
require_once 'auth.php';
logout_session();
header("Location: login.php");
exit;
?>