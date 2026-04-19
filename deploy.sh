#!/bin/bash
# ═══════════════════════════════════════════════════
# Flashship — Deploy Script cho cPanel Shared Hosting
# Chạy sau khi upload code lên server
# ═══════════════════════════════════════════════════
# Usage: bash deploy.sh

set -e  # Dừng nếu có lỗi

echo "🚀 Bắt đầu deploy Flashship..."

# 1. Cài dependencies (production only, bỏ dev packages)
echo "📦 Cài Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# 2. Tối ưu Laravel (cache config, routes, views)
echo "⚡ Tối ưu Laravel..."
php artisan config:cache     # Cache .env + config files → nhanh hơn 10x
php artisan route:cache      # Cache routes
php artisan view:cache       # Cache Blade templates
php artisan event:cache      # Cache events

# 3. Chạy migration (nếu có thay đổi DB)
echo "🗃️  Chạy Migration..."
php artisan migrate --force  # --force để chạy trên production không hỏi confirm

# 4. Tạo bảng jobs/cache nếu chưa có (cần cho QUEUE_CONNECTION=database)
echo "📋 Đảm bảo bảng jobs & cache tồn tại..."
php artisan queue:table 2>/dev/null || true
php artisan cache:table 2>/dev/null || true
php artisan session:table 2>/dev/null || true
php artisan migrate --force 2>/dev/null || true

# 5. Clear cache cũ
echo "🧹 Clear cache..."
php artisan cache:clear
php artisan config:clear
# Sau đó cache lại
php artisan config:cache
php artisan route:cache

# 6. Tạo storage symlink
echo "🔗 Tạo storage symlink..."
php artisan storage:link 2>/dev/null || true

# 7. Set permissions (cPanel)
echo "🔐 Set permissions..."
chmod -R 755 storage bootstrap/cache
chmod -R 644 storage/logs bootstrap/cache

echo ""
echo "✅ Deploy xong! Kiểm tra:"
echo "   - https://admin.flashship.vn"
echo "   - https://admin.flashship.vn/api/health (nếu có)"
echo ""
