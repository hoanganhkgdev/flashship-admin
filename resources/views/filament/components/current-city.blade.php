@php
    $city = \App\Models\City::find(session('current_city_id'));
@endphp

@if($city)
    <div class="px-4 text-sm text-gray-600">
        🏙️ <strong>{{ $city->name }}</strong>
    </div>
@endif