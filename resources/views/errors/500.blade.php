@extends('errors.layout')

@section('title', '500 Server Error')

@section('content')
    <div class="icon-wrap" style="background:#fff1f2;">
        <svg style="width:2.5rem;height:2.5rem;color:#f43f5e;" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
        </svg>
    </div>
    <span class="badge" style="background:#ffe4e6;color:#9f1239;">Error 500</span>
    <h1>Server Error</h1>
    <p class="desc">Something went wrong on our end. We've been notified and are working on a fix.</p>
    <div class="actions">
        <a href="/en" class="btn btn-primary">Go Home</a>
        <button class="btn btn-secondary" onclick="window.location.reload()">Try Again</button>
    </div>
@endsection
