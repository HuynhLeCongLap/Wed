<?php
session_start();
require_once 'connect.php';

/* ============================================================
   LẤY DỮ LIỆU
   ============================================================ */

// --- Bộ lọc ---
$filterHang = $_GET['hang'] ?? 'all';
$validHangs = ['all', 'Samsung', 'Apple', 'Xiaomi', 'Oppo', 'Realme', 'Poco'];
if (!in_array($filterHang, $validHangs)) $filterHang = 'all';

$search  = trim($_GET['q'] ?? '');
$sortBy  = $_GET['sort'] ?? 'default';
$validSort = ['default', 'price_asc', 'price_desc', 'name_asc'];
if (!in_array($sortBy, $validSort)) $sortBy = 'default';

// --- Query sản phẩm ---
if ($sortBy === 'price_asc')       $orderClause = 'GiaMin ASC';
elseif ($sortBy === 'price_desc')  $orderClause = 'GiaMin DESC';
elseif ($sortBy === 'name_asc')    $orderClause = 'sp.TenSanPham ASC';
else                               $orderClause = 'sp.Hang, sp.MaSanPham DESC';

$sql = "SELECT
    sp.MaSanPham,
    sp.TenSanPham,
    sp.Hang,
    MIN(g.GiaMoi)  AS GiaMin,
    MAX(g.GiaMoi)  AS GiaMax,
    MIN(g.GiaCu)   AS GiaCuMin,
    SUM(g.SoLuong) AS TongTonKho,
    COUNT(DISTINCT g.MaMau) AS SoMau,
    COUNT(DISTINCT g.MaRam) AS SoRam,
    sp.NgayNhap,
    (SELECT DiaChiAnh FROM image i WHERE i.MaSanPham = sp.MaSanPham LIMIT 1) AS AnhDaiDien
FROM sanpham sp
LEFT JOIN giasanpham g ON g.MaSanPham = sp.MaSanPham
WHERE 1=1";

$params = [];
$types  = '';

if ($filterHang !== 'all') {
    $sql    .= " AND sp.Hang = ?";
    $params[] = $filterHang;
    $types   .= 's';
}
if ($search !== '') {
    $sql     .= " AND sp.TenSanPham LIKE ?";
    $params[] = '%' . $search . '%';
    $types   .= 's';
}

$sql .= " GROUP BY sp.MaSanPham ORDER BY $orderClause";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$products = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// --- Đếm giỏ hàng ---
$cartCount = 0;
if (isset($_SESSION['user'])) {
    $stmtC = mysqli_prepare($conn, "SELECT COUNT(*) FROM giohang WHERE TenDangNhap = ?");
    mysqli_stmt_bind_param($stmtC, 's', $_SESSION['user']);
    mysqli_stmt_execute($stmtC);
    mysqli_stmt_bind_result($stmtC, $cartCount);
    mysqli_stmt_fetch($stmtC);
    mysqli_stmt_close($stmtC);
}

// --- Thống kê nhanh ---
$totalProducts = count($products);
$r = mysqli_query($conn, "SELECT COUNT(*) FROM khachhang"); $totalCustomers = mysqli_fetch_row($r)[0];
$r = mysqli_query($conn, "SELECT COUNT(*) FROM hoadon WHERE TrangThai='Hoàn thành'"); $totalSold = mysqli_fetch_row($r)[0];

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mobile Web — Cửa hàng điện thoại chính hãng</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <style>
/* ============================================================
   RESET & BASE
   ============================================================ */
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
:root {
    --blue:    #3498db;
    --red:     #e74c3c;
    --dark:    #1a202c;
    --gray:    #718096;
    --light:   #f7f8fa;
    --white:   #ffffff;
    --radius:  14px;
    --shadow:  0 4px 20px rgba(0,0,0,.08);
    --shadow-lg: 0 12px 40px rgba(0,0,0,.14);
}
body {
    font-family: 'Inter', 'Segoe UI', sans-serif;
    background: var(--light);
    color: var(--dark);
    line-height: 1.6;
    overflow-x: hidden;
}
a { text-decoration: none; color: inherit; }
img { display: block; }

.container { max-width: 1240px; margin: 0 auto; padding: 0 20px; }

/* ============================================================
   HEADER / NAVBAR
   ============================================================ */
