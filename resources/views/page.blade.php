<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $page->title }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            color: #333;
            line-height: 1.7;
        }

        .container {
            max-width: 760px;
            margin: 40px auto;
            background: #fff;
            border-radius: 12px;
            padding: 48px 56px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.08);
        }

        h1.page-title {
            font-size: 28px;
            font-weight: 700;
            color: #111;
            margin-bottom: 32px;
            padding-bottom: 16px;
            border-bottom: 2px solid #f0f0f0;
        }

        .content h1, .content h2, .content h3 {
            font-weight: 600;
            color: #111;
            margin: 24px 0 10px;
        }

        .content h1 { font-size: 22px; }
        .content h2 { font-size: 19px; }
        .content h3 { font-size: 16px; }

        .content p {
            margin-bottom: 14px;
            color: #444;
        }

        .content ul, .content ol {
            padding-left: 24px;
            margin-bottom: 14px;
        }

        .content li { margin-bottom: 6px; color: #444; }

        .content strong { color: #111; }

        .content a { color: #e63946; text-decoration: none; }
        .content a:hover { text-decoration: underline; }

        footer {
            text-align: center;
            margin-top: 48px;
            font-size: 13px;
            color: #aaa;
        }

        @media (max-width: 600px) {
            .container { margin: 0; border-radius: 0; padding: 28px 20px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="page-title">{{ $page->title }}</h1>
        <div class="content">
            {!! $page->content !!}
        </div>
        <footer>© {{ date('Y') }} Flashship</footer>
    </div>
</body>
</html>
