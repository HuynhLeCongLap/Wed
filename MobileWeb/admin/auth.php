<?php
// Guard file: include vào đầu mỗi trang admin
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}
