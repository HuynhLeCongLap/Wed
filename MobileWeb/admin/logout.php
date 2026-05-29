<?php
session_start();
unset($_SESSION['admin'], $_SESSION['admin_name']);
session_destroy();
header('Location: login.php');
exit;
