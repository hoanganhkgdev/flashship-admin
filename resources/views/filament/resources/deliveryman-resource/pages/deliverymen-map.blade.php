<x-filament::page>
    <div style="position: relative; width: 100%; height: calc(100vh - 150px); border-radius: 12px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
        <!-- Stats Overlay -->
        <div style="position: absolute; top: 20px; left: 20px; z-index: 10; display: flex; gap: 15px; pointer-events: none;">
            <div class="stat-card" style="background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(8px); padding: 15px 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border: 1px solid rgba(255,255,255,0.2); pointer-events: auto;">
                <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Tổng Tài xế</div>
                <div style="font-size: 24px; font-weight: 800; color: #1e293b;">{{ count($drivers) }}</div>
            </div>
            <div class="stat-card" style="background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(8px); padding: 15px 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border: 1px solid rgba(255,255,255,0.2); pointer-events: auto;">
                <div style="font-size: 11px; font-weight: 700; color: #2ecc71; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Trống Đơn</div>
                <div style="font-size: 24px; font-weight: 800; color: #16a34a;">{{ collect($drivers)->where('is_busy', false)->count() }}</div>
            </div>
            <div class="stat-card" style="background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(8px); padding: 15px 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border: 1px solid rgba(255,255,255,0.2); pointer-events: auto;">
                <div style="font-size: 11px; font-weight: 700; color: #e74c3c; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Đang Có Đơn</div>
                <div style="font-size: 24px; font-weight: 800; color: #dc2626;">{{ collect($drivers)->where('is_busy', true)->count() }}</div>
            </div>
        </div>

        <div id="map" style="height: 100%; width: 100%;"></div>
    </div>

    <style>
        .custom-marker {
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer;
        }
        .custom-marker:hover {
            transform: scale(1.15);
            z-index: 1000 !important;
        }
        .avatar-container {
            position: relative;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 4px solid #fff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.25);
            overflow: visible;
        }
        .avatar-container::after {
            content: '';
            position: absolute;
            top: -6px;
            left: -6px;
            right: -6px;
            bottom: -6px;
            border-radius: 50%;
            border: 3px solid transparent;
        }
        .marker-busy .avatar-container::after {
            border-color: #dc2626; /* Red */
        }
        .marker-free .avatar-container::after {
            border-color: #16a34a; /* Green */
        }
        .avatar-img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            background: #f1f5f9;
        }
        .status-dot {
            position: absolute;
            bottom: 2px;
            right: 2px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 2px solid #fff;
            background: #16a34a;
            z-index: 2;
        }
        .marker-busy .status-dot {
            background: #dc2626;
        }

        .info-window-content {
            padding: 8px;
            text-align: center;
            font-family: inherit;
        }
        .info-window-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            margin-bottom: 10px;
            box-shadow: 0 3px 8px rgba(0,0,0,0.1);
            object-fit: cover;
        }
        .info-window-name {
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
        }
        .info-window-status {
            font-size: 13px;
            display: inline-block;
            padding: 2px 10px;
            border-radius: 20px;
            font-weight: 600;
        }
        .status-badge-busy {
            background: #fee2e2;
            color: #b91c1c;
        }
        .status-badge-free {
            background: #dcfce7;
            color: #15803d;
        }
    </style>
</x-filament::page>

<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDnE3bCwhzy4tJ22BVmRMyolwuyCx-1rQc&libraries=marker"></script>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        const drivers = @json($drivers);

        const map = new google.maps.Map(document.getElementById("map"), {
            center: { lat: 10.776889, lng: 106.700806 },
            zoom: 13,
            maxZoom: 18,
            minZoom: 5,
            mapId: '98d5c4b5742e946a', // Sử dụng mapId để kích hoạt Advanced Markers
            styles: [
                {
                    "featureType": "poi",
                    "elementType": "labels",
                    "stylers": [{ "visibility": "off" }]
                }
            ]
        });

        const infoWindow = new google.maps.InfoWindow();

        drivers.forEach(driver => {
            const isBusy = driver.is_busy;
            
            // Create custom marker element
            const markerElement = document.createElement('div');
            markerElement.className = `custom-marker ${isBusy ? 'marker-busy' : 'marker-free'}`;
            
            markerElement.innerHTML = `
                <div class="avatar-container">
                    <img class="avatar-img" src="${driver.avatar_url}" onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(driver.name)}&background=random'">
                    <div class="status-dot"></div>
                </div>
            `;

            // Use AdvancedMarkerElement if available, fallback to regular Marker
            if (google.maps.marker && google.maps.marker.AdvancedMarkerElement) {
                const marker = new google.maps.marker.AdvancedMarkerElement({
                    map: map,
                    position: { lat: parseFloat(driver.latitude), lng: parseFloat(driver.longitude) },
                    title: driver.name,
                    content: markerElement
                });

                marker.addListener("click", () => {
                    openInfoWindow(driver, marker.position);
                });
            } else {
                // Fallback to traditional way if AdvancedMarker is not supported
                // (Though we loaded the library, sometimes keys or regions block it)
                const marker = new google.maps.Marker({
                    position: { lat: parseFloat(driver.latitude), lng: parseFloat(driver.longitude) },
                    map: map,
                    title: driver.name,
                    // Note: Traditional markers don't support HTML. 
                    // To keep the visual for older markers we could use an Icon,
                    // but let's assume AdvancedMarker works with the library='marker' param.
                });

                marker.addListener("click", () => {
                   openInfoWindow(driver, marker.getPosition());
                });
            }
        });

        function openInfoWindow(driver, position) {
            const isBusy = driver.is_busy;
            const content = `
                <div class="info-window-content">
                    <img src="${driver.avatar_url}" class="info-window-avatar" onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(driver.name)}&background=random'">
                    <div class="info-window-name">${driver.name}</div>
                    <div class="info-window-status ${isBusy ? 'status-badge-busy' : 'status-badge-free'}">
                        ${isBusy ? '🔥 Đang giao hàng' : '🌱 Đang trống đơn'}
                    </div>
                    <div style="margin-top: 8px; font-size: 12px; color: #64748b;">
                        ID: #${driver.id}
                    </div>
                </div>
            `;
            infoWindow.setContent(content);
            infoWindow.setPosition(position);
            infoWindow.open(map);
        }
    });
</script>
