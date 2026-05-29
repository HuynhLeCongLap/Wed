-- ============================================================
-- CẬP NHẬT DATABASE - PHIÊN BẢN 2
-- Chạy file này 1 lần trong phpMyAdmin
-- ============================================================

USE cellphone_k;

-- 1. Thêm cột xác thực email vào bảng khachhang
ALTER TABLE `khachhang`
  ADD COLUMN IF NOT EXISTS `DaXacThuc` TINYINT(1) NOT NULL DEFAULT 0 AFTER `Email`,
  ADD COLUMN IF NOT EXISTS `TokenXacThuc` VARCHAR(64) NULL AFTER `DaXacThuc`;

-- Cập nhật tài khoản demo cũ là đã xác thực
UPDATE `khachhang` SET `DaXacThuc` = 1 WHERE `TenDangNhap` = '1';

-- 2. Tạo bảng lịch sử trạng thái đơn hàng
CREATE TABLE IF NOT EXISTS `order_tracking` (
  `MaTracking` INT NOT NULL AUTO_INCREMENT,
  `MaHoaDon`   INT NOT NULL,
  `TrangThai`  VARCHAR(50) NOT NULL,
  `GhiChu`     VARCHAR(255) NULL,
  `ThoiGian`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`MaTracking`),
  KEY `MaHoaDon` (`MaHoaDon`),
  CONSTRAINT `order_tracking_ibfk_1` FOREIGN KEY (`MaHoaDon`) REFERENCES `hoadon` (`MaHoaDon`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Thêm tracking mẫu cho đơn hàng số 1
INSERT IGNORE INTO `order_tracking` (`MaHoaDon`, `TrangThai`, `GhiChu`, `ThoiGian`)
VALUES (1, 'Chưa xác nhận', 'Đơn hàng vừa được đặt', '2025-06-27 10:00:00');

-- 3. Thêm cột thời gian cập nhật vào hoadon nếu chưa có
ALTER TABLE `hoadon`
  ADD COLUMN IF NOT EXISTS `HoTenNhan` VARCHAR(255) NULL AFTER `TenDangNhap`,
  ADD COLUMN IF NOT EXISTS `SoDienThoaiNhan` VARCHAR(15) NULL AFTER `HoTenNhan`,
  ADD COLUMN IF NOT EXISTS `DiaChiNhan` VARCHAR(500) NULL AFTER `SoDienThoaiNhan`,
  ADD COLUMN IF NOT EXISTS `GhiChu` TEXT NULL AFTER `DiaChiNhan`,
  ADD COLUMN IF NOT EXISTS `PhuongThucThanhToan` VARCHAR(50) NULL DEFAULT 'COD' AFTER `GhiChu`;

-- 4. Bảng lưu OTP đăng nhập
CREATE TABLE IF NOT EXISTS `login_otp` (
  `id`           INT NOT NULL AUTO_INCREMENT,
  `TenDangNhap`  VARCHAR(50) NOT NULL,
  `OtpCode`      VARCHAR(6)  NOT NULL,
  `ExpiresAt`    DATETIME    NOT NULL,
  `Used`         TINYINT(1)  NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `TenDangNhap` (`TenDangNhap`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Thêm tài khoản admin mặc định (nếu chưa có)
INSERT IGNORE INTO `admin_inf` (`TenDangNhap`, `MatKhau`, `HoTen`, `Email`)
VALUES ('admin', 'admin123', 'Quản trị viên', 'admin@mobileweb.vn');

-- 6. Cập nhật ảnh sản phẩm (từ update_images.sql — đã gộp vào đây)
-- iPhone
UPDATE image SET DiaChiAnh = 'img/iphone/iphone-13.jpg'          WHERE MaSanPham = 43;
UPDATE image SET DiaChiAnh = 'img/iphone/iphone-14.jpg'          WHERE MaSanPham = 44;
UPDATE image SET DiaChiAnh = 'img/iphone/iphone-15.jpg'          WHERE MaSanPham = 45;
UPDATE image SET DiaChiAnh = 'img/iphone/iphone-14-plus.jpg'     WHERE MaSanPham = 46;
UPDATE image SET DiaChiAnh = 'img/iphone/iphone-15-plus.jpg'     WHERE MaSanPham = 47;
UPDATE image SET DiaChiAnh = 'img/iphone/iphone-15-pro.jpg'      WHERE MaSanPham = 48;
UPDATE image SET DiaChiAnh = 'img/iphone/iphone-14-pro.jpg'      WHERE MaSanPham = 49;
UPDATE image SET DiaChiAnh = 'img/iphone/iphone-15-pro-max.jpg'  WHERE MaSanPham = 50;
-- Samsung
UPDATE image SET DiaChiAnh = 'img/samsung/dien_thoai_S24_ULTRA.jpg'              WHERE MaSanPham = 1;
UPDATE image SET DiaChiAnh = 'img/samsung/samsung-galaxy-s22-ultra.jpg'           WHERE MaSanPham = 2;
UPDATE image SET DiaChiAnh = 'img/samsung/samsung-s23.jpg'                        WHERE MaSanPham = 3;
UPDATE image SET DiaChiAnh = 'img/samsung/samsung-galaxy-z-fold-6.jpg'            WHERE MaSanPham = 4;
UPDATE image SET DiaChiAnh = 'img/samsung/samsung-galaxy-a55.jpg'                 WHERE MaSanPham = 5;
UPDATE image SET DiaChiAnh = 'img/samsung/dien-thoai-samsung-galaxy-a36.jpg'      WHERE MaSanPham = 6;
UPDATE image SET DiaChiAnh = 'img/samsung/samsung-galaxy-a55.jpg'                 WHERE MaSanPham = 7;
UPDATE image SET DiaChiAnh = 'img/samsung/dien-thoai-samsung-galaxy-a06.jpg'      WHERE MaSanPham = 8;
UPDATE image SET DiaChiAnh = 'img/samsung/samsung-galaxy-a55.jpg'                 WHERE MaSanPham = 9;
UPDATE image SET DiaChiAnh = 'img/samsung/dien-thoai-samsung-galaxy-a26.jpg'      WHERE MaSanPham = 10;
-- Đồng bộ giỏ hàng
UPDATE giohang SET DiaChiAnh = 'img/samsung/dien_thoai_S24_ULTRA.jpg'    WHERE MaSanPham = 1;
UPDATE giohang SET DiaChiAnh = 'img/samsung/samsung-galaxy-s22-ultra.jpg' WHERE MaSanPham = 2;
UPDATE giohang SET DiaChiAnh = 'img/samsung/samsung-s23.jpg'              WHERE MaSanPham = 3;
UPDATE giohang SET DiaChiAnh = 'img/samsung/samsung-galaxy-z-fold-6.jpg'  WHERE MaSanPham = 4;
UPDATE giohang SET DiaChiAnh = 'img/samsung/samsung-galaxy-a55.jpg'       WHERE MaSanPham IN (5,7,9);
UPDATE giohang SET DiaChiAnh = 'img/samsung/dien-thoai-samsung-galaxy-a36.jpg' WHERE MaSanPham = 6;
UPDATE giohang SET DiaChiAnh = 'img/samsung/dien-thoai-samsung-galaxy-a06.jpg' WHERE MaSanPham = 8;
UPDATE giohang SET DiaChiAnh = 'img/samsung/dien-thoai-samsung-galaxy-a26.jpg' WHERE MaSanPham = 10;
UPDATE giohang SET DiaChiAnh = 'img/iphone/iphone-13.jpg'         WHERE MaSanPham = 43;
UPDATE giohang SET DiaChiAnh = 'img/iphone/iphone-14.jpg'         WHERE MaSanPham = 44;
UPDATE giohang SET DiaChiAnh = 'img/iphone/iphone-15.jpg'         WHERE MaSanPham = 45;
UPDATE giohang SET DiaChiAnh = 'img/iphone/iphone-14-plus.jpg'    WHERE MaSanPham = 46;
UPDATE giohang SET DiaChiAnh = 'img/iphone/iphone-15-plus.jpg'    WHERE MaSanPham = 47;
UPDATE giohang SET DiaChiAnh = 'img/iphone/iphone-15-pro.jpg'     WHERE MaSanPham = 48;
UPDATE giohang SET DiaChiAnh = 'img/iphone/iphone-14-pro.jpg'     WHERE MaSanPham = 49;
UPDATE giohang SET DiaChiAnh = 'img/iphone/iphone-15-pro-max.jpg' WHERE MaSanPham = 50;
