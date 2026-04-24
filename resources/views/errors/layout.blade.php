<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Error') — {{ config('app.name', 'Store') }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { font-family: ui-sans-serif, system-ui, sans-serif; -webkit-font-smoothing: antialiased; }
        body { min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #f8fafc; color: #0f172a; padding: 2rem 1rem; }
        @media (prefers-color-scheme: dark) {
            body { background: #0a0f1e; color: #e2e8f0; }
            .card { background: #0d1426; border-color: #1e2d4a; }
            .badge { background: #1e2d4a; color: #94a3b8; }
            .desc { color: #64748b; }
            .btn-secondary { background: #0d1426; border-color: #1e2d4a; color: #94a3b8; }
            .btn-secondary:hover { background: #1e2d4a; }
        }
        .card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 1.25rem; padding: 3rem 2.5rem; text-align: center; max-width: 28rem; width: 100%; box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.07); }
        .icon-wrap { width: 5rem; height: 5rem; border-radius: 1rem; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; }
        .badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; letter-spacing: 0.05em; background: #f1f5f9; color: #475569; margin-bottom: 1rem; }
        h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.75rem; }
        .desc { font-size: 0.875rem; color: #64748b; line-height: 1.6; margin-bottom: 2rem; }
        .actions { display: flex; flex-wrap: wrap; gap: 0.75rem; justify-content: center; }
        .btn { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.625rem 1.25rem; border-radius: 0.75rem; font-size: 0.875rem; font-weight: 600; text-decoration: none; border: none; cursor: pointer; transition: all 0.15s; }
        .btn-primary { background: #6747f5; color: #ffffff; }
        .btn-primary:hover { background: #5538d8; }
        .btn-secondary { background: #ffffff; color: #374151; border: 1px solid #e5e7eb; }
        .btn-secondary:hover { background: #f9fafb; }
    </style>
</head>
<body>
    <div class="card">
        @yield('content')
    </div>
</body>
</html>
