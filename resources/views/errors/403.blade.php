@extends('errors.layout')

@section('title', '403 Forbidden')

@section('content')
    <div class="icon-wrap" style="background:#fffbeb;">
        <svg style="width:2.5rem;height:2.5rem;color:#f59e0b;" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
        </svg>
    </div>
    <span class="badge" style="background:#fef3c7;color:#92400e;">Error 403</span>
    <h1>Forbidden</h1>
    <p class="desc">You don't have permission to access this page.</p>
    <div class="actions">
        <a href="/en" class="btn btn-primary">Go Home</a>
        <button class="btn btn-secondary" onclick="history.back()">Go Back</button>
    </div>
@endsection
