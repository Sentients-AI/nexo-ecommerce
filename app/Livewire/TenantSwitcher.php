<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Domain\Tenant\Models\Tenant;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

final class TenantSwitcher extends Component
{
    public ?int $selectedTenantId = null;

    public function mount(): void
    {
        $this->selectedTenantId = session('filament_selected_tenant_id');
    }

    public function selectTenant(?int $tenantId): void
    {
        if ($tenantId === null) {
            session()->forget('filament_selected_tenant_id');
            $this->selectedTenantId = null;

            Notification::make()
                ->title('Viewing All Tenants')
                ->info()
                ->send();
        } else {
            $tenant = Tenant::find($tenantId);

            if ($tenant === null) {
                Notification::make()
                    ->title('Tenant not found')
                    ->danger()
                    ->send();

                return;
            }

            session(['filament_selected_tenant_id' => $tenantId]);
            $this->selectedTenantId = $tenantId;

            Notification::make()
                ->title("Switched to {$tenant->name}")
                ->success()
                ->send();
        }

        $this->redirect(request()->header('Referer', route('filament.control-plane.pages.operations-dashboard')));
    }

    public function getTenants(): Collection
    {
        return Tenant::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    }

    public function getSelectedTenant(): ?Tenant
    {
        if ($this->selectedTenantId === null) {
            return null;
        }

        return Tenant::find($this->selectedTenantId);
    }

    public function render(): View
    {
        return view('livewire.tenant-switcher', [
            'tenants' => $this->getTenants(),
            'selectedTenant' => $this->getSelectedTenant(),
        ]);
    }
}
