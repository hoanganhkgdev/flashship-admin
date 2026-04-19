# 🧪 Kịch Bản Test AI FlashShip — Đối Tượng: KHÁCH LẺ

> **Điểm khác biệt cốt lõi so với Shop:**
> - Phải đủ **4 thông tin**: `pickup_address` + `pickup_phone` + `delivery_address` + `delivery_phone`
> - AI **KHÔNG được tạo đơn** khi còn thiếu bất kỳ trường nào
> - AI **KHÔNG được bịa** địa chỉ hay SĐT
> - Sau khi tạo đơn, AI gợi ý nhắn `"Menu"` để đặt thêm

---

## 📋 DANH MỤC

| # | Nhóm Test | Số Kịch Bản |
|---|-----------|-------------|
| 1 | 🚚 Giao Hàng (delivery) | 6 |
| 2 | 🛒 Mua Hộ (shopping) | 4 |
| 3 | 🛵 Xe Ôm (bike) | 3 |
| 4 | 🏍️ Lái Hộ Xe Máy (motor) | 3 |
| 5 | 🚗 Lái Hộ Ô Tô (car) | 2 |
| 6 | 💳 Nạp Tiền (topup) | 4 |
| 7 | ⭐ Tình Huống Đặc Biệt | 8 |

---

## 🚚 NHÓM 1: GIAO HÀNG (delivery) — Khách Lẻ

> **Bắt buộc đủ 4 trường:** `pickup_address` + `pickup_phone` + `delivery_address` + `delivery_phone`

### TC-LD01 | Happy path — đủ thông tin 1 tin

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 👤 Khách | `ship từ 10 Lê Lợi SĐT 0901112233 đến 55 Nguyễn Trung Trực SĐT 0987654321` |
| 1 | 🤖 AI | ✅ `create_order` đủ 4 thông tin → Xác nhận đơn |

**Kỳ vọng:**
- ✅ Tạo đơn ngay, không hỏi thêm
- ✅ Confirm dạng "**Đơn giao hàng**" (không phải "Đơn cửa hàng")
- ✅ Cuối có gợi ý nhắn `"Menu"`
- ✅ `shop_id` = null trong DB

---

### TC-LD02 | Thiếu SĐT người gửi

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 👤 Khách | `ship từ 10 Lê Lợi đến 55 NTT SĐT 0987654321` |
| 1 | 🤖 AI | ❓ Hỏi SĐT người gửi |
| 2 | 👤 Khách | `0901112233` |
| 2 | 🤖 AI | ✅ `create_order` đủ thông tin |

---

### TC-LD03 | Thiếu địa chỉ lấy hàng

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 👤 Khách | `giao hàng đến 22 Nguyễn Huệ SĐT 0977889900 SĐT mình 0911223344` |
| 1 | 🤖 AI | ❓ Hỏi địa chỉ lấy hàng |
| 2 | 👤 Khách | `lấy ở 5 Trần Phú` |
| 2 | 🤖 AI | ✅ `create_order` đủ thông tin |

---

### TC-LD04 | Thiếu nhiều thông tin — hỏi từng bước

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 👤 Khách | `ship cái này cho bạn tôi` |
| 1 | 🤖 AI | ❓ Hỏi địa chỉ lấy + SĐT người gửi |
| 2 | 👤 Khách | `lấy 30 Lý Tự Trọng SĐT 0933445566` |
| 2 | 🤖 AI | ❓ Hỏi địa chỉ + SĐT người nhận |
| 3 | 👤 Khách | `giao 88 Trần Hưng Đạo SĐT 0944556677` |
| 3 | 🤖 AI | ✅ `create_order` đủ thông tin |

**Kỳ vọng:** AI nhớ thông tin từ các lượt trước, không hỏi lại

---

### TC-LD05 | Viết tắt / không dấu

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 👤 Khách | `ship tu 10 le loi sdt 0901 den 55 ntt sdt 0987` |
| 1 | 🤖 AI | ✅ Nhận diện được → `create_order` |

---