.navbar {
    position: sticky;
    top: 0;
    z-index: 200;
    background: rgba(255,255,255,.95);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid #e8ecf0;
    box-shadow: 0 2px 12px rgba(0,0,0,.06);
}
.navbar-inner {
    display: flex;
    align-items: center;
    height: 64px;
    gap: 24px;
}
.navbar-logo {
    font-size: 22px;
    font-weight: 800;
    background: linear-gradient(135deg, #3498db, #2980b9);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    white-space: nowrap;
    letter-spacing: -0.5px;
}
.navbar-logo span { color: var(--red); -webkit-text-fill-color: var(--red); }

/* Search trong nav */
.nav-search {
    flex: 1;
    max-width: 460px;
    position: relative;
}
.nav-search form { display: flex; }
.nav-search input {
    width: 100%;
    padding: 10px 44px 10px 16px;
    border: 1.5px solid #e0e4ea;
    border-radius: 50px;
    font-size: 14px;
    outline: none;
    background: var(--light);
    transition: border-color .2s, box-shadow .2s;
    font-family: inherit;
}
.nav-search input:focus {
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(52,152,219,.15);
    background: white;
}
.nav-search button {
    position: absolute;
    right: 4px; top: 50%;
    transform: translateY(-50%);
    background: var(--blue);
    border: none;
    color: white;
    width: 34px; height: 34px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 15px;
    display: flex; align-items: center; justify-content: center;
    transition: background .2s;
}
.nav-search button:hover { background: #2980b9; }

/* Nav links */
.nav-links {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-left: auto;
}
.nav-link {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    color: var(--gray);
    transition: all .2s;
    white-space: nowrap;
}
.nav-link:hover { background: var(--light); color: var(--dark); }
.nav-link.active { color: var(--blue); font-weight: 600; }
.nav-link .icon { font-size: 17px; }

/* Cart badge */
.cart-pill {
    display: flex;
    align-items: center;
    gap: 6px;
    background: var(--blue);
    color: white;
    padding: 8px 16px;
    border-radius: 50px;
    font-size: 14px;
    font-weight: 600;
    transition: background .2s, transform .15s;
}
.cart-pill:hover { background: #2980b9; transform: scale(1.03); color: white; }
.cart-count {
    background: var(--red);
    color: white;
    border-radius: 50%;
    min-width: 20px; height: 20px;
    font-size: 11px;
    font-weight: 700;
    display: flex; align-items: center; justify-content: center;
}

/* ============================================================
   HERO SECTION
   ============================================================ */
.hero {
    background: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
    position: relative;
    overflow: hidden;
    padding: 72px 0 60px;
    color: white;
}
.hero::before {
    content: '';
    position: absolute;
    width: 600px; height: 600px;
    background: radial-gradient(circle, rgba(52,152,219,.3) 0%, transparent 70%);
    top: -200px; right: -100px;
    pointer-events: none;
}
.hero::after {
    content: '';
    position: absolute;
    width: 400px; height: 400px;
    background: radial-gradient(circle, rgba(231,76,60,.2) 0%, transparent 70%);
    bottom: -150px; left: -50px;
    pointer-events: none;
}
.hero-inner {
    position: relative;
    z-index: 2;
    display: flex;
    align-items: center;
    gap: 48px;
}
.hero-text { flex: 1; }
.hero-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(52,152,219,.25);
    border: 1px solid rgba(52,152,219,.4);
    color: #7ecff5;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    padding: 5px 14px;
    border-radius: 50px;
    margin-bottom: 18px;
}
.hero-title {
    font-size: clamp(28px, 4vw, 46px);
    font-weight: 800;
    line-height: 1.15;
    margin-bottom: 16px;
    letter-spacing: -1px;
}
.hero-title .highlight {
    background: linear-gradient(90deg, #74b9ff, #a29bfe);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.hero-sub {
    font-size: 16px;
    color: rgba(255,255,255,.7);
    margin-bottom: 28px;
    max-width: 440px;
}
.hero-cta {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}
.btn-hero-primary {
    padding: 13px 28px;
    background: var(--blue);
    color: white;
    border-radius: 10px;
    font-weight: 700;
    font-size: 15px;
    transition: all .2s;
    border: none;
    cursor: pointer;
}
.btn-hero-primary:hover {
    background: #2980b9;
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(52,152,219,.4);
    color: white;
}
.btn-hero-secondary {
    padding: 13px 28px;
    background: rgba(255,255,255,.12);
    border: 1.5px solid rgba(255,255,255,.3);
    color: white;
    border-radius: 10px;
    font-weight: 600;
    font-size: 15px;
    transition: all .2s;
    backdrop-filter: blur(4px);
}
.btn-hero-secondary:hover {
    background: rgba(255,255,255,.22);
    color: white;
    transform: translateY(-2px);
}

/* Hero Stats */
.hero-stats {
    display: flex;
    gap: 32px;
    margin-top: 36px;
}
.hero-stat-val {
    font-size: 26px;
    font-weight: 800;
    color: white;
}
.hero-stat-lbl {
    font-size: 12px;
    color: rgba(255,255,255,.55);
    margin-top: 2px;
}

/* Hero phone mockup */
.hero-visual {
    flex-shrink: 0;
    width: 260px;
    height: 280px;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
}
.hero-phone {
    width: 150px; height: 250px;
    background: linear-gradient(160deg, #1e3a5f, #2c5282);
    border-radius: 28px;
    border: 3px solid rgba(255,255,255,.2);
    box-shadow: 0 30px 60px rgba(0,0,0,.4), inset 0 1px 0 rgba(255,255,255,.15);
    position: relative;
    overflow: hidden;
    display: flex; align-items: center; justify-content: center;
    font-size: 64px;
}
.hero-phone::before {
    content: '';
    position: absolute;
    top: 0; left: 50%;
    transform: translateX(-50%);
    width: 70px; height: 22px;
    background: rgba(0,0,0,.6);
    border-radius: 0 0 18px 18px;
}
.hero-phone-float {
    position: absolute;
    right: 0; top: 30px;
    background: white;
    border-radius: 12px;
    padding: 10px 14px;
    box-shadow: 0 8px 24px rgba(0,0,0,.2);
    font-size: 13px;
    font-weight: 700;
    color: var(--dark);
    white-space: nowrap;
}
.hero-phone-float span { color: var(--red); display: block; font-size: 11px; font-weight: 500; margin-top: 2px; }

@media (max-width: 768px) {
    .hero-visual { display: none; }
    .hero { padding: 48px 0 40px; }
}

/* ============================================================
   BRAND SHOWCASE
   ============================================================ */
.brands-section {
    background: white;
    border-bottom: 1px solid #e8ecf0;
    padding: 0;
    position: sticky;
    top: 64px;
    z-index: 100;
    box-shadow: 0 2px 8px rgba(0,0,0,.04);
}
.brands-scroll {
    display: flex;
    align-items: center;
    gap: 6px;
    overflow-x: auto;
    padding: 12px 20px;
    scrollbar-width: none;
}
.brands-scroll::-webkit-scrollbar { display: none; }
.brand-chip {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 18px;
    border-radius: 50px;
    font-size: 14px;
    font-weight: 600;
    white-space: nowrap;
    border: 1.5px solid #e0e4ea;
    background: white;
    color: var(--gray);
    transition: all .2s;
    cursor: pointer;
    flex-shrink: 0;
}
.brand-chip:hover { border-color: var(--blue); color: var(--blue); background: #ebf5fb; }
.brand-chip.active {
    background: var(--dark);
    color: white;
    border-color: var(--dark);
}
.brand-logo-dot {
    width: 22px; height: 22px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 10px;
    font-weight: 900;
    color: white;
    flex-shrink: 0;
}

/* ============================================================
   PRODUCTS SECTION
   ============================================================ */
.products-section { padding: 28px 0 60px; }

/* Toolbar */
.section-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 12px;
}
.result-info {
    display: flex;
    align-items: center;
    gap: 10px;
}
.result-info h2 {
    font-size: 20px;
    font-weight: 800;
    color: var(--dark);
}
.result-count-badge {
    background: var(--blue);
    color: white;
    font-size: 12px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 50px;
}

/* Sort */
.sort-select {
    padding: 9px 14px;
    border: 1.5px solid #e0e4ea;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    background: white;
    color: var(--dark);
    cursor: pointer;
    outline: none;
    font-family: inherit;
    transition: border-color .2s;
}
.sort-select:focus { border-color: var(--blue); }

/* Search result bar */
.search-result-bar {
    background: #ebf5fb;
    border: 1px solid #d6eaf8;
    border-radius: 10px;
    padding: 12px 16px;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 14px;
    color: #2874a6;
    gap: 10px;
}
.search-result-bar a {
    color: var(--red);
    font-weight: 600;
    white-space: nowrap;
}

/* ============================================================
   PRODUCT GRID & CARDS
   ============================================================ */
.product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
    gap: 18px;
}

.product-card {
    background: white;
    border-radius: var(--radius);
    overflow: hidden;
    box-shadow: var(--shadow);
    transition: transform .22s, box-shadow .22s;
    display: flex;
    flex-direction: column;
    border: 1.5px solid transparent;
    position: relative;
}
.product-card:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-lg);
    border-color: rgba(52,152,219,.2);
}

/* Image area */
.pc-image {
    position: relative;
    height: 210px;
    background: #f7f8fa;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}
.pc-image img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 12px;
    transition: transform .3s;
}
.product-card:hover .pc-image img { transform: scale(1.05); }

/* Placeholder khi không có ảnh */
.pc-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    gap: 8px;
}
.pc-placeholder-icon {
    font-size: 58px;
    opacity: .7;
}
.pc-placeholder-brand {
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    opacity: .5;
}

