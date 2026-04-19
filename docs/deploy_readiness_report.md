# 🚀 Deploy Readiness Report — FlashShip Backend
> Ngày kiểm tra: 2026-03-14 | Môi trường hiện tại: `local`

---

## ✅ TRẠNG THÁI TỔNG QUAN

| Hạng Mục | Trạng Thái | Ghi Chú |
|----------|-----------|---------|
| Database migrations | 🟢 OK | 67 migrations, tất cả đã chạy |
| Failed jobs | 🟢 Sạch | 0 failed jobs |
| Escalation tồn đọng | 🟢 Sạch | 0 escalation open |
| Pricing rules | 🟢 OK | 38 rules |
| Zalo webhook route | 🟢 OK | `POST api/zalo/webhook` |
| Scheduled jobs | 🟢 OK | 9 schedules định nghĩa |
| APP_ENV | 🔴 **local** | Cần đổi thành `production` |
| APP_DEBUG | 🔴 **true** | Cần đổi thành `false` |
| Queue worker | 🔴 **Manual** | Cần Supervisor để auto-restart |
| Redis password | 🟡 null | Nên đặt password trên production |
| API keys trong .env | 🟡 Exposed | Gemini, Zalo, OneSignal keys cần bảo vệ |

---

## 🔴 PHẢI LÀM TRƯỚC KHI DEPLOY

### 1. Cập nhật `.env` Production

```env
# ⚠️ BẮT BUỘC thay đổi
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.flashship.vn   ← URL thực tế

# Database production
DB_HOST=127.0.0.1
DB_DATABASE=flashship_prod
DB_USERNAME=flashship_user
DB_PASSWORD=<strong_password>

# Redis (nếu có password)
REDIS_PASSWORD=<redis_password>

# Giữ nguyên các key API
GEMINI_API_KEY=...
ZALO_APP_SECRET=...
ONESIGNAL_REST_API_KEY=...
GOOGLE_MAPS_JS_KEY=...
```

### 2. Cài Supervisor cho Queue Worker (QUAN TRỌNG NHẤT)

Không có Supervisor → Queue worker chết → AI không reply!

Tạo file `/etc/supervisor/conf.d/flashship-queue.conf`:

```ini
[program:flashship-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /home/user/flashship-backend/artisan queue:work redis --tries=3 --timeout=90 --sleep=2 --max-jobs=500
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/home/user/flashship-backend/storage/logs/queue.log
stopwaitsecs=120
```

Khởi động:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start flashship-queue:*
```

### 3. Cấu hình Cron Job (Scheduled Tasks)

Thêm vào crontab (`crontab -e`):
```bash
* * * * * cd /home/user/flashship-backend && php artisan schedule:run >> /dev/null 2>&1
```

**Lưu ý cPanel:** Vào **Cron Jobs** → thêm dòng trên.

### 4. Chạy lệnh optimize sau deploy

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:optimize
php artisan storage:link
```

---

## 🟡 NÊN LÀM (Không bắt buộc nhưng khuyến khích)

### 5. Đặt Webhook URL Zalo về domain production

Hiện tại webhook Zalo đang trỏ vào `valet share` (ngrok tunnel tạm). Sau deploy cần:
1. Vào Zalo Developer Console
2. Cập nhật Callback URL → `https://api.flashship.vn/api/zalo/webhook`
3. Verify lại webhook

### 6. Queue worker: Restart sau deploy

Thêm vào `deploy.sh` dòng cuối:
```bash
php artisan queue:restart
```
Supervisor sẽ tự khởi động worker mới với code mới.

### 7. Bảo vệ API Keys

Các key trong `.env` cực kỳ nhạy cảm:
- **Gemini API Key** → Có thể bị lạm dụng, phát sinh chi phí
- **OneSignal API Key** → Có thể spam notification
- **Zalo App Secret** → Có thể giả mạo webhook

➡️ Đảm bảo file `.env` KHÔNG bao giờ commit vào Git (đã có trong `.gitignore` ✅)

### 8. Log Level Production

```env
LOG_LEVEL=warning   # Thay vì 'debug' như local
LOG_CHANNEL=daily   # Rotate log hàng ngày
```

### 9. Xóa file `error_log` root

File `error_log` ở thư mục gốc (204KB) chứa thông tin PHP warnings từ server cPanel. Không ảnh hưởng chức năng nhưng nên xóa định kỳ.

---

## 🟢 ĐÃ SẴN SÀNG (Không cần làm gì)

| Hạng Mục | Chi Tiết |
|----------|---------|
| **Migrations** | 67 migrations sạch, không pending |
| **Webhook Zalo** | Route OK, CSRF excluded |
| **AI Logic** | Đã test đầy đủ khách Lẻ + Shop |
| **Human Handoff** | Đã implement và test ổn |
| **Pricing Rules** | 38 rules cho delivery + shopping |
| **Scheduled Tasks** | 9 jobs định nghĩa đúng |
| **Error Handling** | Try/catch ở các điểm quan trọng |
| **Deploy Script** | `deploy.sh` có sẵn |
| **cPanel Guide** | `CPANEL_DEPLOY_GUIDE.md` có sẵn |
| **No debug code** | Không có `dd()`, `var_dump()` trong production code |
| **No failed jobs** | Queue sạch |

---

## 📋 CHECKLIST DEPLOY (Copy & paste để tick)

```
PRE-DEPLOY:
[ ] Cập nhật .env: APP_ENV=production, APP_DEBUG=false
[ ] Cập nhật .env: APP_URL đúng domain production
[ ] Cập nhật .env: DB credentials production
[ ] Đảm bảo Redis hoạt động trên server
[ ] Git commit + push tất cả code mới nhất

DEPLOY:
[ ] git pull origin main
[ ] composer install --no-dev --optimize-autoloader
[ ] php artisan migrate --force
[ ] php artisan config:cache
[ ] php artisan route:cache
[ ] php artisan view:cache
[ ] php artisan storage:link
[ ] php artisan queue:restart
[ ] chmod -R 775 storage bootstrap/cache

POST-DEPLOY:
[ ] Cài Supervisor & start queue worker
[ ] Thêm Cron Job vào cPanel
[ ] Cập nhật Webhook URL Zalo → domain mới
[ ] Test gửi tin Zalo → AI reply thành công
[ ] Kiểm tra admin panel hoạt động
[ ] Monitor logs 30 phút đầu
```

---

## ⚡ Lệnh nhanh để test sau deploy

```bash
# 1. Queue worker đang chạy?
ps aux | grep queue:work

# 2. Test AI reply (qua tinker)
php artisan tinker
>>> app(\App\Services\ChatInteractionService::class)->handleMessage('menu', 'test123', 'zalo', 2)

# 3. Có failed jobs không?
php artisan queue:failed

# 4. Cron chạy không?
php artisan schedule:run --verbose
```

---

> 📅 Báo cáo tạo: 2026-03-14 | Flashship Backend v1.0