### TC-LD06 | Hỏi giá rồi đặt đơn

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 👤 Khách | `ship từ 10 Lê Lợi đến bệnh viện tỉnh bao nhiêu tiền` |
| 1 | 🤖 AI | ✅ `calculate_fee(pickup=10 Lê Lợi, delivery=BV tỉnh)` → Báo giá |
| 2 | 👤 Khách | `ok ship cho mình, SĐT gửi 0901112233 SĐT nhận 0987654321` |
| 2 | 🤖 AI | ✅ `create_order` (nhớ địa chỉ từ lượt 1, chỉ thêm SĐT) |

---

## 🛒 NHÓM 2: MUA HỘ (shopping) — Khách Lẻ

### TC-LS01 | Mua hộ đủ thông tin

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 👤 Khách | `mua hộ ở Big C 5 cái bánh giao về 22 Nguyễn Bình Khiêm SĐT 0911223344 SĐT mình 0922334455` |
| 1 | 🤖 AI | ✅ `create_order(shopping, pickup=Big C, delivery=22 NBK, items=5 cái bánh)` |

---

### TC-LS02 | Thiếu địa chỉ tiệm mua

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 👤 Khách | `mua 10 tờ giấy A4 giao về 15 Lê Duẩn SĐT 0933221144 SĐT mình 0944332255` |
| 1 | 🤖 AI | ❓ "Dạ, anh/chị mua ở **tiệm/địa chỉ nào** ạ?" |
| 2 | 👤 Khách | `tiệm VPP Minh Châu đường Lê Hồng Phong` |
| 2 | �� AI | ✅ `create_order` đủ thông tin |

---

### TC-LS03 | Mua hộ hỏi giá

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 👤 Khách | `mua hộ từ chợ Rạch Sỏi về 22 NTT bao nhiêu tiền` |
| 1 | 🤖 AI | ✅ `calculate_fee(pickup=Chợ Rạch Sỏi, delivery=22 NTT, service_type=shopping)` |

---

### TC-LS04 | Mua hộ nhiều mặt hàng

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 👤 Khách | `mua hộ ở Co.opmart: 2kg đường, 1 lít dầu, 3 gói mì, giao 88 NTT SĐT 0900112233 SĐT mình 0911223344` |
| 1 | 🤖 AI | ✅ `create_order(items=2kg đường, 1 lít dầu, 3 gói mì)` — ghi đầy đủ |

---

## 🛵 NHÓM 3: XE ÔM (bike) — Khách Lẻ

### TC-LB01 | Đặt xe ôm đủ thông tin

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 👤 Khách | `đặt xe ôm đón tôi ở 10 Trần Phú SĐT 0901234567 đến bệnh viện đa khoa` |
| 1 | 🤖 AI | ✅ `create_order(bike, pickup=10 Trần Phú, pickup_phone=0901234567, delivery=BV ĐK, delivery_phone=0901234567)` |

**Kỳ vọng:** `pickup_phone` = `delivery_phone` = SĐT khách (xe ôm 1 người)

---

### TC-LB02 | Xe ôm hỏi giá

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 👤 Khách | `xe ôm từ 5 Lê Lai đến sân bay bao nhiêu` |
| 1 | 🤖 AI | ✅ `calculate_fee(pickup=5 Lê Lai, delivery=Sân bay Rạch Giá, service_type=bike)` |

---

### TC-LB03 | Thiếu điểm đến

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 👤 Khách | `cho tôi xe ôm tại 20 Nguyễn Huệ SĐT 0933445566` |
| 1 | 🤖 AI | ❓ "Dạ, anh/chị cần đến **đâu** ạ?" |
| 2 | 👤 Khách | `đến trường đại học kiên giang` |
| 2 | 🤖 AI | ✅ `create_order(bike, ...)` |

---

## 🏍️ NHÓM 4: LÁI HỘ XE MÁY (motor) — Khách Lẻ

### TC-LM01 | Nói rõ "xe máy" → tạo đơn ngay

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 👤 Khách | `lái xe máy hộ từ 15 Lý Thường Kiệt SĐT 0900123456 đến 80 Ngô Quyền SĐT 0900123456` |
| 1 | 🤖 AI | ✅ `create_order(motor, ...)` không hỏi loại xe |

---

### TC-LM02 | "Lái xe hộ" chưa rõ loại → Phải hỏi

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 👤 Khách | `lái xe hộ từ 10 Lê Lợi SĐT 0911332244 đến 55 Phan Đình Phùng SĐT 0911332244` |
| 1 | 🤖 AI | ❓ "Dạ, lái **xe máy** hay **ô tô** ạ?" |
| 2 | 👤 Khách | `xe máy` |
| 2 | 🤖 AI | ✅ `create_order(motor, ...)` |