/* Badges trên ảnh */
.pc-badge-group {
    position: absolute;
    top: 10px; left: 10px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.pc-badge {
    display: inline-block;
    padding: 3px 9px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    line-height: 1.4;
}
.pc-badge.discount { background: var(--red); color: white; }
.pc-badge.new      { background: #9b59b6;    color: white; }

.pc-stock-badge {
    position: absolute;
    top: 10px; right: 10px;
    padding: 3px 9px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    line-height: 1.4;
    backdrop-filter: blur(4px);
}
.pc-stock-badge.in    { background: rgba(39,174,96,.15);  color: #196f3d; border: 1px solid rgba(39,174,96,.3); }
.pc-stock-badge.low   { background: rgba(243,156,18,.15); color: #d68910; border: 1px solid rgba(243,156,18,.3); }
.pc-stock-badge.out   { background: rgba(231,76,60,.15);  color: #922b21; border: 1px solid rgba(231,76,60,.3); }

/* Card body */
.pc-body {
    padding: 14px 16px;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.pc-brand-tag {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .8px;
}
.pc-brand-dot {
    width: 7px; height: 7px;
    border-radius: 50%;
    flex-shrink: 0;
}
.pc-name {
    font-size: 15px;
    font-weight: 700;
    color: var(--dark);
    line-height: 1.35;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.pc-variants {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.pc-variant-pill {
    font-size: 11px;
    font-weight: 500;
    color: var(--gray);
    background: var(--light);
    padding: 2px 8px;
    border-radius: 4px;
}

/* Pricing */
.pc-pricing { margin-top: 2px; }
.pc-price-main {
    font-size: 20px;
    font-weight: 800;
    color: var(--red);
    line-height: 1.2;
}
.pc-price-from {
    font-size: 11px;
    font-weight: 400;
    color: var(--gray);
    margin-right: 2px;
}
.pc-price-old {
    font-size: 13px;
    color: #aaa;
    text-decoration: line-through;
    margin-top: 1px;
}

/* Stock bar */
.pc-stock-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 2px;
}
.pc-stock-bar-wrap {
    flex: 1;
    height: 4px;
    background: #e8ecf0;
    border-radius: 4px;
    overflow: hidden;
}
.pc-stock-bar-fill {
    height: 100%;
    border-radius: 4px;
    transition: width .4s;
}
.pc-stock-text {
    font-size: 11px;
    color: var(--gray);
    white-space: nowrap;
    font-weight: 500;
}

/* Actions */
.pc-actions {
    display: flex;
    gap: 8px;
    margin-top: 6px;
}
.pc-btn-detail {
    flex: 1;
    padding: 10px;
    background: var(--blue);
    color: white;
    border-radius: 8px;
    text-align: center;
    font-size: 13px;
    font-weight: 700;
    transition: background .2s;
    border: none;
    cursor: pointer;
}
.pc-btn-detail:hover { background: #2980b9; color: white; }
.pc-btn-cart {
    width: 40px;
    background: #f0f2f5;
    color: var(--gray);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 17px;
    transition: all .2s;
    flex-shrink: 0;
    border: none;
    cursor: pointer;
}
.pc-btn-cart:hover { background: #e8ecf0; color: var(--dark); }
.pc-btn-cart.out-disabled { opacity: .35; cursor: not-allowed; }

/* ============================================================
   EMPTY STATE
   ============================================================ */
.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 80px 20px;
}
.empty-state-icon { font-size: 64px; margin-bottom: 16px; }
.empty-state h3 { font-size: 20px; font-weight: 700; color: var(--dark); margin-bottom: 8px; }
.empty-state p { color: var(--gray); margin-bottom: 20px; }
.btn-reset {
    display: inline-block;
    padding: 12px 28px;
    background: var(--blue);
    color: white;
    border-radius: 8px;
    font-weight: 600;
    transition: background .2s;
}
.btn-reset:hover { background: #2980b9; color: white; }

/* ============================================================
   FOOTER
   ============================================================ */
.footer {
    background: var(--dark);
    color: rgba(255,255,255,.65);
    padding: 48px 0 24px;
    margin-top: 20px;
}
.footer-grid {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr;
    gap: 36px;
    margin-bottom: 36px;
}
@media (max-width: 900px) {
    .footer-grid { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 560px) {
    .footer-grid { grid-template-columns: 1fr; }
    .hero-stats  { gap: 20px; }
    .product-grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); }
    .pc-image { height: 160px; }
}
.footer-brand {
    font-size: 22px;
    font-weight: 800;
    color: white;
    margin-bottom: 10px;
}
.footer-brand span { color: var(--blue); }
.footer-desc { font-size: 14px; line-height: 1.7; }
.footer-title {
    font-size: 13px;
    font-weight: 700;
    color: white;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 14px;
}
.footer-links { list-style: none; }
.footer-links li { margin-bottom: 8px; }
.footer-links a {
    font-size: 14px;
    transition: color .2s;
}
.footer-links a:hover { color: white; }
.footer-bottom {
    border-top: 1px solid rgba(255,255,255,.1);
    padding-top: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13px;
    flex-wrap: wrap;
    gap: 10px;
}

/* ============================================================
   SCROLL TO TOP
   ============================================================ */
.scroll-top {
    position: fixed;
    bottom: 28px; right: 28px;
    background: var(--blue);
    color: white;
    width: 44px; height: 44px;
    border-radius: 50%;
    border: none;
    cursor: pointer;
    font-size: 20px;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 16px rgba(52,152,219,.4);
    opacity: 0;
    transform: translateY(12px);
    transition: opacity .3s, transform .3s;
    z-index: 999;
}
.scroll-top.visible { opacity: 1; transform: translateY(0); }

/* Skeleton loader animation */
@keyframes shimmer {
    0%   { background-position: -468px 0; }
    100% { background-position: 468px 0; }
}

/* Mobile nav adjustments */
@media (max-width: 640px) {
    .nav-search { display: none; }
    .navbar-inner { gap: 12px; }
    .nav-link .label { display: none; }
    .brands-section { top: 56px; }
}
    </style>
</head>
<body>

<!-- ============================================================
     NAVBAR
     ============================================================ -->
<header class="navbar">
    <div class="container">
        <div class="navbar-inner">
            <a href="index.php" class="navbar-logo">
                📱 Mobile<span>Web</span>
            </a>

            <div class="nav-search">
                <form method="GET" action="index.php">
                    <?php if ($filterHang !== 'all'): ?>
                        <input type="hidden" name="hang" value="<?= htmlspecialchars($filterHang) ?>">
                    <?php endif; ?>
                    <input type="text" name="q"
                           placeholder="Tìm kiếm điện thoại..."
                           value="<?= htmlspecialchars($search) ?>"
                           autocomplete="off">
                    <button type="submit" title="Tìm kiếm">🔍</button>
                </form>
            </div>

            <nav class="nav-links">
                <a href="index.php" class="nav-link active">
                    <span class="icon">🏠</span>
                    <span class="label">Trang chủ</span>
                </a>
                <a href="cart.php" class="cart-pill">
                    🛒 Giỏ hàng
                    <?php if ($cartCount > 0): ?>
                        <span class="cart-count"><?= $cartCount ?></span>
                    <?php endif; ?>
                </a>
                <?php if (isset($_SESSION['user'])): ?>
                    <a href="my_orders.php" class="nav-link">
                        <span class="icon">📦</span>
                        <span class="label">Đơn hàng</span>
                    </a>
                    <a href="pages/profile.php" class="nav-link">
                        <span class="icon">👤</span>
                        <span class="label">Tài khoản</span>
                    </a>
                <?php else: ?>
                    <a href="pages/login.php" class="nav-link" style="background:var(--light); font-weight:600; color:var(--dark);">
                        <span class="icon">🔑</span>
                        <span class="label">Đăng nhập</span>
                    </a>
                <?php endif; ?>
            </nav>
        </div>
    </div>
</header>

<!-- ============================================================
     HERO
     ============================================================ -->
<section class="hero">
    <div class="container">
        <div class="hero-inner">
            <div class="hero-text">
                <div class="hero-eyebrow">✨ Chính hãng · Giá tốt · Bảo hành</div>
                <h1 class="hero-title">
                    Mua điện thoại<br>
                    <span class="highlight">thông minh hơn</span>
                </h1>
                <p class="hero-sub">
                    Hơn <?= $totalProducts ?> mẫu điện thoại từ <?= count(array_unique(array_column($products, 'Hang'))) ?> thương hiệu hàng đầu.
                    Giá cạnh tranh, giao hàng nhanh toàn quốc.
                </p>
                <div class="hero-cta">
                    <a href="#products" class="btn-hero-primary">Mua ngay →</a>
                    <a href="my_orders.php" class="btn-hero-secondary">Theo dõi đơn hàng</a>
                </div>
                <div class="hero-stats">
                    <div>
                        <div class="hero-stat-val"><?= $totalProducts ?>+</div>
                        <div class="hero-stat-lbl">Sản phẩm</div>
                    </div>
                    <div>
                        <div class="hero-stat-val"><?= $totalCustomers ?>+</div>
                        <div class="hero-stat-lbl">Khách hàng</div>
                    </div>
                    <div>
                        <div class="hero-stat-val"><?= $totalSold ?>+</div>
                        <div class="hero-stat-lbl">Đơn thành công</div>
                    </div>
                </div>
            </div>

            <div class="hero-visual">
                <div class="hero-phone">📱</div>
                <div class="hero-phone-float">
                    🎉 Giảm đến 30%
                    <span>Cho đơn đầu tiên</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================
     BRAND FILTER
     ============================================================ -->
<div class="brands-section">
    <div class="brands-scroll">
        <?php
        $brandsMap = [
            'all'     => ['label' => 'Tất cả',  'color' => '#2c3e50', 'char' => '★'],
            'Samsung' => ['label' => 'Samsung',  'color' => '#1428A0', 'char' => 'S'],
            'Apple'   => ['label' => 'iPhone',   'color' => '#555555', 'char' => ''],
            'Xiaomi'  => ['label' => 'Xiaomi',   'color' => '#FF6900', 'char' => '小'],
            'Oppo'    => ['label' => 'Oppo',     'color' => '#1D8348', 'char' => 'O'],
            'Realme'  => ['label' => 'Realme',   'color' => '#F7C600', 'char' => 'R'],
            'Poco'    => ['label' => 'Poco',     'color' => '#F7C600', 'char' => 'P'],
        ];
        foreach ($brandsMap as $val => $b):
            $qStr = $val === 'all' ? '' : 'hang=' . urlencode($val);
            if ($search !== '') $qStr .= ($qStr ? '&' : '') . 'q=' . urlencode($search);
            if ($sortBy !== 'default') $qStr .= ($qStr ? '&' : '') . 'sort=' . $sortBy;
            $href = 'index.php' . ($qStr ? '?' . $qStr : '');

            // Đếm sản phẩm theo hãng từ $products
            $brandCount = $val === 'all' ? count($products) : count(array_filter($products, fn($p) => $p['Hang'] === $val));
            $isActive   = $filterHang === $val;
        ?>
        <a href="<?= $href ?>" class="brand-chip <?= $isActive ? 'active' : '' ?>">
            <span class="brand-logo-dot" style="background:<?= $b['color'] ?>;">
                <?= $b['char'] ?>
            </span>
            <?= $b['label'] ?>
            <span style="font-size:11px; opacity:.6;">(<?= $brandCount ?>)</span>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- ============================================================
     PRODUCTS SECTION
     ============================================================ -->
<section class="products-section" id="products">
    <div class="container">

        <?php if ($search !== ''): ?>
        <div class="search-result-bar">
            <span>🔍 Kết quả tìm kiếm cho: <strong>"<?= htmlspecialchars($search) ?>"</strong>
                — tìm thấy <strong><?= count($products) ?></strong> sản phẩm</span>
            <a href="index.php<?= $filterHang !== 'all' ? '?hang=' . urlencode($filterHang) : '' ?>">
                ✕ Xóa tìm kiếm
            </a>
        </div>
        <?php endif; ?>

        <div class="section-toolbar">
            <div class="result-info">
                <h2>
                    <?php if ($filterHang !== 'all'): ?>
                        <?= htmlspecialchars($brandsMap[$filterHang]['label'] ?? $filterHang) ?>
                    <?php elseif ($search !== ''): ?>
                        Kết quả tìm kiếm
                    <?php else: ?>
                        Tất cả sản phẩm
                    <?php endif; ?>
                </h2>
                <span class="result-count-badge"><?= count($products) ?></span>
            </div>
            <form method="GET" id="sort-form">
                <?php if ($filterHang !== 'all'): ?><input type="hidden" name="hang" value="<?= htmlspecialchars($filterHang) ?>"><?php endif; ?>
                <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
                <select name="sort" class="sort-select" onchange="this.form.submit()">
                    <option value="default"    <?= $sortBy==='default'    ?'selected':''?>>🏷 Mặc định</option>
                    <option value="price_asc"  <?= $sortBy==='price_asc'  ?'selected':''?>>💰 Giá: Thấp → Cao</option>
                    <option value="price_desc" <?= $sortBy==='price_desc' ?'selected':''?>>💰 Giá: Cao → Thấp</option>
                    <option value="name_asc"   <?= $sortBy==='name_asc'   ?'selected':''?>>🔤 Tên A → Z</option>
                </select>
            </form>
        </div>

        <!-- ===== GRID ===== -->
        <div class="product-grid">
            <?php if (empty($products)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <h3>Không tìm thấy sản phẩm</h3>
                    <p>Thử tìm kiếm với từ khóa khác hoặc bỏ bộ lọc.</p>
                    <a href="index.php" class="btn-reset">Xem tất cả sản phẩm</a>
                </div>
            <?php else: ?>
                <?php foreach ($products as $p):
                    $tonKho  = (int)$p['TongTonKho'];
                    $giaMin  = (int)$p['GiaMin'];
                    $giaCu   = (int)$p['GiaCuMin'];
                    $soMau   = (int)$p['SoMau'];
                    $soRam   = (int)$p['SoRam'];
                    $hang    = $p['Hang'];
                    $color   = $brandsMap[$hang]['color'] ?? '#3498db';

                    // Discount
                    $discount = ($giaCu > 0 && $giaCu > $giaMin)
                        ? round((1 - $giaMin / $giaCu) * 100) : 0;

                    // Stock
                    if ($tonKho === 0) {
                        $sClass = 'out'; $sTxt = 'Hết hàng';
                        $barW = 0; $barColor = '#e74c3c';
                    } elseif ($tonKho <= 20) {
                        $sClass = 'low'; $sTxt = "Còn $tonKho";
                        $barW = max(10, min(90, $tonKho * 3)); $barColor = '#f39c12';
                    } else {
                        $sClass = 'in'; $sTxt = 'Còn hàng';
                        $barW = 100; $barColor = '#27ae60';
                    }

                    // Is new (nhập trong 30 ngày)
                    $isNew = !empty($p['NgayNhap'])
                        && strtotime($p['NgayNhap']) > strtotime('-30 days');

                    $hasImage = !empty($p['AnhDaiDien']);
                ?>
                <div class="product-card">
                    <!-- Image -->
                    <div class="pc-image" style="<?= !$hasImage ? 'background:linear-gradient(135deg, ' . $color . '18, ' . $color . '08)' : '' ?>">
                        <?php if ($hasImage): ?>
                            <img src="<?= htmlspecialchars($p['AnhDaiDien']) ?>"
                                 alt="<?= htmlspecialchars($p['TenSanPham']) ?>"
                                 onerror="this.parentElement.innerHTML = '<div class=\'pc-placeholder\'><div class=\'pc-placeholder-icon\'>📱</div><div class=\'pc-placeholder-brand\' style=\'color:<?= addslashes($color) ?>\'><?= htmlspecialchars(addslashes($hang)) ?></div></div>'">
                        <?php else: ?>
                            <div class="pc-placeholder">
                                <div class="pc-placeholder-icon">📱</div>
                                <div class="pc-placeholder-brand" style="color:<?= $color ?>">
                                    <?= htmlspecialchars($hang) ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Badges -->
                        <div class="pc-badge-group">
                            <?php if ($discount >= 5): ?>
                                <span class="pc-badge discount">-<?= $discount ?>%</span>
                            <?php endif; ?>
                            <?php if ($isNew): ?>
                                <span class="pc-badge new">Mới</span>
                            <?php endif; ?>
                        </div>
                        <span class="pc-stock-badge <?= $sClass ?>"><?= $sTxt ?></span>
                    </div>

                    <!-- Body -->
                    <div class="pc-body">
                        <div class="pc-brand-tag" style="color:<?= $color ?>">
                            <span class="pc-brand-dot" style="background:<?= $color ?>"></span>
                            <?= htmlspecialchars($hang) ?>
                        </div>

                        <div class="pc-name"><?= htmlspecialchars($p['TenSanPham']) ?></div>

                        <!-- Variants info -->
                        <div class="pc-variants">
                            <?php if ($soMau > 0): ?>
                                <span class="pc-variant-pill">🎨 <?= $soMau ?> màu</span>
                            <?php endif; ?>
                            <?php if ($soRam > 0): ?>
                                <span class="pc-variant-pill">💾 <?= $soRam ?> tùy chọn</span>
                            <?php endif; ?>
                        </div>

                        <!-- Pricing -->
                        <div class="pc-pricing">
                            <div class="pc-price-main">
                                <span class="pc-price-from">Từ</span>
                                <?= number_format($giaMin, 0, ',', '.') ?>đ
                            </div>
                            <?php if ($giaCu > $giaMin): ?>
                                <div class="pc-price-old">
                                    <?= number_format($giaCu, 0, ',', '.') ?>đ
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Stock bar -->
                        <div class="pc-stock-row">
                            <div class="pc-stock-bar-wrap">
                                <div class="pc-stock-bar-fill"
                                     style="width:<?= $barW ?>%; background:<?= $barColor ?>"></div>
                            </div>
                            <span class="pc-stock-text">
                                <?php if ($tonKho === 0): ?>
                                    Hết hàng
                                <?php elseif ($tonKho <= 20): ?>
                                    Còn <?= $tonKho ?>
                                <?php else: ?>
                                    <?= number_format($tonKho, 0, ',', '.') ?> sp
                                <?php endif; ?>
                            </span>
                        </div>

                        <!-- Actions -->
                        <div class="pc-actions">
                            <a href="product_detail.php?id=<?= (int)$p['MaSanPham'] ?>"
                               class="pc-btn-detail">
                                Xem chi tiết
                            </a>
                            <?php if ($tonKho > 0 && isset($_SESSION['user'])): ?>
                                <a href="product_detail.php?id=<?= (int)$p['MaSanPham'] ?>"
                                   class="pc-btn-cart" title="Thêm vào giỏ">🛒</a>
                            <?php else: ?>
                                <span class="pc-btn-cart <?= $tonKho === 0 ? 'out-disabled' : '' ?>"
                                      title="<?= $tonKho === 0 ? 'Hết hàng' : 'Đăng nhập để mua' ?>">
                                    <?= $tonKho === 0 ? '🚫' : '🛒' ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div><!-- .container -->
</section>

<!-- ============================================================
     FOOTER
     ============================================================ -->
<footer class="footer">
    <div class="container">
        <div class="footer-grid">
            <div>
                <div class="footer-brand">📱 Mobile<span>Web</span></div>
                <p class="footer-desc">
                    Cửa hàng điện thoại chính hãng — Cam kết giá tốt nhất, bảo hành chính hãng.
                    Giao hàng nhanh toàn quốc.
                </p>
            </div>
            <div>
                <div class="footer-title">Sản phẩm</div>
                <ul class="footer-links">
                    <?php
                    $footerBrands = ['Samsung','Apple','Xiaomi','Oppo','Realme'];
                    foreach ($footerBrands as $fb):
                    ?>
                        <li><a href="index.php?hang=<?= urlencode($fb) ?>"><?= $fb ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div>
                <div class="footer-title">Hỗ trợ</div>
                <ul class="footer-links">
                    <li><a href="my_orders.php">Theo dõi đơn hàng</a></li>
                    <li><a href="pages/login.php">Đăng nhập</a></li>
                    <li><a href="pages/register.php">Đăng ký</a></li>
                    <li><a href="pages/profile.php">Tài khoản</a></li>
                </ul>
            </div>
            <div>
                <div class="footer-title">Liên hệ</div>
                <ul class="footer-links">
                    <li>📞 0123 456 789</li>
                    <li>✉️ info@mobileweb.vn</li>
                    <li>📍 123 Đường ABC, TP.HCM</li>
                    <li style="margin-top:12px;">
                        <a href="admin/login.php"
                           style="background:rgba(255,255,255,.1); padding:6px 14px; border-radius:6px; font-size:12px; color:rgba(255,255,255,.6);">
                            ⚙️ Quản trị
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <span>© <?= date('Y') ?> Mobile Web — Đồ án cuối kỳ</span>
            <span><?= $totalProducts ?> sản phẩm · <?= $totalCustomers ?> khách hàng</span>
        </div>
    </div>
</footer>

<!-- Scroll to top -->
<button class="scroll-top" id="scrollTop" onclick="window.scrollTo({top:0,behavior:'smooth'})">↑</button>

<script>
// Scroll to top button
const scrollBtn = document.getElementById('scrollTop');
window.addEventListener('scroll', () => {
    scrollBtn.classList.toggle('visible', window.scrollY > 400);
});

// Smooth anchor scroll
document.querySelectorAll('a[href="#products"]').forEach(a => {
    a.addEventListener('click', e => {
        e.preventDefault();
        document.getElementById('products').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
});
</script>
</body>
</html>
