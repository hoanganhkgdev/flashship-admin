# 🧪 Kịch Bản Test AI FlashShip — Đối Tượng: KHÁCH SHOP

> **Mục tiêu:** Test toàn diện AI xử lý đúng tình huống khi khách là **Cửa hàng** (đã có hồ sơ Shop trong hệ thống với địa chỉ, SĐT, tên shop).
>
> **Giả định Shop:** Tên: `Shop Hoa Tươi Lan Anh` | SĐT: `0901234567` | Địa chỉ: `47 Lê Lợi, Rạch Giá`

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
| 7 | ⭐ Tình Huống Đặc Biệt | 7 |

---

## 🚚 NHÓM 1: GIAO HÀNG (delivery)

> **Lưu ý:** Với Shop, `pickup_address` luôn = địa chỉ Shop, AI KHÔNG hỏi lại.

---

### TC-D01 | Đặt giao hàng đầy đủ thông tin (1 tin nhắn)

**Mô tả:** Shop nhắn đủ địa chỉ giao + SĐT người nhận → AI tạo đơn ngay.

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 🏪 Shop | `giao hộp bánh sinh nhật đến 22 Nguyễn Trung Trực SĐT 0987654321` |
| 1 | 🤖 AI | ✅ Gọi `create_order(service_type=delivery, pickup_address=47 Lê Lợi, pickup_phone=0901234567, delivery_address=22 Nguyễn Trung Trực, delivery_phone=0987654321, items=hộp bánh sinh nhật)` → Trả xác nhận đơn |

**Kỳ vọng:**
- ✅ Tool: `create_order` được gọi
- ✅ `pickup_address` = địa chỉ shop tự điền (không hỏi)
- ✅ Message xác nhận dạng "Đơn cửa hàng"
- ❌ AI không hỏi "địa chỉ lấy hàng?"

---

### TC-D02 | "Có đơn ship" — chưa có địa chỉ giao

**Mô tả:** Shop thông báo có hàng cần ship nhưng chưa nhắn địa chỉ giao → AI tạo đơn nợ địa chỉ.

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 🏪 Shop | `có đơn cần ship` |
| 1 | 🤖 AI | ✅ Gọi `create_order(delivery_address='Sẽ cung cấp sau', delivery_phone='Sẽ cung cấp sau')` |
| 2 | 🏪 Shop | `giao 100 Trần Phú SĐT 0912345678 tên Minh` |
| 2 | 🤖 AI | ✅ Gọi `update_order_address(delivery_address=100 Trần Phú, delivery_phone=0912345678)` → Xác nhận cập nhật |

**Kỳ vọng:**
- ✅ Lượt 1: Tool `create_order` với `delivery_address='Sẽ cung cấp sau'`
- ✅ Lượt 2: Tool `update_order_address` cập nhật đúng đơn vừa tạo
- ✅ Phí ship được tính lại sau khi có địa chỉ

---

### TC-D03 | Đặt giao hàng viết tắt / không dấu

**Mô tả:** Shop nhắn kiểu tắt, không dấu → AI phải hiểu đúng.

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 🏪 Shop | `ship len 50 nguyen binh khiem sdt 0977112233` |
| 1 | 🤖 AI | ✅ Gọi `create_order(delivery_address=50 Nguyễn Bình Khiêm, delivery_phone=0977112233)` |

**Kỳ vọng:**
- ✅ AI nhận diện được địa chỉ từ chữ viết tắt/không dấu
- ✅ Không hỏi lại địa chỉ lấy hàng

---

### TC-D04 | Đặt nhiều đơn giao hàng liên tiếp

**Mô tả:** Shop đặt 2 đơn giao hàng liên tiếp → AI xử lý từng đơn độc lập.

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 🏪 Shop | `ship hoa đến 12 Lý Tự Trọng SĐT 0933221144` |
| 1 | 🤖 AI | ✅ Tạo đơn #1, trả xác nhận |
| 2 | 🏪 Shop | `tiếp tục ship 1 giỏ hoa nữa đến 88 Ngô Quyền SĐT 0944332255` |
| 2 | 🤖 AI | ✅ Tạo đơn #2 mới (không lẫn với đơn #1) |

