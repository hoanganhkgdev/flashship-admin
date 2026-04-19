<?php

namespace Database\Seeders;

use App\Models\AiKnowledge;
use Illuminate\Database\Seeder;

class AiKnowledgeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Từ khóa địa phương (Shortcuts) - Tại Rạch Giá
        $shortcuts = [
            [
                'title' => 'Chợ đêm Rạch Giá (Phú Cường)',
                'type' => 'shortcut',
                'input_text' => 'Chợ đêm',
                'output_data' => 'Chợ đêm Phú Cường, P. An Hòa, Rạch Giá, Kiên Giang',
            ],
            [
                'title' => 'Bệnh viện Đa khoa Kiên Giang',
                'type' => 'shortcut',
                'input_text' => 'BV Tỉnh',
                'output_data' => 'Bệnh viện Đa khoa tỉnh Kiên Giang, đường Võ Văn Kiệt, Rạch Giá',
            ],
            [
                'title' => 'Quán Bún cá Út Ơi',
                'type' => 'shortcut',
                'input_text' => 'Bún cá Út Ơi',
                'output_data' => 'Quán Bún Cá Út Ơi, khu lấn biển Rạch Giá',
            ],
            [
                'title' => 'Cổng Tam Quan',
                'type' => 'shortcut',
                'input_text' => 'Cổng Tam Quan',
                'output_data' => 'Cổng Tam Quan, đường Nguyễn Trung Trực, Rạch Giá',
            ],
            [
                'title' => 'Siêu thị Go! Rạch Giá',
                'type' => 'shortcut',
                'input_text' => 'Siêu thị Go',
                'output_data' => 'Trung tâm thương mại GO! Rạch Giá, P. Vĩnh Bảo, Rạch Giá',
            ],
            [
                'title' => 'Quảng trường Trần Quang Khải',
                'type' => 'shortcut',
                'input_text' => 'Quảng trường',
                'output_data' => 'Quảng trường Trần Quang Khải, Rạch Giá',
            ],
        ];

        foreach ($shortcuts as $data) {
            AiKnowledge::updateOrCreate(['input_text' => $data['input_text']], $data);
        }

        // 2. Ví dụ mẫu (Examples) - Dạy AI cách bóc tách
        $examples = [
            [
                'title' => 'Ví dụ đơn nạp rút tiền',
                'type' => 'example',
                'input_text' => 'Rút 500k tại bến xe Rạch Giá mang về 26 Võ Thị Sáu',
                'output_data' => [
                    'service_type' => 'topup',
                    'items' => 'Rút tiền 500,000đ',
                    'pickup_address' => 'Bến xe Rạch Giá',
                    'delivery_address' => '26 Võ Thị Sáu, Rạch Giá',
                    'shipping_fee' => 500000,
                ],
            ],
            [
                'title' => 'Ví dụ đơn xe ôm',
                'type' => 'example',
                'input_text' => 'Cho mình 1 xe từ bv tỉnh về chợ đêm',
                'output_data' => [
                    'service_type' => 'bike',
                    'pickup_address' => 'BV Tỉnh',
                    'delivery_address' => 'Chợ đêm',
                    'items' => 'Chở khách',
                ],
            ],
        ];

        foreach ($examples as $data) {
            AiKnowledge::updateOrCreate(['title' => $data['title']], $data);
        }

        // 3. Quy tắc (Rules)
        $rules = [
            [
                'title' => 'Quy tắc ưu tiên hỏa tốc',
                'type' => 'rule',
                'input_text' => 'Gấp, Hỏa tốc, Nhanh',
                'output_data' => 'Đánh dấu đơn hàng có ghi chú [HỎA TỐC]. Ưu tiên tài xế phản ứng nhanh.',
            ],
            [
                'title' => 'Quy tắc hàng dễ vỡ',
                'type' => 'rule',
                'input_text' => 'Bánh kem, trứng, đồ sành sứ',
                'output_data' => 'Thêm cảnh báo [HÀNG DỄ VỠ] vào ghi chú đơn hàng.',
            ],
        ];

        foreach ($rules as $data) {
            AiKnowledge::updateOrCreate(['title' => $data['title']], $data);
        }
    }
}
