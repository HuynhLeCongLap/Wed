<?php
// Include file này để render header/sidebar admin
// Usage: require_once '_layout.php'; then call adminHeader($title, $activePage)

function adminHeader($title = 'Admin', $activePage = '') {
    $admin = $_SESSION['admin_name'] ?? $_SESSION['admin'] ?? 'Admin';
    $menu = [
        'index'    => ['icon' => '📊', 'label' => 'Dashboard'],
        'orders'   => ['icon' => '🛒', 'label' => 'Đơn hàng'],
        'products' => ['icon' => '📱', 'label' => 'Sản phẩm'],
        'customers'=> ['icon' => '👥', 'label' => 'Khách hàng'],
    ];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> - Admin Mobile Web</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',sans-serif; background:#f0f2f5; display:flex; min-height:100vh; }

        /* Sidebar */
        .sidebar {
            width: 220px;
            background: #2c3e50;
            color: white;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; left: 0; bottom: 0;
            z-index: 100;
        }
        .sidebar-logo {
            padding: 20px;
            font-size: 18px;
            font-weight: 800;
            color: #3498db;
            border-bottom: 1px solid rgba(255,255,255,.1);
        }
        .sidebar-logo span { color: #fff; font-size: 13px; font-weight: 400; display: block; margin-top: 2px; }
        .sidebar-nav { flex: 1; padding: 16px 0; }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 20px;
            color: rgba(255,255,255,.7);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all .2s;
        }
        .sidebar-nav a:hover { background: rgba(255,255,255,.08); color: white; }
        .sidebar-nav a.active { background: #3498db; color: white; }
        .sidebar-nav .icon { font-size: 18px; width: 24px; text-align: center; }

        .sidebar-footer {
            padding: 16px 20px;
            border-top: 1px solid rgba(255,255,255,.1);
            font-size: 13px;
            color: rgba(255,255,255,.6);
        }
        .sidebar-footer a { color: #e74c3c; text-decoration: none; display: block; margin-top: 6px; }

        /* Main */
        .main-wrap {
            margin-left: 220px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .topbar {
            background: white;
            padding: 14px 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,.06);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .topbar h1 { font-size: 20px; color: #2c3e50; }
        .topbar .admin-info { color: #666; font-size: 14px; }

        .content { padding: 24px; flex: 1; }

        /* Cards & tables */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,.06);
            text-align: center;
        }
        .stat-card .stat-icon { font-size: 32px; margin-bottom: 8px; }
        .stat-card .stat-value { font-size: 26px; font-weight: 800; color: #2c3e50; }
        .stat-card .stat-label { font-size: 13px; color: #888; margin-top: 4px; }

        .card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,.06);
            margin-bottom: 20px;
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #eee;
        }
        .card-header h2 { font-size: 16px; color: #2c3e50; }

        table { width: 100%; border-collapse: collapse; }
        thead tr { background: #f8fafc; }
        thead th { padding: 11px 12px; text-align: left; font-size: 13px; color: #666; font-weight: 600; }
        tbody td { padding: 11px 12px; border-bottom: 1px solid #f0f0f0; font-size: 14px; vertical-align: middle; }
        tbody tr:hover { background: #fafafa; }
        tbody tr:last-child td { border-bottom: none; }

        .btn {
            display: inline-block;
            padding: 7px 14px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: opacity .2s;
        }
        .btn:hover { opacity: .85; text-decoration: none; }
        .btn-sm    { padding: 5px 10px; font-size: 12px; }
        .btn-blue  { background: #3498db; color: white; }
        .btn-green { background: #2ecc71; color: white; }
        .btn-red   { background: #e74c3c; color: white; }
        .btn-gray  { background: #95a5a6; color: white; }
        .btn-orange{ background: #f39c12; color: white; }

        .badge { display:inline-block; padding:3px 10px; border-radius:10px; font-size:12px; font-weight:600; }
        .badge-pending    { background:#fef9e7; color:#d68910; }
        .badge-confirmed  { background:#d6eaf8; color:#2874a6; }
        .badge-shipping   { background:#d4e6f1; color:#1f618d; }
        .badge-done       { background:#d5f5e3; color:#196f3d; }
        .badge-cancelled  { background:#fadbd8; color:#922b21; }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            outline: none;
        }
        .form-control:focus { border-color: #3498db; }

        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; font-size: 14px; }
        .alert-success { background:#d5f5e3; color:#196f3d; }
        .alert-error   { background:#fadbd8; color:#922b21; }

        @media(max-width:768px) {
            .sidebar { width: 60px; }
            .sidebar-logo span, .sidebar-nav a span, .sidebar-footer { display: none; }
            .main-wrap { margin-left: 60px; }
        }
    </style>
</head>
<body>
    <nav class="sidebar">
        <div class="sidebar-logo">
            ⚙️ Admin
            <span>Mobile Web</span>
        </div>
        <div class="sidebar-nav">
            <?php foreach ($menu as $key => $item): ?>
                <a href="<?= $key ?>.php" class="<?= $activePage === $key ? 'active' : '' ?>">
                    <span class="icon"><?= $item['icon'] ?></span>
                    <span><?= $item['label'] ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="sidebar-footer">
            👤 <?= htmlspecialchars($admin) ?>
            <a href="logout.php">🚪 Đăng xuất</a>
            <a href="../index.php" style="color:#3498db; margin-top:4px;">🏠 Về trang chủ</a>
        </div>
    </nav>
    <div class="main-wrap">
        <div class="topbar">
            <h1><?= htmlspecialchars($title) ?></h1>
            <div class="admin-info">👤 <?= htmlspecialchars($admin) ?></div>
        </div>
        <div class="content">
<?php
} // end adminHeader

function adminFooter() {
?>
        </div><!-- .content -->
    </div><!-- .main-wrap -->
</body>
</html>
<?php
} // end adminFooter

function statusBadge($status) {
    $map = [
        'Chưa xác nhận' => 'badge-pending',
        'Đã xác nhận'   => 'badge-confirmed',
        'Đang giao'     => 'badge-shipping',
        'Hoàn thành'    => 'badge-done',
        'Đã hủy'        => 'badge-cancelled',
    ];
    $cls = $map[$status] ?? 'badge-pending';
    return '<span class="badge ' . $cls . '">' . htmlspecialchars($status) . '</span>';
}