**Kỳ vọng:**
- ✅ Mỗi đơn là một order riêng biệt trong DB
- ✅ AI không nhầm địa chỉ giữa 2 đơn

---

### TC-D05 | Hỏi giá ship trước rồi đặt đơn

**Mô tả:** Shop muốn biết phí ship trước khi quyết định.

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 🏪 Shop | `ship đến chợ lớn rạch giá bao nhiêu tiền vậy` |
| 1 | 🤖 AI | ✅ Gọi `calculate_fee(pickup_address=47 Lê Lợi, delivery_address=Chợ Lớn Rạch Giá, service_type=delivery)` → Báo giá |
| 2 | 🏪 Shop | `ok ship cho anh, giao đến SĐT 0955443322` |
| 2 | 🤖 AI | ✅ Gọi `create_order` với địa chỉ đã biết từ lượt 1 |

**Kỳ vọng:**
- ✅ Lượt 1: Tool `calculate_fee` dùng Shop là pickup
- ✅ Lượt 2: AI nhớ địa chỉ từ câu trước, tạo đơn luôn

---

### TC-D06 | Đơn giao hàng thiếu SĐT người nhận

**Mô tả:** Shop quên không nhắn SĐT → AI hỏi thêm.

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 🏪 Shop | `giao bánh đến 5 Lê Hồng Phong` |
| 1 | 🤖 AI | ❓ "Dạ, anh/chị cho em xin SĐT người nhận để em đặt đơn ạ!" |
| 2 | 🏪 Shop | `0966778899` |
| 2 | 🤖 AI | ✅ Gọi `create_order` với đủ thông tin |

**Kỳ vọng:**
- ✅ AI hỏi đúng thông tin còn thiếu (SĐT)
- ✅ Không hỏi lại địa chỉ lấy hàng

---

## 🛒 NHÓM 2: MUA HỘ (shopping)

> **Lưu ý:** `pickup_address` = địa chỉ TIỆM MUA (không phải Shop). Nếu "giao về shop" thì `delivery_address` = địa chỉ Shop.

---

### TC-S01 | Mua hộ giao về shop

**Mô tả:** Shop cần mua hàng từ tiệm khác rồi giao về shop.

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 🏪 Shop | `ra chợ đầm mua 10 bó hoa hồng giao về shop` |
| 1 | 🤖 AI | ✅ Gọi `create_order(service_type=shopping, pickup_address=Chợ Đầm Rạch Giá, delivery_address=47 Lê Lợi, items=10 bó hoa hồng)` |

**Kỳ vọng:**
- ✅ `pickup_address` = Chợ Đầm (tiệm mua), KHÔNG phải địa chỉ Shop
- ✅ `delivery_address` = Địa chỉ Shop (vì đã nói "giao về shop")
- ✅ `service_type` = shopping

---

### TC-S02 | Mua hộ giao đến địa chỉ khác (không phải shop)

**Mô tả:** Shop cần mua hàng từ tiệm rồi giao thẳng đến khách.

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 🏪 Shop | `qua siêu thị big c mua 2 hộp sữa, giao đến 33 Phạm Ngũ Lão SĐT 0911223344` |
| 1 | 🤖 AI | ✅ Gọi `create_order(service_type=shopping, pickup_address=Siêu thị Big C Rạch Giá, delivery_address=33 Phạm Ngũ Lão, delivery_phone=0911223344, items=2 hộp sữa)` |

**Kỳ vọng:**
- ✅ `pickup_address` = Big C (nơi shipper đến mua hàng)
- ✅ `delivery_address` = 33 Phạm Ngũ Lão (không phải Shop)

---

### TC-S03 | Mua hộ thiếu địa chỉ tiệm mua

**Mô tả:** Shop không nói rõ mua ở đâu → AI hỏi thêm.

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 🏪 Shop | `mua hộ 5 kg gạo về shop` |
| 1 | 🤖 AI | ❓ "Dạ, anh/chị mua ở tiệm nào / địa chỉ nào ạ?" |
| 2 | 🏪 Shop | `tiệm gạo Thanh Bình đường Nguyễn Thái Học` |
| 2 | 🤖 AI | ✅ Gọi `create_order(pickup_address=Tiệm gạo Thanh Bình, Nguyễn Thái Học, delivery_address=47 Lê Lợi)` |

