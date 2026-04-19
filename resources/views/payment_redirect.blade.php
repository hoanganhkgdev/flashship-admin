<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $status === 'success' ? 'Thanh toán thành công' : 'Đã hủy thanh toán' }}</title>
    <style>
        body { font-family: sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: #f5f5f5; }
        .box { text-align: center; padding: 2rem; background: white; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,.1); max-width: 360px; width: 90%; }
        h2 { color: {{ $status === 'success' ? '#16a34a' : '#dc2626' }}; margin-bottom: .5rem; }
        p { color: #555; font-size: .9rem; }
    </style>
</head>
<body>
<div class="box">
    @if($status === 'success')
        <h2>Thanh toán thành công!</h2>
        <p>Đang chuyển về ứng dụng...</p>
    @else
        <h2>Đã hủy thanh toán</h2>
        <p>Đang chuyển về ứng dụng...</p>
    @endif
</div>
<script>
    window.location.href = "{{ $deepLink }}";
</script>
</body>
</html>
