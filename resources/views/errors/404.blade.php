@extends('errors.layout')

@section('title', '404 Not Found')

@section('content')
    <div class="icon-wrap" style="background:#f1f5f9;">
        <svg style="width:2.5rem;height:2.5rem;color:#94a3b8;" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
        </svg>
    </div>
    <span class="badge">Error 404</span>
    <h1>Page Not Found</h1>
    <p class="desc">The page you're looking for doesn't exist or has been moved.</p>
    <div class="actions">
        <a href="/en" class="btn btn-primary">Go Home</a>
        <button class="btn btn-secondary" onclick="history.back()">Go Back</button>
    </div>
@endsection