**Kỳ vọng:**
- ✅ AI hỏi đúng "tiệm mua ở đâu"
- ✅ Sau khi có đủ thông tin → tạo đơn luôn

---

### TC-S04 | Hỏi giá mua hộ

**Mô tả:** Shop hỏi giá trước khi đặt.

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 🏪 Shop | `mua hộ từ chợ lớn về shop bao nhiêu phí vậy` |
| 1 | 🤖 AI | ✅ Gọi `calculate_fee(pickup_address=Chợ Lớn Rạch Giá, delivery_address=47 Lê Lợi, service_type=shopping)` → Báo giá |

**Kỳ vọng:**
- ✅ `pickup_address` = Chợ Lớn (điểm mua)
- ✅ `delivery_address` = Shop

---

## 🛵 NHÓM 3: XE ÔM (bike)

> **Lưu ý:** `pickup_address` = nơi ĐÓN khách (AI hỏi nếu chưa có, KHÔNG dùng địa chỉ Shop).

---

### TC-B01 | Đặt xe ôm đầy đủ thông tin

**Mô tả:** Shop cần xe ôm cho khách, cung cấp đủ thông tin.

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 🏪 Shop | `cho xe ôm đón khách ở 47 Lê Lợi đến bệnh viện đa khoa SĐT 0933556677` |
| 1 | 🤖 AI | ✅ Gọi `create_order(service_type=bike, pickup_address=47 Lê Lợi, delivery_address=Bệnh viện Đa Khoa, delivery_phone=0933556677)` |

**Kỳ vọng:**
- ✅ `service_type` = bike
- ✅ `pickup_address` = 47 Lê Lợi (nơi đón, không phải tự điền shop)

---

### TC-B02 | Đặt xe ôm thiếu nơi đón

**Mô tả:** Shop không nói nơi đón → AI hỏi lại.

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 🏪 Shop | `cần xe ôm đến siêu thị SĐT 0944667788` |
| 1 | 🤖 AI | ❓ "Dạ, anh/chị cần đón ở địa chỉ nào ạ?" |
| 2 | 🏪 Shop | `đón ở 12 Lê Lai` |
| 2 | 🤖 AI | ✅ Gọi `create_order(service_type=bike, pickup_address=12 Lê Lai, delivery_address=siêu thị, delivery_phone=0944667788)` |

**Kỳ vọng:**
- ✅ AI hỏi đúng nơi đón
- ✅ `pickup_address` ≠ địa chỉ Shop

---

### TC-B03 | Hỏi giá xe ôm

**Mô tả:** Shop hỏi giá xe ôm.

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 🏪 Shop | `xe ôm từ Lê Lợi đến bệnh viện tỉnh bao nhiêu tiền` |
| 1 | 🤖 AI | ✅ Gọi `calculate_fee(pickup_address=Lê Lợi, delivery_address=Bệnh viện Tỉnh Kiên Giang, service_type=bike)` → Báo giá |

---

## 🏍️ NHÓM 4: LÁI HỘ XE MÁY (motor)

---

### TC-M01 | Lái hộ xe máy nêu rõ loại xe

**Mô tả:** Shop nói rõ "lái xe máy hộ" → AI tạo đơn ngay.

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 🏪 Shop | `lái xe máy hộ từ 20 Nguyễn Huệ đến 88 Trần Hưng Đạo SĐT 0966112233` |
| 1 | 🤖 AI | ✅ Gọi `create_order(service_type=motor, pickup_address=20 Nguyễn Huệ, delivery_address=88 Trần Hưng Đạo, delivery_phone=0966112233)` |

**Kỳ vọng:**
- ✅ `service_type` = motor (không phải car)
- ✅ Không hỏi lại loại xe

---

### TC-M02 | "Lái xe hộ" — chưa rõ loại xe (bắt buộc hỏi)

