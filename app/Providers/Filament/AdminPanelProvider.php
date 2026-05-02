<?php

namespace App\Providers\Filament;

use App\Models\City;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use App\Filament\Resources\DriverDebtResource;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->brandName('Flash Ship Admin')
            ->font('Roboto Condensed')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->pages([
                \App\Filament\Pages\Dashboard::class,
            ])
            ->widgets([])
            ->navigationGroups([
                NavigationGroup::make()->label('ĐƠN HÀNG'),
                NavigationGroup::make()->label('QUẢN LÝ TÀI XẾ'),
                NavigationGroup::make()->label('TÀI CHÍNH'),
                NavigationGroup::make()->label('BÁO CÁO'),
                NavigationGroup::make()->label('CẤU HÌNH VẬN HÀNH'),
                NavigationGroup::make()->label('CÀI ĐẶT HỆ THỐNG'),
            ])
            ->navigationItems([
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])


            // 🟢 HIỂN THỊ CHỌN VÙNG Ở CUỐI SIDEBAR
            ->renderHook(
                'panels::user-menu.before',
                fn(): string => view('filament.components.city-switcher')->render(),
            )
            // 🟢 TÍCH HỢP FCM PUSH NOTIFICATION (WEB)
            ->renderHook(
                'panels::head.end',
                fn(): string => \Illuminate\Support\Facades\Blade::render(<<<'HTML'
                        <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-app.js"></script>
                        <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-messaging.js"></script>
                        <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-database.js"></script>
                        
                        <script>
                            const firebaseConfig = {
                              apiKey: "AIzaSyDga3drPiu7YcxWoK68a1hZV0sfqcxF6sg",
                              authDomain: "local-flashship.firebaseapp.com",
                              databaseURL: "https://local-flashship-default-rtdb.firebaseio.com",
                              projectId: "local-flashship",
                              storageBucket: "local-flashship.firebasestorage.app",
                              messagingSenderId: "652824641372",
                              appId: "1:652824641372:web:ae11696409d9e1fa10cb99"
                            };

                            firebase.initializeApp(firebaseConfig);
                            const database = firebase.database();
                            const messaging = firebase.messaging();

                            // 🔄 Tự động Refresh toàn bộ Admin khi có đơn hàng mới hoặc đổi trạng thái
                            // Dùng debounce để tránh refresh liên tục khi nhiều event cùng lúc
                            let refreshTimer = null;
                            database.ref('/flashship/events/orders').on('value', (snap) => {
                                if (window.Livewire) {
                                    clearTimeout(refreshTimer);
                                    refreshTimer = setTimeout(() => {
                                        console.log('🔄 Realtime Signal: Đang làm mới dữ liệu...');
                                        window.Livewire.dispatch('$refresh');
                                    }, 1000); // Đợi 1s, nếu có nhiều event liên tiếp chỉ refresh 1 lần
                                }
                            });

                            if ('serviceWorker' in navigator) {
                            navigator.serviceWorker.register('/sw.js').then((registration) => {
                                messaging.useServiceWorker(registration);
                                
                                // 🔄 Ép buộc lấy Token mới để đảm bảo khớp cấu hình
                                messaging.deleteToken().catch(() => {}).then(() => {
                                    Notification.requestPermission().then((permission) => {
                                        if (permission === 'granted') {
                                            messaging.getToken().then((currentToken) => {
                                                if (currentToken) {
                                                    console.log('✅ FCM Token mới:', currentToken);
                                                    fetch('/admin/update-fcm-token', {
                                                        method: 'POST',
                                                        headers: {
                                                            'Content-Type': 'application/json',
                                                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                                        },
                                                        body: JSON.stringify({ fcm_token: currentToken })
                                                    });
                                                }
                                            });
                                        }
                                    });
                                });
                            });
                        }

                        // 🔔 Lắng nghe và "Ép" hiển thị bằng mọi giá
                        messaging.onMessage((payload) => {
                            console.log('✅ Foreground Message Received:', payload);
                            
                            // 🔍 Lọc theo vùng Admin đang chọn (City Switch)
                            const activeCityId = "{{ (string) session('current_city_id', 0) }}";
                            const msgCityId = payload.data ? payload.data.city_id : null;
                            
                            // Nếu đang chọn vùng cụ thể (khác Toàn Quốc/0) VÀ đơn này thuộc vùng khác -> SKIP
                            if (activeCityId !== "0" && msgCityId && activeCityId !== msgCityId) {
                                console.log(`🚫 Bỏ qua thông báo vùng ${msgCityId} (Đang trực vùng ${activeCityId})`);
                                return;
                            }

                            // 1. Âm thanh Ping
                            const audio = new Audio("https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3");
                            audio.play().catch(e => {});

                            // Hỗ trợ cả FCM có notification block lẫn data-only payload
                            const notifTitle = payload.notification?.title ?? payload.data?.title ?? 'Thông báo mới';
                            const notifBody  = payload.notification?.body  ?? payload.data?.body  ?? '';

                            // 2. Hiện Banner Thông báo (Native)
                            if (Notification.permission === 'granted') {
                                new Notification(notifTitle, {
                                    body: notifBody,
                                    icon: '{{ asset("logo.png") }}'
                                });
                            }

                            // 3. Hiện Popup Toast chuẩn Filament (Không gây gián đoạn như alert)
                            if (window.Livewire) {
                                window.Livewire.dispatch('notificationSent', {
                                    notification: {
                                        id: 'fcm_' + Date.now(),
                                        title: notifTitle,
                                        body: notifBody,
                                        status: 'success',
                                        icon: 'heroicon-o-bell-alert',
                                        iconColor: 'success',
                                        duration: 10000,
                                        isPersistent: false
                                    }
                                });
                            }
                        });
                    </script>
HTML
                ),
            );
    }

    public function boot(): void
    {
        Route::middleware(['web', Authenticate::class])
            ->get('/admin/switch-city/{cityId}', function (int $cityId) {
                if ($cityId === 0) {
                    session()->forget('current_city_id');
                } else {
                    $city = City::findOrFail($cityId);
                    session(['current_city_id' => $city->id]);
                }
                $previous = url()->previous();
                $current  = url()->current();
                return redirect(
                    $previous !== $current
                    ? $previous
                    : route('filament.admin.pages.dashboard')
                );
            })->name('admin.switch-city');

        Filament::serving(function () {
            if (auth()->check()) {
                $user = auth()->user();
                // Nếu là driver và KHÔNG CÓ role quản trị nào → Chặn
                $isAdmin = $user->hasAnyRole(['admin', 'dispatcher', 'accountant', 'subadmin', 'manager', 'editor', 'sub-admin']);
                if ($user->hasRole('driver') && !$isAdmin) {
                    \Illuminate\Support\Facades\Log::warning("⛔ Filament Serving Abort: [#{$user->id}] {$user->name} is driver and not admin. Roles: ".implode(',', $user->getRoleNames()->toArray()));
                    auth()->logout();
                    abort(403, 'Tài xế không có quyền truy cập trang quản trị.');
                }
            }
        });
    }
}