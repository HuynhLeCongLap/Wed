-- ============================================================
-- BỔ SUNG CHO PHẦN THANH TOÁN
-- Chạy file này 1 lần để thêm các cột cần thiết vào bảng hoadon
-- ============================================================

USE cellphone_k;

-- Thêm các trường thông tin người nhận và phương thức thanh toán
ALTER TABLE `hoadon`
  ADD COLUMN `HoTenNhan` VARCHAR(255) NULL AFTER `TenDangNhap`,
  ADD COLUMN `SoDienThoaiNhan` VARCHAR(15) NULL AFTER `HoTenNhan`,
  ADD COLUMN `DiaChiNhan` VARCHAR(500) NULL AFTER `SoDienThoaiNhan`,
  ADD COLUMN `GhiChu` TEXT NULL AFTER `DiaChiNhan`,
  ADD COLUMN `PhuongThucThanhToan` VARCHAR(50) NULL DEFAULT 'COD' AFTER `GhiChu`;

-- Cập nhật dữ liệu mẫu cũ (đơn hàng số 1) để khỏi NULL
UPDATE `hoadon`
SET `HoTenNhan` = 'Huỳnh Lê Công Lập',
    `SoDienThoaiNhan` = '0764806087',
    `DiaChiNhan` = '123 NTH',
    `PhuongThucThanhToan` = 'COD'
WHERE `MaHoaDon` = 1;
