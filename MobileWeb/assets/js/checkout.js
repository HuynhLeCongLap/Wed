/**
 * JS cho phần Thanh toán & Quản lý đơn hàng
 * - Validate form thanh toán (client-side)
 * - AJAX gửi đơn hàng
 * - AJAX hủy đơn hàng
 */

document.addEventListener('DOMContentLoaded', function () {

    // ========================================
    // 1. XỬ LÝ FORM ĐẶT HÀNG
    // ========================================
    const form = document.getElementById('checkout-form');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            // Clear lỗi cũ
            clearErrors();
            hideAlert();

            // Validate client-side trước
            if (!validateCheckoutForm()) {
                return;
            }

            // Chặn double-click
            const btn = document.getElementById('btn-place-order');
            btn.disabled = true;
            btn.textContent = 'Đang xử lý...';

            // Gửi AJAX
            const formData = new FormData(form);

            fetch('process_order.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Đặt thành công -> chuyển trang
                    showAlert('success', data.message + ' Đang chuyển đến trang xác nhận...');
                    setTimeout(() => {
                        window.location.href = 'order_success.php?id=' + data.MaHoaDon;
                    }, 1000);
                } else {
                    // Lỗi -> hiện thông báo
                    showAlert('error', data.message);
                    if (data.errors) {
                        showFieldErrors(data.errors);
                    }
                    btn.disabled = false;
                    btn.textContent = 'Đặt hàng';
                }
            })
            .catch(err => {
                showAlert('error', 'Có lỗi kết nối, vui lòng thử lại.');
                btn.disabled = false;
                btn.textContent = 'Đặt hàng';
                console.error(err);
            });
        });
    }

    // ========================================
    // 2. XỬ LÝ HỦY ĐƠN HÀNG
    // ========================================
    const btnCancel = document.getElementById('btn-cancel-order');
    if (btnCancel) {
        btnCancel.addEventListener('click', function () {
            const maHoaDon = this.dataset.id;

            // Yêu cầu xác nhận trước khi hủy
            if (!confirm('Bạn có chắc muốn hủy đơn hàng #' + maHoaDon + ' không?\nHành động này không thể hoàn tác.')) {
                return;
            }

            this.disabled = true;
            this.textContent = 'Đang hủy...';

            const formData = new FormData();
            formData.append('MaHoaDon', maHoaDon);

            fetch('cancel_order.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message + ' Đang tải lại trang...');
                    setTimeout(() => location.reload(), 1200);
                } else {
                    showAlert('error', data.message);
                    btnCancel.disabled = false;
                    btnCancel.textContent = 'Hủy đơn hàng';
                }
            })
            .catch(err => {
                showAlert('error', 'Có lỗi kết nối, vui lòng thử lại.');
                btnCancel.disabled = false;
                btnCancel.textContent = 'Hủy đơn hàng';
                console.error(err);
            });
        });
    }
});

// ========================================
// HÀM VALIDATE FORM
// ========================================
function validateCheckoutForm() {
    let ok = true;

    const hoTen = document.getElementById('hoTen').value.trim();
    const sdt   = document.getElementById('soDienThoai').value.trim();
    const dc    = document.getElementById('diaChi').value.trim();

    // Họ tên
    if (hoTen === '') {
        showFieldError('hoTen', 'Vui lòng nhập họ tên.');
        ok = false;
    } else if (hoTen.length < 2) {
        showFieldError('hoTen', 'Họ tên quá ngắn.');
        ok = false;
    }

    // SĐT - regex cho số VN
    if (sdt === '') {
        showFieldError('soDienThoai', 'Vui lòng nhập số điện thoại.');
        ok = false;
    } else if (!/^(0|\+84)[0-9]{9,10}$/.test(sdt)) {
        showFieldError('soDienThoai', 'SĐT không hợp lệ (VD: 0901234567).');
        ok = false;
    }

    // Địa chỉ
    if (dc === '') {
        showFieldError('diaChi', 'Vui lòng nhập địa chỉ.');
        ok = false;
    } else if (dc.length < 10) {
        showFieldError('diaChi', 'Vui lòng nhập đầy đủ địa chỉ (≥ 10 ký tự).');
        ok = false;
    }

    if (!ok) {
        showAlert('error', 'Vui lòng kiểm tra lại thông tin nhập.');
    }
    return ok;
}

// ========================================
// HÀM HỖ TRỢ HIỂN THỊ LỖI
// ========================================
function showFieldError(fieldName, message) {
    const el = document.getElementById('err-' + fieldName);
    if (el) {
        el.textContent = message;
        el.style.display = 'block';
    }
    const input = document.getElementById(fieldName);
    if (input) input.classList.add('input-error');
}

function showFieldErrors(errors) {
    for (const f in errors) {
        showFieldError(f, errors[f]);
    }
}

function clearErrors() {
    document.querySelectorAll('.error-msg').forEach(e => {
        e.textContent = '';
        e.style.display = 'none';
    });
    document.querySelectorAll('.input-error').forEach(e => e.classList.remove('input-error'));
}

function showAlert(type, message) {
    const box = document.getElementById('alert-box');
    if (!box) return;
    box.className = 'alert-box alert-' + type;
    box.textContent = message;
    box.classList.remove('hidden');
    // Scroll lên đầu để user thấy
    box.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function hideAlert() {
    const box = document.getElementById('alert-box');
    if (box) box.classList.add('hidden');
}
