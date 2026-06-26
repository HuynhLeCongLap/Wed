<?php
session_start();
require 'connect.php';

// tìm kiếm
$limit        = 12;
$page         = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset       = ($page - 1) * $limit;
$search       = isset($_GET['search']) ? trim($_GET['search']) : '';
$brand_filter = isset($_GET['hang'])   ? trim($_GET['hang'])   : '';
// Sắp xếp
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : '';

// Chọn khu vực
$location = isset($_GET['location']) ? trim($_GET['location']) : 'Hồ Chí Minh';

// danh sách
$where_clauses = ["1=1"];
$params        = [];
$types         = '';

if ($search !== '') {
    $where_clauses[] = "sp.TenSanPham LIKE ?";
    $params[]        = "%$search%";
    $types          .= 's';
}
if ($brand_filter !== '') {
    $where_clauses[] = "sp.Hang = ?";
    $params[]        = $brand_filter;
    $types          .= 's';
}
$where_sql = implode(' AND ', $where_clauses);

// Xử lý tham số sắp xếp (whitelist)
$allowed_sorts = ['price_asc', 'price_desc', 'newest'];
if (!in_array($sort, $allowed_sorts)) $sort = '';
switch ($sort) {
    case 'price_asc':
        $order_by = 'GiaMoi ASC';
        break;
    case 'price_desc':
        $order_by = 'GiaMoi DESC';
        break;
    case 'newest':
        $order_by = 'sp.MaSanPham DESC';
        break;
    default:
        $order_by = 'sp.MaSanPham ASC';
}

// Đếm tổng số sản phẩm
$count_sql  = "SELECT COUNT(DISTINCT sp.MaSanPham) as total
               FROM sanpham sp
               INNER JOIN giasanpham gsp ON sp.MaSanPham = gsp.MaSanPham
               INNER JOIN image img ON img.MaSanPham = sp.MaSanPham
               WHERE $where_sql";
$count_stmt = $conn->prepare($count_sql);
if ($types !== '') {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_rows  = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);
$count_stmt->close();

// Lấy danh sách sản phẩm
$list_sql = "
    SELECT
        sp.MaSanPham,
        sp.TenSanPham,
        sp.Hang,
        MIN(gsp.GiaMoi)  AS GiaMoi,
        MIN(gsp.GiaCu)   AS GiaCu,
        SUM(gsp.SoLuong) AS TongTonKho,
        (
            SELECT img2.DiaChiAnh
            FROM image img2
            WHERE img2.MaSanPham = sp.MaSanPham
            ORDER BY img2.MaHinhAnh ASC
            LIMIT 1
        ) AS DiaChiAnh
    FROM sanpham sp
    INNER JOIN giasanpham gsp ON sp.MaSanPham = gsp.MaSanPham
    INNER JOIN image img     ON img.MaSanPham  = sp.MaSanPham
    WHERE $where_sql
    GROUP BY sp.MaSanPham, sp.TenSanPham, sp.Hang
    ORDER BY " . ($order_by ?? 'sp.MaSanPham ASC') . "
    LIMIT ? OFFSET ?
";

$param_types = $types . 'ii';
$all_params  = array_merge($params, [$limit, $offset]);

$list_stmt = $conn->prepare($list_sql);
$list_stmt->bind_param($param_types, ...$all_params);
$list_stmt->execute();
$products = $list_stmt->get_result();
$list_stmt->close();

// Lấy danh sách hãng theo danh mục sản phẩm
$brands_result = $conn->query("SELECT DISTINCT LoaiSanPham, Hang FROM sanpham ORDER BY LoaiSanPham ASC, Hang ASC");
$brands_grouped = [];
if ($brands_result) {
    while ($b = $brands_result->fetch_assoc()) {
        $brands_grouped[$b['LoaiSanPham']][] = $b['Hang'];
    }
} else {
    echo "Lỗi query hãng: " . $conn->error;
}
?>
<?php include 'header.php'; ?>

