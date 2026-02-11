<div
    x-data="{ open: false }"
    x-on:click.away="open = false"
    class="relative"
>
    <button
        x-on:click="open = !open"
        type="button"
        class="flex items-center gap-2 rounded-lg bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
    >
        <x-heroicon-o-building-office-2 class="h-5 w-5" />
        <span class="max-w-[150px] truncate">
            {{ $selectedTenant?->name ?? 'All Tenants' }}
        </span>
        <x-heroicon-m-chevron-down class="h-4 w-4" x-bind:class="{ 'rotate-180': open }" />
    </button>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        class="absolute right-0 z-50 mt-2 w-64 origin-top-right rounded-lg bg-white shadow-lg ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10"
        style="display: none;"
    >
        <div class="p-2">
            {{-- All Tenants Option --}}
            <button
                wire:click="selectTenant(null)"
                type="button"
                class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm hover:bg-gray-100 dark:hover:bg-gray-800 {{ $selectedTenant === null ? 'bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400' : 'text-gray-700 dark:text-gray-300' }}"
            >
                <x-heroicon-o-globe-alt class="h-5 w-5 shrink-0" />
                <div>
                    <div class="font-medium">All Tenants</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Global view</div>
                </div>
                @if($selectedTenant === null)
                    <x-heroicon-m-check class="ml-auto h-5 w-5 text-primary-600 dark:text-primary-400" />
                @endif
            </button>

            @if($tenants->isNotEmpty())
                <div class="my-2 border-t border-gray-200 dark:border-gray-700"></div>

                <div class="max-h-64 overflow-y-auto">
                    @foreach($tenants as $tenant)
                        <button
                            wire:click="selectTenant({{ $tenant->id }})"
                            type="button"
                            class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm hover:bg-gray-100 dark:hover:bg-gray-800 {{ $selectedTenant?->id === $tenant->id ? 'bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400' : 'text-gray-700 dark:text-gray-300' }}"
                        >
                            <x-heroicon-o-building-storefront class="h-5 w-5 shrink-0" />
                            <div class="min-w-0 flex-1">
                                <div class="truncate font-medium">{{ $tenant->name }}</div>
                                <div class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $tenant->slug }}</div>
                            </div>
                            @if($selectedTenant?->id === $tenant->id)
                                <x-heroicon-m-check class="ml-auto h-5 w-5 shrink-0 text-primary-600 dark:text-primary-400" />
                            @endif
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
