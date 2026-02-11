@php
    $user = auth()->user();
    $selectedTenantId = session('filament_selected_tenant_id');
    $selectedTenant = $selectedTenantId ? \App\Domain\Tenant\Models\Tenant::find($selectedTenantId) : null;
@endphp

@if($user?->isSuperAdmin() && $selectedTenant)
    <div class="bg-primary-600 px-4 py-2 text-center text-sm text-white">
        <span class="font-medium">Viewing as:</span>
        {{ $selectedTenant->name }}
        <span class="text-primary-200">({{ $selectedTenant->slug }})</span>
        <span class="mx-2">|</span>
        <a
            href="{{ route('filament.control-plane.clear-tenant') }}"
            class="underline hover:no-underline"
        >
            Return to Global View
        </a>
    </div>
@endif