---

### TC-LM03 | Hỏi giá lái xe máy hộ liên tỉnh

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 👤 Khách | `lái xe máy hộ từ Rạch Giá đến Hà Tiên bao nhiêu` |
| 1 | 🤖 AI | ✅ `calculate_fee(service_type=motor, ...)` → Báo giá theo km thực |

---

## 🚗 NHÓM 5: LÁI HỘ Ô TÔ (car) — Khách Lẻ

### TC-LC01 | Nói rõ "ô tô" → tạo đơn ngay

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 👤 Khách | `lái ô tô hộ từ 3 Đinh Tiên Hoàng SĐT 0955443322 đến bến xe Rạch Giá SĐT 0955443322` |
| 1 | 🤖 AI | ✅ `create_order(car, ...)` |

---

### TC-LC02 | "Lái xe hộ" → Hỏi → Chọn ô tô

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 👤 Khách | `lái xe giúp mình từ nhà đến bệnh viện, nhà ở 20 Lê Thánh Tông SĐT 0966778899` |
| 1 | 🤖 AI | ❓ "Dạ, lái **xe máy** hay **ô tô** ạ?" |
| 2 | 👤 Khách | `ô tô` |
| 2 | 🤖 AI | ✅ `create_order(car, ...)` |

---

## 💳 NHÓM 6: NẠP TIỀN (topup) — Khách Lẻ

### TC-LT01 | Nạp tiền đủ thông tin

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 👤 Khách | `nạp 200k cho tôi ở 22 Nguyễn Trung Trực SĐT 0911223344` |
| 1 | 🤖 AI | ✅ `create_order(topup, delivery=22 NTT, phone=0911223344, items=Nạp tiền 200k)` |

**Kỳ vọng:** Không hỏi loại nạp (điện thoại/ngân hàng)

---

### TC-LT02 | Thiếu số tiền — Phải hỏi

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 👤 Khách | `nạp tiền điện thoại cho tôi ở 5 Lê Hồng Phong SĐT 0944556677` |
| 1 | 🤖 AI | ❓ "Dạ, anh/chị cần nạp **bao nhiêu tiền** ạ?" |
| 2 | 👤 Khách | `100k` |
| 2 | 🤖 AI | ✅ `create_order(topup, items=Nạp tiền 100k)` |

**Kỳ vọng:** Không hỏi loại nạp (dù khách đề cập "điện thoại")

---

### TC-LT03 | Số tiền dạng triệu

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 👤 Khách | `nạp 2tr cho tôi ở 30 Trần Phú SĐT 0900998877` |
| 1 | 🤖 AI | ✅ `create_order(topup, items=Nạp tiền 2tr)` (parse đúng 2,000,000đ) |

---

### TC-LT04 | Hỏi giá nạp tiền

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 👤 Khách | `nạp 500k phí bao nhiêu` |
| 1 | 🤖 AI | ✅ `calculate_fee(topup, amount=500k)` → Báo phí, không hỏi địa chỉ |

---

## ⭐ NHÓM 7: TÌNH HUỐNG ĐẶC BIỆT

### TC-LX01 | Hủy đơn vừa tạo (pending)
| L | Người | Nội dung |
|---|-------|----------|
| 1 | 👤 | `ship từ 10 Lê Lợi SĐT 0901 đến 55 NTT SĐT 0987` |
| 1 | 🤖 | ✅ Tạo đơn #XXX |
| 2 | 👤 | `hủy đơn đó đi` |
| 2 | 🤖 | ✅ `cancel_order()` → Hủy thành công |

---

### TC-LX02 | Kiểm tra trạng thái đơn
| L | Người | Nội dung |
|---|-------|----------|
| 1 | 👤 | `đơn của tôi tới đâu rồi` |
| 1 | 🤖 | ✅ `get_order_status()` → Trạng thái + thông tin tài xế (nếu có) |

---

### TC-LX03 | Reset phiên hội thoại
| L | Người | Nội dung |
|---|-------|----------|
| 1 | 👤 | `hủy` / `xóa` / `reset` |
| 1 | 🤖 | ✅ Xóa session → "Đã xóa thông tin cũ..." |