**Mô tả:** Shop chỉ nói "lái xe hộ" không rõ xe máy hay ô tô → AI PHẢI hỏi.

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 🏪 Shop | `lái xe hộ từ 5 Lê Duẩn đến sân bay SĐT 0977889900` |
| 1 | 🤖 AI | ❓ "Dạ, anh/chị cần lái **xe máy** hay **ô tô** ạ?" |
| 2 | 🏪 Shop | `xe máy` |
| 2 | 🤖 AI | ✅ Gọi `create_order(service_type=motor, ...)` |

**Kỳ vọng:**
- ✅ AI KHÔNG tạo đơn khi chưa rõ loại xe
- ✅ Sau khi biết "xe máy" → tạo `motor`

---

### TC-M03 | Lái hộ xe máy hỏi giá trước

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 🏪 Shop | `lái xe máy hộ từ đây đến bến xe bao nhiêu` |
| 1 | 🤖 AI | ❓ Hỏi địa chỉ điểm đi (hoặc dùng shop làm mốc) rồi gọi `calculate_fee(service_type=motor)` |

---

## 🚗 NHÓM 5: LÁI HỘ Ô TÔ (car)

---

### TC-C01 | Lái hộ ô tô nêu rõ loại xe

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 🏪 Shop | `cần lái ô tô hộ từ 10 Trần Phú đến bệnh viện SĐT 0900112233` |
| 1 | 🤖 AI | ✅ Gọi `create_order(service_type=car, pickup_address=10 Trần Phú, delivery_address=Bệnh viện, delivery_phone=0900112233)` |

**Kỳ vọng:**
- ✅ `service_type` = car
- ✅ Tạo đơn ngay không hỏi thêm

---

### TC-C02 | "Lái xe hộ" không rõ loại → chọn ô tô

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 🏪 Shop | `lái xe hộ từ 3 Đinh Tiên Hoàng đến cảng cá SĐT 0988776655` |
| 1 | 🤖 AI | ❓ "Dạ, anh/chị cần lái **xe máy** hay **ô tô** ạ?" |
| 2 | 🏪 Shop | `ô tô` |
| 2 | 🤖 AI | ✅ Gọi `create_order(service_type=car, ...)` |

---

## 💳 NHÓM 6: NẠP TIỀN (topup)

> **Lưu ý:** Shipper đến tận nơi khách để nạp. Cần: địa chỉ khách + SĐT + Số tiền nạp. KHÔNG hỏi loại nạp.

---

### TC-T01 | Nạp tiền đầy đủ thông tin

**Mô tả:** Shop gửi đủ thông tin nạp tiền → AI tạo đơn ngay.

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 🏪 Shop | `nạp 100k cho khách ở 22 Nguyễn Trung Trực SĐT 0933445566` |
| 1 | 🤖 AI | ✅ Gọi `create_order(service_type=topup, delivery_address=22 Nguyễn Trung Trực, delivery_phone=0933445566, items=Nạp tiền 100k)` |

**Kỳ vọng:**
- ✅ `service_type` = topup
- ✅ AI không hỏi loại nạp (điện thoại/ngân hàng)
- ✅ Tạo đơn ngay với đủ 3 thông tin

---

### TC-T02 | Nạp tiền thiếu số tiền

**Mô tả:** Shop chưa cho biết số tiền nạp → AI PHẢI hỏi.

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 🏪 Shop | `nạp tiền cho khách ở 15 Lê Thánh Tôn SĐT 0955334411` |
| 1 | 🤖 AI | ❓ "Dạ, anh/chị cần nạp **bao nhiêu tiền** để em tính phí ạ?" |
| 2 | 🏪 Shop | `200k` |
| 2 | 🤖 AI | ✅ Gọi `create_order(service_type=topup, items=Nạp tiền 200k, ...)` |

**Kỳ vọng:**
- ✅ AI hỏi đúng "bao nhiêu tiền"
- ✅ Không hỏi loại nạp

---

### TC-T03 | Nạp tiền số tiền dạng triệu

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 🏪 Shop | `nạp 1.5tr cho 33 Lý Thường Kiệt SĐT 0944556677` |
| 1 | 🤖 AI | ✅ Gọi `create_order(service_type=topup, items=Nạp tiền 1.5tr, ...)` |

