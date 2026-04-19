@push('styles')
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    :root {
        --sidebar-bg: #0f172a;
        --card-bg: rgba(30, 41, 59, 0.7);
        --accent-blue: #3b82f6;
        --accent-emerald: #10b981;
        --accent-rose: #f43f5e;
        --accent-amber: #f59e0b;
        --text-main: #f8fafc;
        --text-muted: #94a3b8;
    }

    #driver-map-root { 
        display: flex; 
        gap: 0; 
        height: calc(100vh - 160px); 
        min-height: 650px; 
        border-radius: 20px; 
        overflow: hidden; 
        box-shadow: 0 20px 50px rgba(0,0,0,0.2); 
        background: var(--sidebar-bg); 
        font-family: 'Inter', sans-serif;
    }

    /* Sidebar Styling */
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

    #map-sidebar-header { 
        padding: 24px 20px 16px; 
    }
    #map-sidebar-header h2 { 
        font-size: 18px; 
        font-weight: 800; 
        color: #fff; 
        margin: 0; 
        display: flex; 
        align-items: center; 
        gap: 10px;
        letter-spacing: -0.5px;
    }
    #map-sidebar-header p { 
        font-size: 12px; 
        color: var(--text-muted); 
        margin-top: 4px; 
        font-weight: 500;
    }

    /* Stats Grid */
    .stat-pills { 
        display: grid; 
        grid-template-columns: 1fr 1fr; 
        gap: 10px; 
        padding: 0 20px 20px; 
    }
    .stat-pill { 
        background: var(--card-bg); 
        backdrop-filter: blur(10px); 
        border-radius: 14px; 
        padding: 12px; 
        border: 1px solid rgba(255,255,255,0.05); 
        transition: transform 0.2s;
    }
    .stat-pill:hover {
        transform: translateY(-2px);
        background: rgba(30, 41, 59, 0.9);
    }
    .stat-pill .pill-num { font-size: 22px; font-weight: 800; line-height: 1; }
    .stat-pill .pill-label { font-size: 10px; color: var(--text-muted); margin-top: 4px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }
    
    .pill-total  .pill-num { color: var(--accent-blue); }
    .pill-online .pill-num { color: var(--accent-emerald); }
    .pill-busy   .pill-num { color: var(--accent-rose); }
    .pill-off    .pill-num { color: var(--text-muted); }

    /* Search Bar */
    #driver-search-wrap { 
        padding: 0 20px 15px; 
    }
    #driver-search { 
        width: 100%; 
        background: rgba(15, 23, 42, 0.5); 
        border: 1px solid rgba(255,255,255,0.1); 
        border-radius: 12px; 
        padding: 10px 12px 10px 38px; 
        color: #fff; 
        font-size: 14px; 
        outline: none; 
        transition: all 0.3s;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b' stroke-width='2.5'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M21 21l-4.35-4.35m0 0A7 7 0 1 0 6.5 16.65 7 7 0 0 0 16.65 16.65z'/%3E%3C/svg%3E"); 
        background-repeat: no-repeat; 
        background-size: 16px; 
        background-position: 12px center; 
    }
    #driver-search:focus {
        border-color: var(--accent-blue);
        background-color: rgba(15, 23, 42, 0.8);
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
    }

    /* List Item Styling */
    #driver-list { 
        flex: 1; 
        overflow-y: auto; 
        padding: 0 12px 10px; 
        scrollbar-width: thin; 
        scrollbar-color: rgba(255,255,255,0.1) transparent; 
    }
    .driver-card { 
        display: flex; 
        align-items: center; 
        gap: 12px; 
        padding: 12px; 
        border-radius: 14px; 
        cursor: pointer; 
        transition: all 0.2s; 
        margin-bottom: 6px; 
        border: 1px solid transparent; 
    }
    .driver-card:hover { 
        background: rgba(255,255,255,0.03); 
    }
    .driver-card.active { 
        background: rgba(59, 130, 246, 0.1); 
        border-color: rgba(59, 130, 246, 0.3); 
    }
    
    .driver-avatar-box { 
        position: relative; 
        flex-shrink: 0;
    }
    .driver-avatar-img { 
        width: 44px; 
        height: 44px; 
        border-radius: 50%; 
        object-fit: cover; 
        border: 2px solid transparent;
        transition: transform 0.2s;
    }
    .driver-card:hover .driver-avatar-img {
        transform: scale(1.05);
    }
    
    .status-badge-dot { 
        position: absolute; 
        bottom: 2px; 
        right: 2px; 
        width: 12px; 
        height: 12px; 
        border-radius: 50%; 
        border: 2px solid var(--sidebar-bg);
    }

    .driver-info { flex: 1; min-width: 0; }
    .driver-name { font-size: 14px; font-weight: 600; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .driver-meta { font-size: 12px; color: var(--text-muted); margin-top: 2px; display: flex; align-items: center; gap: 6px; }
    
    .mini-badge {
        font-size: 10px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 20px;
        text-transform: uppercase;
    }
    .bg-busy    { background: rgba(244, 63, 94, 0.15); color: #fb7185; }
    .bg-online  { background: rgba(16, 185, 129, 0.15); color: #34d399; }
    .bg-offline { background: rgba(148, 163, 184, 0.1); color: #94a3b8; }

    /* Map Controls Overlay */
    #refresh-bar { 
        padding: 12px 20px; 
        background: rgba(15, 23, 42, 0.85); 
        backdrop-filter: blur(10px);
        border-top: 1px solid rgba(255,255,255,0.05); 
        display: flex; 
        align-items: center; 
        justify-content: space-between; 
        font-size: 12px; 
        color: var(--text-muted); 
    }
    #btn-refresh { 
        background: var(--accent-blue); 
        color: #fff; 
        border-radius: 8px; 
        padding: 6px 12px; 
        font-weight: 600; 
        cursor: pointer; 
        border: none;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    #btn-refresh:hover { 
        background: #2563eb; 
        transform: scale(1.03);
    }

    #map-container { flex: 1; position: relative; background: #e5e5e5; }
    #gmap { width: 100%; height: 100%; }

    /* Custom Marker pulse */
    @keyframes marker-pulse {
        0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(244, 63, 94, 0.4); }
        70% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(244, 63, 94, 0); }
        100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(244, 63, 94, 0); }
    }
    .pulse-busy .avatar-wrapper {
        animation: marker-pulse 2s infinite;
    }

    /* Custom InfoWindow */
    .gm-style-iw-c {
        padding: 0 !important;
        border-radius: 16px !important;
        background: #fff !important;
        box-shadow: 0 15px 45px rgba(0,0,0,0.1) !important;
    }
    .gm-style-iw-d {
        overflow: hidden !important;
        max-height: none !important;
    }
    .gm-ui-hover-effect {
        display: none !important;
    }

    #map-loading { 
        position:absolute; inset: 0; background: var(--sidebar-bg); 
        display:flex; flex-direction: column; align-items:center; justify-content:center; z-index:100; 
    }
    .loader-text { color: #fff; margin-top: 15px; font-weight: 500; letter-spacing: 1px; }
    .spinner { width: 45px; height: 45px; border: 3px solid rgba(255,255,255,0.1); border-top-color: var(--accent-blue); border-radius: 50%; animation: spin 1s cubic-bezier(0.4, 0, 0.2, 1) infinite; }
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
    <div id="driver-map-live-wrapper" style="position:relative;">
        <div id="driver-map-root">
            <!-- MAP container -->
            <div id="map-container">

                <div id="map-loading">
                    <div class="spinner"></div>
                    <div class="loader-text">INITIALIZING FLEET DATA...</div>
                </div>
                <div id="gmap"></div>
            </div>
        </div>
    </div>
</x-filament-panels::page>

@push('scripts')
<!-- Firebase SDK (Compat mode) -->
<script src="https://www.gstatic.com/firebasejs/10.7.1/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.7.1/firebase-database-compat.js"></script>

<script>
(function () {
    const MAPS_KEY = @json($googleMapsKey);
    const CENTER_DEFAULT = @json($center);
    const FIREBASE_CONFIG = @json($firebaseConfig);
    const DATA_URL = '/ajax/drivers/map-data';
    const ORDERS_URL = '/ajax/drivers/{id}/active-orders';
    
    let gmap = null, infoWindow = null;
    let markers = {}; // Lưu trữ marker object
    let allDriversProfiles = {}; // Lưu trữ thông tin tài xế từ Backend
    let pendingLocations = {}; // Lưu trữ tọa độ từ Firebase nếu Profile chưa tải kịp

    let selectedCityId = @json($currentCityId) || '';

    const MAP_STYLE = [
        { "featureType": "all", "elementType": "labels.text.fill", "stylers": [{"color": "#7c93a3"}, {"lightness": "-10"}] },
        { "featureType": "administrative.country", "elementType": "geometry", "stylers": [{"visibility": "on"}] },
        { "featureType": "administrative.country", "elementType": "geometry.stroke", "stylers": [{"color": "#a0a0a0"}] },
        { "featureType": "landscape", "elementType": "geometry.fill", "stylers": [{"color": "#edeff0"}] },
        { "featureType": "road", "stylers": [{"saturation": -100}, {"lightness": 45}] }
    ];

    function initMap() { 
        gmap = new google.maps.Map(document.getElementById('gmap'), { 
            center: CENTER_DEFAULT, 
            zoom: 13, 
            disableDefaultUI: false,
            styles: MAP_STYLE,
            mapId: '98d5c4b5742e946a'
        }); 
        infoWindow = new google.maps.InfoWindow({ maxWidth: 300 }); 

        // 🚀 Đóng InfoWindow khi click ra ngoài (vào bản đồ)
        gmap.addListener('click', () => {
            infoWindow.close();
        });
        document.getElementById('map-loading').style.display = 'none'; 
        
        // 1. Lấy thông tin tài xế từ Backend (MySQL)
        fetchDrivers();

        // 2. Lắng nghe Firebase (Realtime)
        initFirebase();
    }

    function initFirebase() {
        if (!FIREBASE_CONFIG.apiKey) {
            updateStatusText("Thiếu thông tin Firebase", "#f43f5e");
            return;
        }
        
        firebase.initializeApp(FIREBASE_CONFIG);
        const database = firebase.database();
        const locationsRef = database.ref('/flashship/locations');
        
        // Cập nhật trạng thái kết nối lên Panel
        database.ref('.info/connected').on('value', snap => {
            if (snap.val() === true) {
                updateStatusText("✅ Đã kết nối", "#10b981");
            } else {
                updateStatusText("⚠️ Mất kết nối", "#f59e0b");
            }
        });

        // Lắng nghe sự kiện di chuyển + Bắt lỗi quyền truy cập
        locationsRef.on('child_added', snapshot => {
            processFirebaseUpdate(snapshot.val());
        }, error => {
            console.error("❌ Firebase Error:", error);
            if (error.code === 'PERMISSION_DENIED') {
                updateStatusText("⚠️ Firebase: Từ chối quyền xem! (Hãy sửa Rules thành .read: true)", "#f43f5e");
            }
        });

        locationsRef.on('child_changed', snapshot => {
            processFirebaseUpdate(snapshot.val());
            const ut = document.getElementById('last-update-time');
            if (ut) ut.textContent = new Date().toLocaleTimeString();
        });

        locationsRef.on('child_removed', snapshot => removeMarker(snapshot.val()?.id));

        // 🚀 CẬP NHẬT TRẠNG THÁI BẬN/RẢNH (MÀU SẮC) REALTIME
        database.ref('/flashship/events/orders').on('value', (snap) => {
            console.log("📥 Tín hiệu đơn hàng thay đổi -> Đang cập nhật trạng thái bận/rảnh tài xế...");
            fetchDrivers();
        });
    }

    function updateStatusText(text, color) {
        const badge = document.getElementById('firebase-status-badge');
        const statusText = document.getElementById('firebase-status-text');
        if (badge) badge.style.backgroundColor = color;
        if (statusText) {
            statusText.textContent = text;
            statusText.style.color = color;
        }
    }

    function processFirebaseUpdate(data) {
        if (!data) return;
        
        const id = data.id || data.driver_id;
        const lat = parseFloat(data.lat || data.latitude);
        const lng = parseFloat(data.lng || data.longitude);
        const activeOrders = data.active_orders !== undefined ? data.active_orders : null;

        if (!id || isNaN(lat) || isNaN(lng)) return;
        
        console.log(`📡 Firebase update [Driver #${id}]: Orders=${activeOrders}, Pos=${lat},${lng}`);

        const pos = { lat, lng };

        if (allDriversProfiles[id]) {
            // Cập nhật trạng thái bận/rảnh từ Firebase vào Profile hiện tại
            if (activeOrders !== null) {
                allDriversProfiles[id].active_orders = activeOrders;
            }
            updateMarkerOnMap(allDriversProfiles[id], pos);
        } else {
            // Tài xế chưa có trong bộ nhớ — lưu tạm vị trí, chờ fetchDrivers xác nhận có thuộc khu vực không
            pendingLocations[id] = pos;
        }
    }

    function clearAllMarkers() {
        Object.values(markers).forEach(m => m.map = null);
        markers = {};
        allDriversProfiles = {};
        pendingLocations = {};
    }

    function fetchDrivers() {
        const url = selectedCityId ? `${DATA_URL}?city_id=${selectedCityId}` : DATA_URL;
        fetch(url).then(r => r.json()).then(json => {
            if (!json.success) return;

            let totalOnline = 0;
            json.data.forEach(driver => {
                allDriversProfiles[driver.id] = driver;
                if (driver.is_online) totalOnline++;

                if (pendingLocations[driver.id]) {
                    updateMarkerOnMap(driver, pendingLocations[driver.id]);
                    delete pendingLocations[driver.id];
                }
            });

            // Xóa pendingLocations của các tài xế không thuộc khu vực này (backend không trả về)
            Object.keys(pendingLocations).forEach(id => {
                if (!allDriversProfiles[id]) {
                    delete pendingLocations[id];
                }
            });

            const adc = document.getElementById('active-drivers-count');
            if (adc) adc.textContent = totalOnline;
        });
    }

    function updateMarkerOnMap(driver, pos) {
        if (markers[driver.id]) {
            // Xe đã có trên bản đồ -> Di chuyển mượt (Move)
            markers[driver.id].position = pos;

            // 🚀 CẬP NHẬT TRẠNG THÁI BẬN/RẢNH (ĐỔI MÀU ICON)
            const hasOrders = driver.active_orders > 0;
            const markerColor = hasOrders ? '#f43f5e' : '#10b981'; // Rose : Emerald
            
            const mEl = markers[driver.id].content;
            if (mEl) {
                if (hasOrders) mEl.classList.add('pulse-busy');
                else mEl.classList.remove('pulse-busy');

                // 🎯 Tìm đúng class để đổi màu
                const mainBorder = mEl.querySelector('.marker-main-border');
                const statusDot = mEl.querySelector('.marker-status-dot');
                
                if (mainBorder) mainBorder.style.borderColor = markerColor;
                if (statusDot) statusDot.style.backgroundColor = markerColor;
            }
        } else {
            createMarker(driver, pos);
        }
    }

    function createMarker(driver, pos) {
        if (markers[driver.id]) return;
        const hasOrders = driver.active_orders > 0;
        const markerColor = hasOrders ? '#f43f5e' : '#10b981';
        
        const mEl = document.createElement('div');
        mEl.className = `custom-driver-marker ${hasOrders ? 'pulse-busy' : ''}`;
        mEl.innerHTML = `
            <div class="marker-main-border" style="width:52px; height:52px; border-radius:50%; border:3px solid ${markerColor}; background:#fff; padding:2px; box-shadow:0 8px 20px rgba(0,0,0,0.2); position:relative;">
                <img src="${driver.avatar_url}" style="width:100%; height:100%; border-radius:50%; object-fit:cover;" onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(driver.name)}'">
                <div class="marker-status-dot" style="position:absolute; bottom:1px; right:1px; width:14px; height:14px; border-radius:50%; border:3px solid #fff; background:${markerColor};"></div>
            </div>`;

        const marker = new google.maps.marker.AdvancedMarkerElement({ 
            map: gmap, 
            position: pos, 
            title: driver.name, 
            content: mEl 
        });
        
        marker.addListener('click', () => openInfoWindow(driver, marker.position));
        markers[driver.id] = marker;
    }

    function removeMarker(id) {
        if (id && markers[id]) { 
            markers[id].setMap(null); 
            delete markers[id]; 
        }
    }

    function openInfoWindow(driver, position) { 
        const hasOrders = driver.active_orders > 0;
        const statusColor = hasOrders ? '#f43f5e' : '#10b981';
        const statusLabel = hasOrders ? 'Đang có đơn' : 'Đang rảnh';
        const statusIcon = hasOrders ? '🚀' : '✨';
        
        const content = `
            <div style="padding: 20px; font-family: 'Inter', sans-serif; min-width: 280px; background: #fff; border-radius: 20px;">
                <div id="driver-info-main-${driver.id}">
                    <!-- Header: Profile -->
                    <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
                        <div style="position: relative; flex-shrink: 0;">
                            <img src="${driver.avatar_url}" style="width: 48px; height: 48px; border-radius: 50%; object-fit: cover; border: 2.5px solid ${statusColor}; padding: 1.5px; background: #fff;">
                            <div style="position: absolute; bottom: 1px; right: 1px; width: 12px; height: 12px; border-radius: 50%; background: ${statusColor}; border: 2px solid #fff;"></div>
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-size: 16px; font-weight: 800; color: #0f172a; margin-bottom: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${driver.name}</div>
                            <div style="color: ${statusColor}; font-size: 12px; font-weight: 700; font-family: 'Roboto', sans-serif; letter-spacing: 0.3px;">
                                ${statusLabel}
                            </div>
                        </div>
                    </div>

                    <!-- Body: Connectivity Info -->
                    <div style="background: #f8fafc; border-radius: 16px; padding: 14px; margin-bottom: 20px; border: 1px solid #f1f5f9;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <span style="color: #64748b; font-size: 12px; font-weight: 500;">Số điện thoại</span>
                            <a href="https://zalo.me/${driver.phone}" target="_blank" style="color: #3b82f6; font-size: 13px; font-weight: 700; letter-spacing: 0.3px; text-decoration: none; display: flex; align-items: center; gap: 4px;">
                                ${driver.phone}
                                <svg style="width: 14px; height: 14px;" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.46 12.87l-1.01 2.1c-.2.4-.6.6-1.05.37-2.09-1.03-3.07-2.31-4.04-3.51-.97-1.2-1.89-2.58-2.61-4.41-.18-.45.03-.96.48-1.12l2.06-.72c.4-.14.84.07.98.47l1.09 3.06c.14.4-.07.84-.47.98l-1.16.4c.48 1.05 1.15 1.95 1.96 2.65l.62-1.07c.23-.4.73-.53 1.12-.3l3.03 1.73c.4.23.53.73.3 1.12z"/></svg>
                            </a>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: #64748b; font-size: 12px; font-weight: 500;">Số đơn đang nhận</span>
                            <span style="color: ${statusColor}; font-size: 13px; font-weight: 800;">${driver.active_orders || 0} đơn</span>
                        </div>
                    </div>
                </div>

                <!-- Footer: Actions -->
                <div style="display: flex; gap: 10px;">
                    <button onclick="window.loadOrders(${driver.id}, this)" style="flex: 1; display: flex; align-items: center; justify-content: center; gap: 8px; background: #3b82f6; color: #fff; padding: 12px; border-radius: 12px; font-size: 13px; font-weight: 700; border: none; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);">
                        <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                        XEM CHI TIẾT ĐƠN HÀNG
                    </button>
                </div>
                
                <!-- Dynamic Order Listing Container -->
                <div id="orders-${driver.id}" style="margin-top: 10px; display: none; max-height: 280px; overflow-y: auto; overflow-x: hidden; scrollbar-width: thin;"></div>
            </div>`;
            
        infoWindow.setContent(content); 
        infoWindow.setPosition(position); 
        infoWindow.open(gmap); 
    }

    window.loadOrders = function(id, btn) {
        const container = document.getElementById(`orders-${id}`);
        const infoMain = document.getElementById(`driver-info-main-${id}`);
        
        // Nếu đang mở thì đóng lại
        if (container.style.display === 'block') {
            container.style.display = 'none';
            if (infoMain) infoMain.style.display = 'block';
            btn.innerHTML = `<svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg> XEM CHI TIẾT ĐƠN HÀNG`;
            return;
        }

        // Nếu chưa có nội dung thì mới fetch từ server
        if (container.innerHTML === "") {
            btn.textContent = 'Đang tải...';
            fetch(ORDERS_URL.replace('{id}', id)).then(r => r.json()).then(json => {
                btn.innerHTML = `<svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"></path></svg> QUAY LẠI THÔNG TIN CHUNG`;
                container.style.display = 'block';
                if (infoMain) infoMain.style.display = 'none';
                
                if (!(json.data || []).length) return container.innerHTML = '<div style="font-size:11px; color:#94a3b8; text-align:center; margin-top:12px; padding:10px;">Không có đơn bận</div>';
                
                let html = '<div style="margin-top:5px;">';
                json.data.forEach(o => {
                    html += `
                    <div style="background:#f8fafc; padding:12px; border-radius:14px; margin-bottom:8px; border:1px solid #e2e8f0; font-size:11.5px; position:relative;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                            <span style="font-weight:900; color:#0f172a; font-size:12px;">#${o.id}</span>
                            <span style="background:${o.status === 'assigned' ? '#3b82f6' : '#f43f5e'}; color:#fff; padding:2px 8px; border-radius:6px; font-size:9px; font-weight:800; text-transform:uppercase;">
                                ${o.status === 'assigned' ? 'Chờ lấy' : 'Đang giao'}
                            </span>
                        </div>`;

                    if (o.order_note && o.order_note.trim() !== "") {
                        html += `<div style="color:#1e293b; font-weight:600; background:#fff; padding:8px; border-radius:8px; border-left:3px solid #f59e0b; margin-bottom:6px; line-height:1.4;">
                                    📝 ${o.order_note}
                                 </div>`;
                    }

                    if (o.pickup && o.delivery) {
                        html += `
                        <div style="display:flex; flex-direction:column; gap:4px; color:#64748b;">
                            <div style="display:flex; align-items:center; gap:5px;"><span style="color:#10b981;">A</span> ${o.pickup}</div>
                            <div style="display:flex; align-items:center; gap:5px;"><span style="color:#f43f5e;">B</span> ${o.delivery}</div>
                        </div>`;
                    }
                    html += `</div>`;
                });
                container.innerHTML = html + '</div>';
            });
        } else {
            // Đã có nội dung thì chỉ việc hiện ra
            container.style.display = 'block';
            if (infoMain) infoMain.style.display = 'none';
            btn.innerHTML = `<svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"></path></svg> QUAY LẠI THÔNG TIN CHUNG`;
        }
    };

    // Google Maps Loader
    if (window.google && window.google.maps) initMap(); 
    else {
        window.__mapCb = initMap;
        const s = document.createElement('script');
        s.src = `https://maps.googleapis.com/maps/api/js?key=${MAPS_KEY}&libraries=marker&callback=__mapCb`;
        s.async = s.defer = true; document.head.appendChild(s);
    }
})();
</script>
@endpush