<section class="hero">
    <h1>Điện Thoại <span class="highlight">Chính Hãng</span></h1>
    <p>Khám phá hàng ngàn mẫu điện thoại với giá tốt nhất thị trường</p>
    <div class="hero-stats">
        <div class="stat"><strong><?= $total_rows ?>+</strong><span>Sản phẩm</span></div>
        <div class="stat"><strong>50+</strong><span>Thương hiệu</span></div>
        <div class="stat"><strong>100%</strong><span>Chính hãng</span></div>
    </div>
</section>

<!-- BANNER SLIDESHOW -->
<section class="banner-section">
    <div class="banner-container">
        <div class="banner-slider" id="bannerSlider">
            <div class="banner-slide active">
                <div class="banner-content">
                    <div class="banner-text">
                        <h2>iPhone 16 Series</h2>
                        <p>Mới nhất 2024 - Giảm giá lên tới 20%</p>
                        <a href="index.php?hang=Apple" class="banner-btn">Xem ngay</a>
                    </div>
                    <div class="banner-image">
                        <img src="img/BANNER/APPLE.jpg" alt="iPhone 16" onerror="this.style.display='none'">
                    </div>
                </div>
            </div>
            <div class="banner-slide">
                <div class="banner-content">
                    <div class="banner-text">
                        <h2>Samsung Galaxy S24</h2>
                        <p>Camera vượt trội - Hiệu năng tuyệt đỉnh</p>
                        <a href="index.php?hang=Samsung" class="banner-btn">Xem ngay</a>
                    </div>
                    <div class="banner-image">
                        <img src="img/BANNER/SAMSUNG.jpg" alt="Samsung S24" onerror="this.style.display='none'">
                    </div>
                </div>
            </div>
            <div class="banner-slide">
                <div class="banner-content">
                    <div class="banner-text">
                        <h2>Khuyến mãi đặc biệt</h2>
                        <p>Mua 2 tặng 1 - Hoàn tiền 100% nếu tìm được giá rẻ hơn</p>
                        <a href="index.php?hang=OPPO" class="banner-btn">Mua sắm</a>
                    </div>
                    <div class="banner-image">
                        <img src="img/BANNER/OPPO.jpg" alt="OPPO" onerror="this.style.display='none'">
                    </div>
                </div>
            </div>
        </div>
        <button class="banner-nav banner-prev" onclick="bannerSlide(-1)">❮</button>
        <button class="banner-nav banner-next" onclick="bannerSlide(1)">❯</button>
        <div class="banner-dots">
            <span class="dot active" onclick="currentBannerSlide(0)"></span>
            <span class="dot" onclick="currentBannerSlide(1)"></span>
            <span class="dot" onclick="currentBannerSlide(2)"></span>
        </div>
    </div>
</section>


