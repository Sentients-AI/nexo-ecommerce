@extends('errors.layout')

@section('title', '419 Page Expired')

@section('content')
    <div class="icon-wrap" style="background:#f0fdf4;">
        <svg style="width:2.5rem;height:2.5rem;color:#22c55e;" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
        </svg>
    </div>
    <span class="badge" style="background:#dcfce7;color:#166534;">Error 419</span>
    <h1>Page Expired</h1>
    <p class="desc">Your session has expired. Please refresh the page and try again.</p>
    <div class="actions">
        <button class="btn btn-primary" onclick="window.location.reload()">Refresh Page</button>
        <a href="/en" class="btn btn-secondary">Go Home</a>
    </div>
@endsection