**Kỳ vọng:**
- ✅ AI nhận diện "1.5tr" = 1,500,000đ
- ✅ Phí topup tính đúng từ `extractAmountFromItems`

---

### TC-T04 | Hỏi giá nạp tiền trước

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 🏪 Shop | `nạp 500k phí bao nhiêu` |
| 1 | 🤖 AI | ✅ Gọi `calculate_fee(service_type=topup, amount=500k)` → Báo phí |

**Kỳ vọng:**
- ✅ AI không hỏi địa chỉ với topup
- ✅ Trả lời đúng phí nạp 500,000đ

---

## ⭐ NHÓM 7: TÌNH HUỐNG ĐẶC BIỆT

---

### TC-X01 | Hủy đơn vừa tạo (đơn đang pending)

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 🏪 Shop | `ship hoa đến 10 Ngô Quyền SĐT 0911223344` |
| 1 | 🤖 AI | ✅ Tạo đơn thành công |
| 2 | 🏪 Shop | `hủy đơn đó đi` |
| 2 | 🤖 AI | ✅ Gọi `cancel_order()` → "Đã hủy đơn #XXX thành công" |

**Kỳ vọng:**
- ✅ Tool `cancel_order` tìm đúng đơn vừa tạo
- ✅ Đơn chuyển trạng thái `cancelled`

---

### TC-X02 | Hủy đơn đã hoàn thành (không được hủy)

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 🏪 Shop | `hủy đơn giao hôm qua đi` |
| 1 | 🤖 AI | ❌ "Dạ, đơn đã hoàn thành nên không thể hủy được nữa ạ." |

**Kỳ vọng:**
- ✅ AI kiểm tra status đơn và phản hồi đúng

---

### TC-X03 | Kiểm tra trạng thái đơn

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 🏪 Shop | `đơn của mình tới đâu rồi` |
| 1 | 🤖 AI | ✅ Gọi `get_order_status()` → Hiển thị trạng thái + tên tài xế (nếu có) |

**Kỳ vọng:**
- ✅ Tool `get_order_status` được gọi
- ✅ Nếu tài xế đã nhận: hiện tên + SĐT tài xế
- ✅ Emoji + nội dung phù hợp với từng trạng thái

---

### TC-X04 | Khiếu nại — hàng bị vỡ (escalate HIGH)

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 🏪 Shop | `tài xế làm vỡ hàng của khách tôi, shop tôi chịu thiệt hại ai chịu trách nhiệm đây` |
| 1 | 🤖 AI | ✅ Gọi `escalate_to_manager(reason=Tài xế làm vỡ hàng gây thiệt hại, urgency=high, summary=...)` → Phản hồi gấp |

**Kỳ vọng:**
- ✅ `urgency` = high
- ✅ Thông báo quản lý ngay lập tức
- ✅ Câu trả lời thể hiện sự khẩn cấp

---

### TC-X05 | Phàn nàn dịch vụ (escalate MEDIUM)

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 🏪 Shop | `sao tài xế giao trễ quá, khách phàn nàn mình hoài` |
| 1 | 🤖 AI | ✅ Gọi `escalate_to_manager(urgency=medium, reason=Giao hàng trễ khiến khách hàng phàn nàn, ...)` |

**Kỳ vọng:**
- ✅ `urgency` = medium
- ✅ Lời hứa bộ phận hỗ trợ sẽ liên hệ lại

---

### TC-X06 | Yêu cầu gặp quản lý trực tiếp (escalate HIGH)

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 🏪 Shop | `em cần gặp quản lý ngay` |
| 1 | 🤖 AI | ✅ Gọi `escalate_to_manager(urgency=high, reason=Khách yêu cầu gặp quản lý trực tiếp, ...)` |

---

### TC-X07 | Tin nhắn reset / xóa phiên hội thoại

| Lượt | Người | Nội dung |
|------|-------|----------|
| 1 | 🏪 Shop | `hủy` hoặc `xóa` hoặc `reset` |
| 1 | 🤖 AI | ✅ Xóa `AiConversation`, trả "Đã xóa thông tin đơn cũ. Anh/chị cần ship gì..." |

