@push('styles')
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    :root {
        --sidebar-bg:     #0f172a;
        --card-bg:        rgba(30, 41, 59, 0.7);
        --accent-blue:    #3b82f6;
        --accent-emerald: #10b981;
        --accent-rose:    #f43f5e;
        --accent-amber:   #f59e0b;
        --text-main:      #f8fafc;
        --text-muted:     #94a3b8;
    }

    #driver-map-root {
        display: flex;
        height: calc(100vh - 160px);
        min-height: 650px;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 20px 50px rgba(0,0,0,0.2);
        background: var(--sidebar-bg);
        font-family: 'Inter', sans-serif;
    }

    /* ── Sidebar ── */
    #map-sidebar {
        width: 350px;
        min-width: 320px;
        display: flex;
        flex-direction: column;
        background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
        color: var(--text-main);
        border-right: 1px solid rgba(255,255,255,0.05);
        z-index: 10;
    }

    #map-sidebar-header { padding: 24px 20px 16px; }
    #map-sidebar-header h2 {
        font-size: 18px; font-weight: 800; color: #fff; margin: 0;
        display: flex; align-items: center; gap: 10px; letter-spacing: -0.5px;
    }
    #map-sidebar-header p { font-size: 12px; color: var(--text-muted); margin-top: 4px; font-weight: 500; }

    /* ── Stats ── */
    .stat-pills { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; padding: 0 20px 20px; }
    .stat-pill {
        background: var(--card-bg); backdrop-filter: blur(10px); border-radius: 14px;
        padding: 12px; border: 1px solid rgba(255,255,255,0.05); transition: transform 0.2s;
    }
    .stat-pill:hover { transform: translateY(-2px); background: rgba(30, 41, 59, 0.9); }
    .stat-pill .pill-num   { font-size: 22px; font-weight: 800; line-height: 1; }
    .stat-pill .pill-label { font-size: 10px; color: var(--text-muted); margin-top: 4px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }
    .pill-total  .pill-num { color: var(--accent-blue); }
    .pill-online .pill-num { color: var(--accent-emerald); }
    .pill-busy   .pill-num { color: var(--accent-rose); }
    .pill-off    .pill-num { color: var(--text-muted); }

    /* ── Search ── */
    #driver-search-wrap { padding: 0 20px 15px; }
    #driver-search {
        width: 100%; box-sizing: border-box;
        background: rgba(15, 23, 42, 0.5); border: 1px solid rgba(255,255,255,0.1);
        border-radius: 12px; padding: 10px 12px 10px 38px; color: #fff; font-size: 14px;
        outline: none; transition: all 0.3s;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b' stroke-width='2.5'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M21 21l-4.35-4.35m0 0A7 7 0 1 0 6.5 16.65 7 7 0 0 0 16.65 16.65z'/%3E%3C/svg%3E");
        background-repeat: no-repeat; background-size: 16px; background-position: 12px center;
    }
    #driver-search:focus {
        border-color: var(--accent-blue);
        background-color: rgba(15, 23, 42, 0.8);
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
    }
    #driver-search::placeholder { color: var(--text-muted); }

    /* ── Driver List ── */
    #driver-list { flex: 1; overflow-y: auto; padding: 0 12px 10px; scrollbar-width: thin; scrollbar-color: rgba(255,255,255,0.1) transparent; }
    .driver-card {
        display: flex; align-items: center; gap: 12px; padding: 12px;
        border-radius: 14px; cursor: pointer; transition: all 0.2s;
        margin-bottom: 6px; border: 1px solid transparent;
    }
    .driver-card:hover  { background: rgba(255,255,255,0.03); }
    .driver-card.active { background: rgba(59, 130, 246, 0.1); border-color: rgba(59, 130, 246, 0.3); }
    .driver-avatar-box  { position: relative; flex-shrink: 0; }
    .driver-avatar-img  {
        width: 44px; height: 44px; border-radius: 50%; object-fit: cover;
        border: 2px solid transparent; transition: transform 0.2s;
    }
    .driver-card:hover .driver-avatar-img { transform: scale(1.05); }
    .status-badge-dot {
        position: absolute; bottom: 2px; right: 2px;
        width: 12px; height: 12px; border-radius: 50%; border: 2px solid var(--sidebar-bg);
    }
    .driver-info   { flex: 1; min-width: 0; }
    .driver-name   { font-size: 14px; font-weight: 600; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .driver-meta   { font-size: 12px; color: var(--text-muted); margin-top: 2px; display: flex; align-items: center; gap: 6px; }
    .mini-badge    { font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 20px; text-transform: uppercase; }
    .bg-busy    { background: rgba(244, 63, 94, 0.15);  color: #fb7185; }
    .bg-online  { background: rgba(16, 185, 129, 0.15); color: #34d399; }
    .bg-offline { background: rgba(148, 163, 184, 0.1); color: #94a3b8; }

    /* ── Refresh Bar ── */
    #refresh-bar {
        padding: 12px 20px;
        background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(10px);
        border-top: 1px solid rgba(255,255,255,0.05);
        display: flex; align-items: center; justify-content: space-between;
        font-size: 12px; color: var(--text-muted);
    }
    .firebase-status { display: flex; align-items: center; gap: 8px; }
    #firebase-status-badge { width: 8px; height: 8px; border-radius: 50%; background: #94a3b8; flex-shrink: 0; transition: background 0.3s; }
    #firebase-status-text  { font-size: 11px; }
    #btn-refresh {
        background: var(--accent-blue); color: #fff; border-radius: 8px;
        padding: 6px 12px; font-weight: 600; cursor: pointer; border: none;
        transition: all 0.2s; display: flex; align-items: center; gap: 6px;
    }
    #btn-refresh:hover { background: #2563eb; transform: scale(1.03); }

    /* ── Map ── */
    #map-container { flex: 1; position: relative; background: #e5e5e5; }
    #gmap { width: 100%; height: 100%; }

    @keyframes marker-pulse {
        0%   { transform: scale(1);    box-shadow: 0 0 0 0   rgba(244, 63, 94, 0.4); }
        70%  { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(244, 63, 94, 0);   }
        100% { transform: scale(1);    box-shadow: 0 0 0 0   rgba(244, 63, 94, 0);   }
    }
    .pulse-busy .avatar-wrapper { animation: marker-pulse 2s infinite; }

    .gm-style-iw-c   { padding: 0 !important; border-radius: 16px !important; background: #fff !important; box-shadow: 0 15px 45px rgba(0,0,0,0.1) !important; }
    .gm-style-iw-d   { overflow: hidden !important; max-height: none !important; }
    .gm-ui-hover-effect { display: none !important; }

    #map-loading {
        position: absolute; inset: 0; background: var(--sidebar-bg);
        display: flex; flex-direction: column; align-items: center; justify-content: center; z-index: 100;
    }
    .loader-text { color: #fff; margin-top: 15px; font-weight: 500; letter-spacing: 1px; }
    .spinner     { width: 45px; height: 45px; border: 3px solid rgba(255,255,255,0.1); border-top-color: var(--accent-blue); border-radius: 50%; animation: spin 1s cubic-bezier(0.4, 0, 0.2, 1) infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }

    @media (max-width: 768px) {
        #driver-map-root { flex-direction: column; height: calc(100vh - 100px); border-radius: 0; }
        #map-sidebar {
            position: fixed; bottom: 0; left: 0; right: 0; width: 100% !important;
            max-height: 60vh; border-radius: 25px 25px 0 0;
            transform: translateY(100%); transition: transform 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
        }
        #map-sidebar.open { transform: translateY(0); }
    }
</style>
@endpush

<x-filament-panels::page>
    <div id="driver-map-root">

        {{-- ── Sidebar ── --}}
        <div id="map-sidebar">
            <div id="map-sidebar-header">
                <h2>
                    <svg style="width:20px;height:20px;color:#3b82f6;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Fleet Realtime
                </h2>
                <p>{{ $cityName ?? 'Tất cả khu vực' }}</p>
            </div>

            <div class="stat-pills">
                <div class="stat-pill pill-total">
                    <div class="pill-num" id="stat-total">—</div>
                    <div class="pill-label">Tổng tài xế</div>
                </div>
                <div class="stat-pill pill-online">
                    <div class="pill-num" id="stat-online">—</div>
                    <div class="pill-label">Đang online</div>
                </div>
                <div class="stat-pill pill-busy">
                    <div class="pill-num" id="stat-busy">—</div>
                    <div class="pill-label">Đang có đơn</div>
                </div>
                <div class="stat-pill pill-off">
                    <div class="pill-num" id="stat-offline">—</div>
                    <div class="pill-label">Đang offline</div>
                </div>
            </div>

            <div id="driver-search-wrap">
                <input id="driver-search" type="text" placeholder="Tìm tên hoặc số điện thoại...">
            </div>

            <div id="driver-list"></div>

            <div id="refresh-bar">
                <div class="firebase-status">
                    <div id="firebase-status-badge"></div>
                    <span id="firebase-status-text">Đang kết nối...</span>
                </div>
                <button id="btn-refresh">
                    <svg style="width:13px;height:13px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Làm mới
                </button>
            </div>
        </div>

        {{-- ── Map ── --}}
        <div id="map-container">
            <div id="map-loading">
                <div class="spinner"></div>
                <div class="loader-text">INITIALIZING FLEET DATA...</div>
            </div>
            <div id="gmap"></div>
        </div>

    </div>
</x-filament-panels::page>

@push('scripts')
<script src="https://www.gstatic.com/firebasejs/10.7.1/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.7.1/firebase-database-compat.js"></script>

<script>
(function () {
    const MAPS_KEY        = @json($googleMapsKey);
    const CENTER_DEFAULT  = @json($center);
    const FIREBASE_CONFIG = @json($firebaseConfig);
    const DATA_URL        = '/ajax/drivers/map-data';
    const ORDERS_URL      = '/ajax/drivers/{id}/active-orders';
    const CITY_ID         = @json($currentCityId) || '';

    const COLOR_BUSY   = '#f43f5e';
    const COLOR_ONLINE = '#10b981';

    let gmap        = null;
    let infoWindow  = null;
    let markers     = {};
    let profiles    = {};   // id → driver object từ backend
    let pending     = {};   // id → {lat,lng} — location trước khi profile tải về
    let activeCardId = null;
    let searchQuery  = '';

    const MAP_STYLE = [
        { featureType: 'all',                    elementType: 'labels.text.fill', stylers: [{ color: '#7c93a3' }, { lightness: '-10' }] },
        { featureType: 'administrative.country', elementType: 'geometry',         stylers: [{ visibility: 'on' }] },
        { featureType: 'administrative.country', elementType: 'geometry.stroke',  stylers: [{ color: '#a0a0a0' }] },
        { featureType: 'landscape',              elementType: 'geometry.fill',    stylers: [{ color: '#edeff0' }] },
        { featureType: 'road',                                                    stylers: [{ saturation: -100 }, { lightness: 45 }] },
    ];

    // ─── Map init ──────────────────────────────────────────────────────────────

    function initMap() {
        gmap = new google.maps.Map(document.getElementById('gmap'), {
            center: CENTER_DEFAULT,
            zoom: 13,
            styles: MAP_STYLE,
            mapId: '98d5c4b5742e946a',
        });
        infoWindow = new google.maps.InfoWindow({ maxWidth: 300 });
        gmap.addListener('click', () => infoWindow.close());

        document.getElementById('map-loading').style.display = 'none';

        fetchDrivers();
        initFirebase();
        bindUI();

        // Auto-refresh DB count mỗi 60s để sửa mismatch với Firebase stale data
        setInterval(fetchDrivers, 60_000);
    }

    // ─── Firebase ──────────────────────────────────────────────────────────────

    function initFirebase() {
        if (!FIREBASE_CONFIG.apiKey) {
            setStatus('Thiếu cấu hình Firebase', COLOR_BUSY);
            return;
        }

        firebase.initializeApp(FIREBASE_CONFIG);
        const db  = firebase.database();
        const ref = db.ref('/flashship_main/locations');

        db.ref('.info/connected').on('value', snap => {
            setStatus(
                snap.val() ? '✅ Đã kết nối' : '⚠️ Mất kết nối',
                snap.val() ? COLOR_ONLINE    : '#f59e0b'
            );
        });

        ref.on('child_added',   s => onLocationUpdate(s.val()), onFirebaseError);
        ref.on('child_changed', s => onLocationUpdate(s.val()), onFirebaseError);
        ref.on('child_removed', s => removeMarker(s.val()?.id));
    }

    function onFirebaseError(err) {
        console.error('❌ Firebase:', err);
        if (err.code === 'PERMISSION_DENIED') {
            setStatus('⚠️ Firebase từ chối quyền (sửa Rules → .read: true)', COLOR_BUSY);
        }
    }

    function onLocationUpdate(data) {
        if (!data) return;

        const id  = data.id || data.driver_id;
        const lat = parseFloat(data.lat || data.latitude);
        const lng = parseFloat(data.lng || data.longitude);
        if (!id || isNaN(lat) || isNaN(lng)) return;

        const pos = { lat, lng };

        if (profiles[id]) {
            // Chỉ cập nhật VỊ TRÍ từ Firebase — active_orders giữ nguyên từ DB
            // (Firebase active_orders do driver app push có thể bị stale khi app bị kill)
            updateMarker(profiles[id], pos);
        } else {
            pending[id] = pos;
        }
    }

    // ─── Backend fetch ──────────────────────────────────────────────────────────

    function fetchDrivers() {
        const url = CITY_ID ? `${DATA_URL}?city_id=${CITY_ID}` : DATA_URL;
        fetch(url)
            .then(r => r.json())
            .then(json => {
                if (!json.success) return;

                json.data.forEach(driver => {
                    profiles[driver.id] = driver;
                    if (pending[driver.id]) {
                        // Firebase location đã về trước → dùng luôn
                        updateMarker(driver, pending[driver.id]);
                        delete pending[driver.id];
                    } else if (!markers[driver.id] && driver.lat && driver.lng) {
                        // Chưa có Firebase location → dùng tạm lat/lng từ MySQL
                        updateMarker(driver, { lat: driver.lat, lng: driver.lng });
                    }
                });

                // Prune pending cho tài xế ngoài khu vực này
                Object.keys(pending).forEach(id => {
                    if (!profiles[id]) delete pending[id];
                });

                renderList();
            });
    }

    // ─── Markers ───────────────────────────────────────────────────────────────

    function driverColor(driver) {
        return driver.active_orders > 0 ? COLOR_BUSY : COLOR_ONLINE;
    }

    function updateMarker(driver, pos) {
        if (!markers[driver.id]) {
            createMarker(driver, pos);
            return;
        }

        markers[driver.id].position = pos;

        const color = driverColor(driver);
        const el    = markers[driver.id].content;
        if (!el) return;

        el.classList.toggle('pulse-busy', driver.active_orders > 0);
        const border = el.querySelector('.marker-main-border');
        const dot    = el.querySelector('.marker-status-dot');
        if (border) border.style.borderColor      = color;
        if (dot)    dot.style.backgroundColor     = color;
    }

    function createMarker(driver, pos) {
        const color = driverColor(driver);
        const el    = document.createElement('div');
        el.className = 'custom-driver-marker' + (driver.active_orders > 0 ? ' pulse-busy' : '');
        el.innerHTML = `
            <div class="marker-main-border" style="width:52px;height:52px;border-radius:50%;border:3px solid ${color};background:#fff;padding:2px;box-shadow:0 8px 20px rgba(0,0,0,0.2);position:relative;">
                <img src="${driver.avatar_url}" style="width:100%;height:100%;border-radius:50%;object-fit:cover;"
                     onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(driver.name)}'">
                <div class="marker-status-dot" style="position:absolute;bottom:1px;right:1px;width:14px;height:14px;border-radius:50%;border:3px solid #fff;background:${color};"></div>
            </div>`;

        const marker = new google.maps.marker.AdvancedMarkerElement({
            map: gmap, position: pos, title: driver.name, content: el,
        });
        marker.addListener('click', () => {
            openInfoWindow(driver, marker.position);
            setActiveCard(driver.id);
        });
        markers[driver.id] = marker;
    }

    function removeMarker(id) {
        if (id && markers[id]) {
            markers[id].map = null;   // AdvancedMarkerElement API
            delete markers[id];
        }
    }

    // ─── InfoWindow ────────────────────────────────────────────────────────────

    function openInfoWindow(driver, position) {
        const busy  = driver.active_orders > 0;
        const color = busy ? COLOR_BUSY : COLOR_ONLINE;
        const label = busy ? 'Đang có đơn' : 'Đang rảnh';

        infoWindow.setContent(`
            <div style="padding:20px;font-family:'Inter',sans-serif;min-width:280px;background:#fff;border-radius:20px;">
                <div id="driver-info-main-${driver.id}">
                    <div style="display:flex;align-items:center;gap:15px;margin-bottom:20px;">
                        <div style="position:relative;flex-shrink:0;">
                            <img src="${driver.avatar_url}"
                                 style="width:48px;height:48px;border-radius:50%;object-fit:cover;border:2.5px solid ${color};padding:1.5px;background:#fff;"
                                 onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(driver.name)}'">
                            <div style="position:absolute;bottom:1px;right:1px;width:12px;height:12px;border-radius:50%;background:${color};border:2px solid #fff;"></div>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:16px;font-weight:800;color:#0f172a;margin-bottom:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${driver.name}</div>
                            <div style="color:${color};font-size:12px;font-weight:700;">${label}</div>
                        </div>
                    </div>
                    <div style="background:#f8fafc;border-radius:16px;padding:14px;margin-bottom:20px;border:1px solid #f1f5f9;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                            <span style="color:#64748b;font-size:12px;font-weight:500;">Số điện thoại</span>
                            <a href="https://zalo.me/${driver.phone}" target="_blank"
                               style="color:#3b82f6;font-size:13px;font-weight:700;text-decoration:none;display:flex;align-items:center;gap:4px;">
                                ${driver.phone}
                                <svg style="width:14px;height:14px;" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.46 12.87l-1.01 2.1c-.2.4-.6.6-1.05.37-2.09-1.03-3.07-2.31-4.04-3.51-.97-1.2-1.89-2.58-2.61-4.41-.18-.45.03-.96.48-1.12l2.06-.72c.4-.14.84.07.98.47l1.09 3.06c.14.4-.07.84-.47.98l-1.16.4c.48 1.05 1.15 1.95 1.96 2.65l.62-1.07c.23-.4.73-.53 1.12-.3l3.03 1.73c.4.23.53.73.3 1.12z"/></svg>
                            </a>
                        </div>
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <span style="color:#64748b;font-size:12px;font-weight:500;">Số đơn đang nhận</span>
                            <span style="color:${color};font-size:13px;font-weight:800;">${driver.active_orders || 0} đơn</span>
                        </div>
                    </div>
                </div>
                <div style="display:flex;gap:10px;">
                    <button onclick="window.loadOrders(${driver.id}, this)"
                            style="flex:1;display:flex;align-items:center;justify-content:center;gap:8px;background:#3b82f6;color:#fff;padding:12px;border-radius:12px;font-size:13px;font-weight:700;border:none;cursor:pointer;box-shadow:0 4px 12px rgba(59,130,246,0.2);">
                        <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                        XEM CHI TIẾT ĐƠN HÀNG
                    </button>
                </div>
                <div id="orders-${driver.id}" style="margin-top:10px;display:none;max-height:280px;overflow-y:auto;overflow-x:hidden;scrollbar-width:thin;"></div>
            </div>`);

        infoWindow.setPosition(position);
        infoWindow.open(gmap);
    }

    // ─── Sidebar ───────────────────────────────────────────────────────────────

    function renderList() {
        const list = document.getElementById('driver-list');
        if (!list) return;

        const all      = Object.values(profiles);
        const q        = searchQuery.toLowerCase();
        const filtered = q
            ? all.filter(d => d.name.toLowerCase().includes(q) || (d.phone || '').includes(q))
            : all;

        // Stats
        const online  = all.filter(d => d.is_online).length;
        const busy    = all.filter(d => d.active_orders > 0).length;
        setText('stat-total',   all.length);
        setText('stat-online',  online);
        setText('stat-busy',    busy);
        setText('stat-offline', all.length - online);

        // Sort: có đơn → online rảnh → offline
        filtered.sort((a, b) => {
            const rank = d => d.active_orders > 0 ? 0 : (d.is_online ? 1 : 2);
            return rank(a) - rank(b);
        });

        if (!filtered.length) {
            list.innerHTML = '<div style="text-align:center;color:#94a3b8;font-size:12px;padding:24px;">Không tìm thấy tài xế nào</div>';
            return;
        }

        list.innerHTML = filtered.map(d => {
            const isBusy   = d.active_orders > 0;
            const dotColor = isBusy ? COLOR_BUSY : (d.is_online ? COLOR_ONLINE : '#94a3b8');
            const badge    = isBusy ? 'bg-busy' : (d.is_online ? 'bg-online' : 'bg-offline');
            const badgeTxt = isBusy ? 'Có đơn'  : (d.is_online ? 'Online'    : 'Offline');
            const active   = activeCardId === d.id ? ' active' : '';
            return `
                <div class="driver-card${active}" onclick="window.focusDriver(${d.id})">
                    <div class="driver-avatar-box">
                        <img class="driver-avatar-img" src="${d.avatar_url}"
                             onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(d.name)}'">
                        <div class="status-badge-dot" style="background:${dotColor};"></div>
                    </div>
                    <div class="driver-info">
                        <div class="driver-name">${d.name}</div>
                        <div class="driver-meta">
                            ${d.phone || ''}
                            <span class="mini-badge ${badge}">${badgeTxt}</span>
                        </div>
                    </div>
                </div>`;
        }).join('');
    }

    function setActiveCard(id) {
        activeCardId = id;
        renderList();
    }

    function setStatus(text, color) {
        const badge = document.getElementById('firebase-status-badge');
        const label = document.getElementById('firebase-status-text');
        if (badge) badge.style.backgroundColor = color;
        if (label) { label.textContent = text; label.style.color = color; }
    }

    function setText(id, val) {
        const el = document.getElementById(id);
        if (el) el.textContent = val;
    }

    // ─── UI bindings ───────────────────────────────────────────────────────────

    function bindUI() {
        document.getElementById('driver-search')?.addEventListener('input', e => {
            searchQuery = e.target.value;
            renderList();
        });

        document.getElementById('btn-refresh')?.addEventListener('click', fetchDrivers);
    }

    window.focusDriver = function (id) {
        const driver = profiles[id];
        const marker = markers[id];
        if (marker) {
            gmap.panTo(marker.position);
            gmap.setZoom(15);
            if (driver) openInfoWindow(driver, marker.position);
        }
        setActiveCard(id);
    };

    // ─── Order detail panel ────────────────────────────────────────────────────

    const SVG_LIST = `<svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>`;
    const SVG_BACK = `<svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"></path></svg>`;

    window.loadOrders = function (id, btn) {
        const container = document.getElementById(`orders-${id}`);
        const infoMain  = document.getElementById(`driver-info-main-${id}`);

        const showDetail = () => {
            container.style.display = 'block';
            if (infoMain) infoMain.style.display = 'none';
            btn.innerHTML = `${SVG_BACK} QUAY LẠI THÔNG TIN CHUNG`;
        };
        const showMain = () => {
            container.style.display = 'none';
            if (infoMain) infoMain.style.display = 'block';
            btn.innerHTML = `${SVG_LIST} XEM CHI TIẾT ĐƠN HÀNG`;
        };

        if (container.style.display === 'block') { showMain(); return; }

        btn.textContent = 'Đang tải...';
        fetch(ORDERS_URL.replace('{id}', id))
            .then(r => r.json())
            .then(json => {
                const orders = json.data || [];

                // Đồng bộ lại active_orders thực từ DB → sửa mismatch với Firebase
                if (profiles[id] && profiles[id].active_orders !== orders.length) {
                    profiles[id].active_orders = orders.length;
                    const markerPos = markers[id]?.position;
                    if (markerPos) updateMarker(profiles[id], markerPos);
                    renderList();
                }

                if (!orders.length) {
                    container.innerHTML = '<div style="font-size:11px;color:#94a3b8;text-align:center;padding:16px;">Không có đơn bận</div>';
                } else {
                    container.innerHTML = '<div style="margin-top:5px;">' + orders.map(o => `
                        <div style="background:#f8fafc;padding:12px;border-radius:14px;margin-bottom:8px;border:1px solid #e2e8f0;font-size:11.5px;">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                                <span style="font-weight:900;color:#0f172a;font-size:12px;">#${o.id}</span>
                                <span style="background:${o.status === 'assigned' ? '#3b82f6' : COLOR_BUSY};color:#fff;padding:2px 8px;border-radius:6px;font-size:9px;font-weight:800;text-transform:uppercase;">
                                    ${o.status === 'assigned' ? 'Chờ lấy' : 'Đang giao'}
                                </span>
                            </div>
                            ${o.order_note?.trim() ? `<div style="color:#1e293b;font-weight:600;background:#fff;padding:8px;border-radius:8px;border-left:3px solid #f59e0b;margin-bottom:6px;line-height:1.4;">📝 ${o.order_note}</div>` : ''}
                            ${o.pickup && o.delivery ? `
                            <div style="display:flex;flex-direction:column;gap:4px;color:#64748b;">
                                <div style="display:flex;align-items:center;gap:5px;"><span style="color:#10b981;">A</span> ${o.pickup}</div>
                                <div style="display:flex;align-items:center;gap:5px;"><span style="color:#f43f5e;">B</span> ${o.delivery}</div>
                            </div>` : ''}
                        </div>`).join('') + '</div>';
                }
                showDetail();
            });
    };

    // ─── Bootstrap Google Maps ──────────────────────────────────────────────────

    if (window.google?.maps) {
        initMap();
    } else {
        window.__mapCb = initMap;
        const s   = document.createElement('script');
        s.src     = `https://maps.googleapis.com/maps/api/js?key=${MAPS_KEY}&libraries=marker&callback=__mapCb`;
        s.async   = true;
        s.defer   = true;
        document.head.appendChild(s);
    }
})();
</script>
@endpush
