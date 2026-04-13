<?php

declare(strict_types=1);

namespace App\Filament\Resources\GiftCards;

use App\Domain\GiftCard\Actions\CreateGiftCardAction;
use App\Domain\GiftCard\Models\GiftCard;
use App\Filament\Concerns\HasTenantAwareness;
use App\Filament\Resources\GiftCards\Pages\CreateGiftCard;
use App\Filament\Resources\GiftCards\Pages\ListGiftCards;
use App\Filament\Resources\GiftCards\Pages\ViewGiftCard;
use App\Filament\Resources\GiftCards\Tables\GiftCardsTable;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

final class GiftCardResource extends Resource
{
    use HasTenantAwareness;

    protected static ?string $model = GiftCard::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Gift Card Details')->schema([
                TextInput::make('code')
                    ->label('Code')
                    ->placeholder('Auto-generated if empty')
                    ->maxLength(20)
                    ->helperText('Leave blank to auto-generate a 10-character code.')
                    ->afterStateHydrated(fn ($component, $state) => $component->state(mb_strtoupper((string) $state)))
                    ->dehydrateStateUsing(fn ($state) => $state !== '' && $state !== null ? mb_strtoupper($state) : null),

                TextInput::make('initial_balance_cents')
                    ->label('Balance (cents)')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->helperText('Amount in cents, e.g. 5000 = $50.00'),

                DateTimePicker::make('expires_at')
                    ->label('Expires At')
                    ->nullable()
                    ->helperText('Leave blank for no expiry.'),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return GiftCardsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGiftCards::route('/'),
            'create' => CreateGiftCard::route('/create'),
            'view' => ViewGiftCard::route('/{record}'),
        ];
    }

    protected static function handleRecordCreation(array $data): GiftCard
    {
        return app(CreateGiftCardAction::class)->execute(
            initialBalanceCents: (int) $data['initial_balance_cents'],
            code: $data['code'] ?? null,
            isActive: (bool) ($data['is_active'] ?? true),
            expiresAt: isset($data['expires_at']) ? now()->parse($data['expires_at']) : null,
            createdByUserId: auth()->id(),
        );
    }
}
