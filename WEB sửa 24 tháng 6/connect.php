<?php
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "cellphone_k";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Không kết nối được với MySQL: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

date_default_timezone_set('Asia/Ho_Chi_Minh');
$conn->query("SET time_zone = '+07:00'");

$migrations = [

    // 1. OTP đăng nhập
    "CREATE TABLE IF NOT EXISTS `otp_logs` (
        `otp_id`     INT NOT NULL AUTO_INCREMENT,
        `ma_khach`   INT NOT NULL,
        `otp_code`   VARCHAR(10) NOT NULL,
        `otp_type`   ENUM('login','register','forgot') DEFAULT 'login',
        `is_used`    TINYINT(1) DEFAULT 0,
        `attempts`   TINYINT DEFAULT 0,
        `ip_address` VARCHAR(45),
        `expired_at` DATETIME NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`otp_id`),
        KEY `idx_otp_lookup` (`ma_khach`,`otp_code`,`is_used`,`expired_at`),
        CONSTRAINT `fk_otp_khach` FOREIGN KEY (`ma_khach`)
            REFERENCES `khachhang` (`MaKhachHang`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // 2. Thanh toán QR
    "CREATE TABLE IF NOT EXISTS `qr_payments` (
        `qr_id`            INT NOT NULL AUTO_INCREMENT,
        `ma_hoa_don`       INT NOT NULL,
        `bank_code`        VARCHAR(20) DEFAULT 'VCB',
        `account_number`   VARCHAR(30) DEFAULT '1234567890',
        `account_name`     VARCHAR(150) DEFAULT 'CONG TY TNHH CELLPHONEK',
        `amount`           DECIMAL(15,2) NOT NULL,
        `transfer_content` VARCHAR(100) NOT NULL,
        `qr_image_url`     VARCHAR(500),
        `status`           ENUM('pending','verified','expired','failed') DEFAULT 'pending',
        `expired_at`       DATETIME,
        `verified_at`      DATETIME,
        `verified_by`      INT DEFAULT NULL,
        `created_at`       DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`qr_id`),
        UNIQUE KEY `uk_qr_hoadon` (`ma_hoa_don`),
        KEY `idx_qr_status` (`status`,`expired_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // 3. Cấu hình hạng VIP
    "CREATE TABLE IF NOT EXISTS `vip_tiers` (
        `tier_id`      INT NOT NULL AUTO_INCREMENT,
        `tier_name`    ENUM('Bronze','Silver','Gold','Platinum','Diamond') UNIQUE NOT NULL,
        `tier_level`   INT NOT NULL,
        `min_spent`    DECIMAL(15,2) DEFAULT 0,
        `min_orders`   INT DEFAULT 0,
        `discount_pct` DECIMAL(5,2) DEFAULT 0,
        `badge_color`  VARCHAR(20) DEFAULT '#CD7F32',
        `badge_icon`   VARCHAR(10) DEFAULT '🥉',
        `description`  VARCHAR(255),
        PRIMARY KEY (`tier_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // 4. Lịch sử thay đổi hạng VIP
    "CREATE TABLE IF NOT EXISTS `vip_history` (
        `history_id` INT NOT NULL AUTO_INCREMENT,
        `ma_khach`   INT NOT NULL,
        `old_tier`   VARCHAR(20),
        `new_tier`   VARCHAR(20),
        `reason`     VARCHAR(100) DEFAULT 'auto',
        `changed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`history_id`),
        CONSTRAINT `fk_vip_hist_khach` FOREIGN KEY (`ma_khach`)
            REFERENCES `khachhang` (`MaKhachHang`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // 5. Chương trình khuyến mãi VIP
    "CREATE TABLE IF NOT EXISTS `vip_promotions` (
        `promo_id`        INT NOT NULL AUTO_INCREMENT,
        `promo_name`      VARCHAR(255) NOT NULL,
        `min_tier`        ENUM('Bronze','Silver','Gold','Platinum','Diamond') DEFAULT 'Silver',
        `discount_type`   ENUM('percent','fixed') DEFAULT 'percent',
        `discount_value`  DECIMAL(10,2) NOT NULL,
        `max_discount`    DECIMAL(15,2) DEFAULT NULL,
        `min_order_value` DECIMAL(15,2) DEFAULT 0,
        `start_date`      DATE NOT NULL,
        `end_date`        DATE NOT NULL,
        `is_active`       TINYINT(1) DEFAULT 1,
        `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`promo_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // 6. Lịch sử sử dụng khuyến mãi
    "CREATE TABLE IF NOT EXISTS `promotion_usage` (
        `usage_id`        INT NOT NULL AUTO_INCREMENT,
        `promo_id`        INT NOT NULL,
        `ma_khach`        INT NOT NULL,
        `ma_hoa_don`      INT NOT NULL,
        `discount_amount` DECIMAL(15,2),
        `used_at`         DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`usage_id`),
        UNIQUE KEY `uk_promo_order` (`promo_id`,`ma_hoa_don`),
        CONSTRAINT `fk_pu_promo` FOREIGN KEY (`promo_id`)
            REFERENCES `vip_promotions` (`promo_id`),
        CONSTRAINT `fk_pu_khach` FOREIGN KEY (`ma_khach`)
            REFERENCES `khachhang` (`MaKhachHang`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // 7. Danh mục sản phẩm
    "CREATE TABLE IF NOT EXISTS `categories` (
        `MaDanhMuc` INT NOT NULL AUTO_INCREMENT,
        `TenDanhMuc` VARCHAR(100) NOT NULL,
        `MoTa` TEXT,
        PRIMARY KEY (`MaDanhMuc`),
        UNIQUE KEY `uk_category_name` (`TenDanhMuc`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // 8. Nhà cung cấp
    "CREATE TABLE IF NOT EXISTS `suppliers` (
        `MaNCC` INT NOT NULL AUTO_INCREMENT,
        `TenNCC` VARCHAR(150) NOT NULL,
        `Email` VARCHAR(150),
        `DienThoai` VARCHAR(30),
        `DiaChi` VARCHAR(255),
        `GhiChu` TEXT,
        PRIMARY KEY (`MaNCC`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // 9. Phiếu nhập kho
    "CREATE TABLE IF NOT EXISTS `purchase_orders` (
        `MaPhieu` INT NOT NULL AUTO_INCREMENT,
        `MaNCC` INT NOT NULL,
        `MaSanPham` INT NOT NULL,
        `SoLuongNhap` INT NOT NULL DEFAULT 0,
        `GiaNhap` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `NgayNhap` DATE NOT NULL,
        `GhiChu` VARCHAR(255),
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`MaPhieu`),
        CONSTRAINT `fk_po_supplier` FOREIGN KEY (`MaNCC`) REFERENCES `suppliers`(`MaNCC`),
        CONSTRAINT `fk_po_sanpham` FOREIGN KEY (`MaSanPham`) REFERENCES `sanpham`(`MaSanPham`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // 10. Coupon / mã giảm giá
    "CREATE TABLE IF NOT EXISTS `coupons` (
        `CouponID` INT NOT NULL AUTO_INCREMENT,
        `Code` VARCHAR(50) NOT NULL,
        `Description` VARCHAR(255),
        `DiscountType` ENUM('percent','fixed') DEFAULT 'percent',
        `DiscountValue` DECIMAL(10,2) NOT NULL,
        `MinOrderValue` DECIMAL(15,2) DEFAULT 0,
        `StartDate` DATE NOT NULL,
        `EndDate` DATE NOT NULL,
        `IsActive` TINYINT(1) DEFAULT 1,
        `CreatedAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`CouponID`),
        UNIQUE KEY `uk_coupon_code` (`Code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // 11. Cấu hình website
    "CREATE TABLE IF NOT EXISTS `system_settings` (
        `SettingKey` VARCHAR(100) NOT NULL,
        `SettingValue` TEXT,
        `UpdatedAt` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`SettingKey`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // 12. Nhật ký hành động admin
    "CREATE TABLE IF NOT EXISTS `admin_logs` (
        `LogID` INT NOT NULL AUTO_INCREMENT,
        `MaAdmin` INT DEFAULT NULL,
        `ActionType` VARCHAR(100) NOT NULL,
        `TargetType` VARCHAR(100),
        `TargetID` VARCHAR(100),
        `Notes` TEXT,
        `CreatedAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`LogID`),
        CONSTRAINT `fk_admin_logs_admin` FOREIGN KEY (`MaAdmin`) REFERENCES `admin_inf`(`MaAdmin`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // 13. Lịch sử trạng thái đơn hàng
    "CREATE TABLE IF NOT EXISTS `order_status_history` (
        `HistoryID` INT NOT NULL AUTO_INCREMENT,
        `MaHoaDon` INT NOT NULL,
        `OldStatus` VARCHAR(100),
        `NewStatus` VARCHAR(100),
        `ChangedBy` INT DEFAULT NULL,
        `ChangedAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`HistoryID`),
        CONSTRAINT `fk_order_status_order` FOREIGN KEY (`MaHoaDon`) REFERENCES `hoadon`(`MaHoaDon`),
        CONSTRAINT `fk_order_status_admin` FOREIGN KEY (`ChangedBy`) REFERENCES `admin_inf`(`MaAdmin`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

foreach ($migrations as $sql) {
    $conn->query($sql);
}

// Đảm bảo các cột bổ sung tồn tại
$checkColumn = $conn->query("SHOW COLUMNS FROM admin_inf LIKE 'Role'");
if ($checkColumn && $checkColumn->num_rows === 0) {
    $conn->query("ALTER TABLE admin_inf ADD COLUMN `Role` VARCHAR(50) DEFAULT 'SuperAdmin'");
}
$checkColumn = $conn->query("SHOW COLUMNS FROM sanpham LIKE 'DanhMuc'");
if ($checkColumn && $checkColumn->num_rows === 0) {
    $conn->query("ALTER TABLE sanpham ADD COLUMN `DanhMuc` VARCHAR(100) DEFAULT 'Điện thoại'");
}
$checkColumn = $conn->query("SHOW COLUMNS FROM feedback LIKE 'is_visible'");
if ($checkColumn && $checkColumn->num_rows === 0) {
    $conn->query("ALTER TABLE feedback ADD COLUMN `is_visible` TINYINT(1) DEFAULT 1");
}
$checkColumn = $conn->query("SHOW COLUMNS FROM giasanpham LIKE 'MaNhaCungCap'");
if ($checkColumn && $checkColumn->num_rows === 0) {
    $conn->query("ALTER TABLE giasanpham ADD COLUMN `MaNhaCungCap` INT DEFAULT NULL");
}

// Seed dữ liệu mặc định vip_tiers (chỉ insert nếu bảng rỗng)
$check = $conn->query("SELECT COUNT(*) as cnt FROM vip_tiers");
if ($check && $check->fetch_assoc()['cnt'] == 0) {
    $conn->query("INSERT INTO vip_tiers
        (tier_name, tier_level, min_spent, min_orders, discount_pct, badge_color, badge_icon, description)
        VALUES
        ('Bronze',   1,         0,         0,   0,  '#CD7F32','🥉','Thành viên mới'),
        ('Silver',   2,   5000000,         5,   5,  '#9CA3AF','🥈','Chi tiêu từ 5 triệu hoặc 5+ đơn'),
        ('Gold',     3,  20000000,        16,  10,  '#F59E0B','🥇','Chi tiêu từ 20 triệu hoặc 16+ đơn'),
        ('Platinum', 4,  50000000,        31,  15,  '#6B7280','💎','Chi tiêu từ 50 triệu hoặc 31+ đơn'),
        ('Diamond',  5, 100000000,        51,  20,  '#06B6D4','💠','Chi tiêu từ 100 triệu hoặc 51+ đơn')");
}

// Seed dữ liệu mặc định vip_promotions (chỉ insert nếu bảng rỗng)
$check2 = $conn->query("SELECT COUNT(*) as cnt FROM vip_promotions");
if ($check2 && $check2->fetch_assoc()['cnt'] == 0) {
    $conn->query("INSERT INTO vip_promotions
        (promo_name, min_tier, discount_type, discount_value, start_date, end_date)
        VALUES
        ('Ưu đãi Silver 5%',    'Silver',   'percent',  5, '2026-01-01', '2099-12-31'),
        ('Ưu đãi Gold 10%',     'Gold',     'percent', 10, '2026-01-01', '2099-12-31'),
        ('Ưu đãi Platinum 15%', 'Platinum', 'percent', 15, '2026-01-01', '2099-12-31'),
        ('Ưu đãi Diamond 20%',  'Diamond',  'percent', 20, '2026-01-01', '2099-12-31')");
}

// Thêm cột TrangThaiThanhToan vào hoadon nếu chưa có
$colCheck = $conn->query("SHOW COLUMNS FROM hoadon LIKE 'TrangThaiThanhToan'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE hoadon ADD COLUMN `TrangThaiThanhToan` VARCHAR(30) DEFAULT 'chua_thanh_toan'");
}

// Đảm bảo HangThanhVien mặc định = 'Bronze' cho khách hàng cũ
$conn->query("UPDATE khachhang SET HangThanhVien='Bronze' WHERE HangThanhVien IS NULL OR HangThanhVien=''");