**Kỳ vọng:**
- ✅ Session bị xóa, không còn context cũ
- ✅ Không gọi bất kỳ tool nào

---

## 📊 Bảng Tổng Hợp — Mapping Test → Tool

| Test Case | Tool Kỳ Vọng | Điểm Kiểm Tra Chính |
|-----------|-------------|----------------------|
| TC-D01 | `create_order` | pickup = shop, không hỏi |
| TC-D02 lượt 1 | `create_order` | delivery = Sẽ cung cấp sau |
| TC-D02 lượt 2 | `update_order_address` | Tính lại phí ship |
| TC-D03 | `create_order` | Hiểu tiếng Việt không dấu |
| TC-D04 | `create_order` x2 | Đơn độc lập |
| TC-D05 | `calculate_fee` → `create_order` | Nhớ context địa chỉ |
| TC-D06 | Hỏi SĐT → `create_order` | Hỏi đúng thông tin thiếu |
| TC-S01 | `create_order` shopping | pickup ≠ shop |
| TC-S02 | `create_order` shopping | delivery ≠ shop |
| TC-S03 | Hỏi tiệm → `create_order` | Hỏi đúng |
| TC-S04 | `calculate_fee` shopping | pickup = tiệm mua |
| TC-B01 | `create_order` bike | pickup = nơi đón |
| TC-B02 | Hỏi nơi đón → `create_order` | pickup ≠ shop |
| TC-B03 | `calculate_fee` bike | — |
| TC-M01 | `create_order` motor | Nhận diện "xe máy" |
| TC-M02 | Hỏi loại xe → `create_order` motor | PHẢI hỏi loại xe |
| TC-M03 | `calculate_fee` motor | — |
| TC-C01 | `create_order` car | Nhận diện "ô tô" |
| TC-C02 | Hỏi loại xe → `create_order` car | PHẢI hỏi loại xe |
| TC-T01 | `create_order` topup | Không hỏi loại nạp |
| TC-T02 | Hỏi số tiền → `create_order` topup | PHẢI hỏi số tiền |
| TC-T03 | `create_order` topup | Parse "1.5tr" |
| TC-T04 | `calculate_fee` topup | Không hỏi địa chỉ |
| TC-X01 | `cancel_order` | Đơn pending → hủy được |
| TC-X02 | — (từ chối) | Đơn completed → không hủy |
| TC-X03 | `get_order_status` | Hiển thị tài xế |
| TC-X04 | `escalate_to_manager` high | Khẩn cấp |
| TC-X05 | `escalate_to_manager` medium | Phàn nàn |
| TC-X06 | `escalate_to_manager` high | Gặp quản lý |
| TC-X07 | — (reset) | Xóa session |

---

## 🔴 Các Trường Hợp AI CẤM LÀM (Negative Test)

| # | AI PHẢI TRÁNH | Tình Huống |
|---|---------------|------------|
| 1 | ❌ Hỏi "lấy hàng ở đâu?" với delivery của Shop | Shop nhắn "ship đến 22 NTT" |
| 2 | ❌ Dùng địa chỉ Shop làm pickup_address cho xe ôm | "xe ôm đến bệnh viện SĐT..." |
| 3 | ❌ Hỏi loại nạp (điện thoại/ngân hàng) | Topup bất kỳ |
| 4 | ❌ Tạo đơn khi "lái xe hộ" chưa rõ loại xe | "lái xe hộ từ A đến B" |
| 5 | ❌ Bịa địa chỉ hoặc SĐT | Bất kỳ tình huống |
| 6 | ❌ Hủy đơn đã completed | "hủy đơn hôm qua" |
| 7 | ❌ Lẫn thông tin giữa các đơn liên tiếp | Đặt 2 đơn liên tiếp |

---

> 📅 Ngày tạo: 2026-03-13 | Phiên bản: 1.0
> 
> 🔧 Cập nhật khi có thay đổi về dịch vụ hoặc logic AI trong `AiOrderService.php`