---

### TC-LX04 | 2 đơn liên tiếp — không lẫn địa chỉ
| L | Người | Nội dung |
|---|-------|----------|
| 1 | 👤 | `ship từ 10 Lê Lợi SĐT 0901 đến 22 NTT SĐT 0911` |
| 1 | 🤖 | ✅ Tạo đơn #1 |
| 2 | 👤 | `ship thêm từ 5 Trần Phú SĐT 0922 đến 77 Lê Duẩn SĐT 0933` |
| 2 | 🤖 | ✅ Tạo đơn #2 với đúng địa chỉ riêng |

---

### TC-LX05 | Mất hàng — Escalate HIGH
| L | Người | Nội dung |
|---|-------|----------|
| 1 | 👤 | `tài xế làm mất hàng của tôi, mất 500k ai đền không` |
| 1 | 🤖 | ✅ `escalate_to_manager(urgency=high)` |

---

### TC-LX06 | Hỏi thông tin dịch vụ — Escalate LOW
| L | Người | Nội dung |
|---|-------|----------|
| 1 | 👤 | `flashship có giao buổi tối không vậy` |
| 1 | �� | ✅ Trả lời hoặc `escalate_to_manager(urgency=low)` |

---

### TC-LX07 | Tin nhắn mơ hồ — Hỏi lại dịch vụ
| L | Người | Nội dung |
|---|-------|----------|
| 1 | 👤 | `cho tôi đặt 1 xe` |
| 1 | 🤖 | ❓ "Dạ, anh/chị cần xe ôm / lái hộ xe máy / lái hộ ô tô ạ?" |

---

### TC-LX08 | Lệnh "Menu"
| L | Người | Nội dung |
|---|-------|----------|
| 1 | 👤 | `menu` |
| 1 | 🤖 | ✅ Hiển thị menu dịch vụ |

---

## 📊 So Sánh Khách Lẻ vs Khách Shop

| Điểm | 👤 Khách Lẻ | 🏪 Khách Shop |
|------|------------|--------------|
| **pickup_address** | ❗ Bắt buộc hỏi | ✅ Tự điền = địa chỉ Shop |
| **pickup_phone** | ❗ Bắt buộc hỏi | ✅ Tự điền = SĐT Shop |
| **delivery_address** | ❗ Bắt buộc hỏi | ⏳ Có thể "Sẽ cung cấp sau" |
| **delivery_phone** | ❗ Bắt buộc hỏi | ⏳ Có thể "Sẽ cung cấp sau" |
| **Số thông tin tối thiểu** | 4 trường | 2 trường |
| **Gợi ý "Menu" sau đặt đơn** | ✅ Có | ❌ Không |
| **`shop_id` trong DB** | `null` | ID shop cụ thể |

---

## 🔴 Negative Tests — AI CẤM Làm với Khách Lẻ

| # | Cấm | Tình Huống |
|---|-----|------------|
| 1 | ❌ Tạo đơn thiếu `pickup_address` | Chỉ có delivery |
| 2 | ❌ Tạo đơn thiếu `pickup_phone` | Chỉ có delivery_phone |
| 3 | ❌ Bịa địa chỉ / SĐT | Bất kỳ tình huống thiếu |
| 4 | ❌ Tạo đơn "lái xe hộ" chưa rõ loại | "lái xe từ A đến B" |
| 5 | ❌ Hỏi loại nạp tiền | Topup bất kỳ |
| 6 | ❌ Lẫn địa chỉ 2 đơn liên tiếp | TC-LX04 |
| 7 | ❌ Hủy đơn đã `completed` | "hủy đơn hôm qua" |
| 8 | ❌ Nhận diện là "khách shop" khi không có `zalo_id` | Kiểm tra DB |

---

## 🏁 Checklist Trước Khi Test

- [ ] Queue worker đang chạy (`php artisan queue:work`)
- [ ] Zalo webhook active (`valet share`)
- [ ] **Tài khoản Zalo test KHÔNG có `zalo_id` trong bảng `shops`** → hệ thống nhận là khách lẻ
- [ ] Clear cache (`php artisan cache:clear`)

---

> 📅 Ngày tạo: 2026-03-14 | Phiên bản: 1.0