<main class="container main-layout">

    <aside class="sidebar">
        <div class="filter-card">
            <h3 class="filter-title">🏷️ Lọc theo hãng</h3>
            <ul class="brand-list">
                <li>
                    <a href="index.php<?= $search ? '?search=' . urlencode($search) : '' ?>"
                        class="brand-item <?= $brand_filter === '' ? 'active' : '' ?>">
                        Tất cả hãng
                    </a>
                </li>
                <?php foreach ($brands_grouped as $loai => $brands): ?>
                    <li style="margin-top: 18px; margin-bottom: 6px; font-weight: 700; color: var(--brand); font-size: 0.85rem; text-transform: uppercase;">
                        <?php 
                        if ($loai == 'DienThoai') echo '📱 Điện thoại';
                        elseif ($loai == 'Laptop') echo '💻 Laptop';
                        elseif ($loai == 'PhuKien') echo '🎧 Phụ kiện';
                        else echo htmlspecialchars($loai);
                        ?>
                    </li>
                    <?php foreach ($brands as $brand): ?>
                        <li>
                            <?php
                            $href = 'index.php?hang=' . urlencode($brand);
                            if ($search) $href .= '&search=' . urlencode($search);
                            ?>
                            <a href="<?= $href ?>"
                                class="brand-item <?= $brand_filter === $brand ? 'active' : '' ?>" style="padding-left: 15px; position: relative;">
                                <span style="position: absolute; left: 4px; top: 50%; transform: translateY(-50%); font-size: 18px; color: <?= $brand_filter === $brand ? 'var(--brand)' : '#cbd5e1' ?>;">•</span>
                                <?= htmlspecialchars($brand) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </ul>
        </div>
    </aside>

    <!-- Khu vực sản phẩm -->
    <section class="products-area">
        <!-- Thông báo -->
        <div id="toastMessage" class="toast hidden"></div>

        <!-- Tiêu đề + bộ lọc -->
        <div class="products-header">
            <div class="results-info">
                <?php if ($search || $brand_filter): ?>
                    <span>
                        Kết quả cho
                        <?php if ($search): ?> "<strong><?= htmlspecialchars($search) ?></strong>"<?php endif; ?>
                            <?php if ($brand_filter): ?> hãng <strong><?= htmlspecialchars($brand_filter) ?></strong><?php endif; ?>
                            — <strong><?= $total_rows ?></strong> sản phẩm
                    </span>
                <?php else: ?>
                    <span>Hiển thị <strong><?= min($offset + $limit, $total_rows) ?></strong> / <strong><?= $total_rows ?></strong> sản phẩm</span>
                <?php endif; ?>
            </div>
            <div class="sort-controls">
                <form method="get" id="sortForm">
                    <?php if ($search): ?><input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
                    <?php if ($brand_filter): ?><input type="hidden" name="hang" value="<?= htmlspecialchars($brand_filter) ?>"><?php endif; ?>
                    <label for="sortSelect">Sắp xếp:</label>
                    <select id="sortSelect" name="sort" onchange="document.getElementById('sortForm').submit()">
                        <option value="" <?= $sort === '' ? 'selected' : '' ?>>Mặc định</option>
                        <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Giá: Thấp → Cao</option>
                        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Giá: Cao → Thấp</option>
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Mới nhất</option>
                    </select>
                </form>
            </div>
        </div>

        <!-- Grid sản phẩm -->
        <?php if ($products->num_rows === 0): ?>
            <div class="no-products">
                <p>😕 Không tìm thấy sản phẩm nào phù hợp.</p>
                <a href="index.php" class="btn-primary">Xem tất cả sản phẩm</a>
            </div>
        <?php else: ?>
            <div class="product-grid" id="productGrid">
                <?php
                $product_index = 0;
                while ($p = $products->fetch_assoc()):
                    $product_index++;
                ?>
                    <?php
                    $imgPath = !empty($p['DiaChiAnh'])
                        ? htmlspecialchars($p['DiaChiAnh'])
                        : 'img/no-image.svg';
                    $discount = ($p['GiaCu'] > 0 && $p['GiaCu'] > $p['GiaMoi'])
                        ? round((1 - $p['GiaMoi'] / $p['GiaCu']) * 100)
                        : 0;
                    ?>
                    <div class="product-card" data-id="<?= $p['MaSanPham'] ?>">
                        <?php if ($discount > 0): ?>
                            <span class="badge-discount">-<?= $discount ?>%</span>
                        <?php endif; ?>

                        <!-- Badges -->
                        <div class="badge-group">
                            <?php if ($page === 1 && $product_index <= 5): ?>
                                <span class="badge badge-new">Mới</span>
                            <?php endif; ?>
                            <?php if ($discount > 15): ?>
                                <span class="badge badge-hot">Hot</span>
                            <?php endif; ?>
                        </div>

                        <a href="pages/detail.php?id=<?= $p['MaSanPham'] ?>" class="product-img-wrap">
                            <img src="<?= $imgPath ?>"
                                alt="<?= htmlspecialchars($p['TenSanPham']) ?>"
                                loading="lazy"
                                onerror="this.src='img/no-image.svg'">
                        </a>

                        <div class="product-info">
                            <span class="product-brand"><?= htmlspecialchars($p['Hang']) ?></span>
                            <h2 class="product-name">
                                <a href="pages/detail.php?id=<?= $p['MaSanPham'] ?>">
                                    <?= htmlspecialchars($p['TenSanPham']) ?>
                                </a>
                            </h2>
                            <?php if (isset($p['Rating'])): ?>
                                <?php $r = round(floatval($p['Rating'])); ?>
                                <div class="rating" aria-hidden="true">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fa-star fa <?= $i <= $r ? 'fa-solid' : 'fa-regular empty' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            <?php else: ?>
                                <div class="rating" aria-hidden="true">
                                    <i class="fa-regular fa-star empty"></i>
                                    <i class="fa-regular fa-star empty"></i>
                                    <i class="fa-regular fa-star empty"></i>
                                    <i class="fa-regular fa-star empty"></i>
                                    <i class="fa-regular fa-star empty"></i>
                                    <span style="font-size:0.78rem;color:var(--muted);margin-left:6px;">Chưa có đánh giá</span>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($p['ThongSo'])): ?>
                                <div class="product-specs"><?= htmlspecialchars($p['ThongSo']) ?></div>
                            <?php endif; ?>
                            <div class="product-price">
                                <span class="price-new"><?= number_format($p['GiaMoi'], 0, ',', '.') ?>đ</span>
                                <?php if ($p['GiaCu'] > $p['GiaMoi']): ?>
                                    <span class="price-old"><?= number_format($p['GiaCu'], 0, ',', '.') ?>đ</span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:0.8rem; color:var(--text-muted); margin-top:5px;">
                                <i class="fa-solid fa-boxes-stacked"></i> Còn lại: <?= $p['TongTonKho'] ?: 0 ?> sản phẩm
                            </div>
                        </div>

                        <div class="product-actions">
                            <a href="pages/detail.php?id=<?= $p['MaSanPham'] ?>" class="btn-detail">
                                Xem chi tiết
                            </a>
                            <button class="btn-add-cart"
                                data-id="<?= $p['MaSanPham'] ?>"
                                data-name="<?= htmlspecialchars($p['TenSanPham'], ENT_QUOTES) ?>"
                                onclick="quickAddToCart(this)">
                                🛒 Thêm vào giỏ
                            </button>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

            <!-- PHÂN TRANG -->
            <?php if ($total_pages > 1): ?>
                <nav class="pagination" aria-label="Phân trang">
                    <?php
                    $query_base = '';
                    if ($search)       $query_base .= '&search=' . urlencode($search);
                    if ($brand_filter) $query_base .= '&hang='   . urlencode($brand_filter);
                    if ($sort)         $query_base .= '&sort='   . urlencode($sort);
                    ?>

                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?><?= $query_base ?>" class="page-btn">‹ Trước</a>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $page - 2);
                    $end   = min($total_pages, $page + 2);
                    if ($start > 1): ?>
                        <a href="?page=1<?= $query_base ?>" class="page-btn">1</a>
                        <?php if ($start > 2): ?><span class="page-ellipsis">…</span><?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $start; $i <= $end; $i++): ?>
                        <a href="?page=<?= $i ?><?= $query_base ?>"
                            class="page-btn <?= $i === $page ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($end < $total_pages): ?>
                        <?php if ($end < $total_pages - 1): ?><span class="page-ellipsis">…</span><?php endif; ?>
                        <a href="?page=<?= $total_pages ?><?= $query_base ?>" class="page-btn"><?= $total_pages ?></a>
                    <?php endif; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?><?= $query_base ?>" class="page-btn">Sau ›</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main>

<?php include 'footer.php'; ?>