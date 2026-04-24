<?php

declare(strict_types=1);

namespace App\Filament\Resources\PromotionExperiments;

use App\Domain\Promotion\Models\PromotionExperiment;
use App\Filament\Concerns\HasTenantAwareness;
use App\Filament\Resources\PromotionExperiments\Pages\ListPromotionExperiments;
use App\Filament\Resources\PromotionExperiments\Pages\ViewPromotionExperiment;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

final class PromotionExperimentResource extends Resource
{
    use HasTenantAwareness;

    protected static ?string $model = PromotionExperiment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $label = 'A/B Experiment';

    protected static ?string $pluralLabel = 'A/B Experiments';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Experiment Details')->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g. Summer Sale — % vs $ discount'),

                Textarea::make('hypothesis')
                    ->rows(3)
                    ->placeholder('What are you testing and why?'),

                Toggle::make('is_active')
                    ->default(true),

                DateTimePicker::make('started_at')
                    ->label('Start Date'),

                DateTimePicker::make('ended_at')
                    ->label('End Date'),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Experiment')->schema([
                TextEntry::make('name'),
                TextEntry::make('hypothesis')->placeholder('No hypothesis set'),
                TextEntry::make('is_active')->badge()->formatStateUsing(fn (bool $state) => $state ? 'Active' : 'Inactive'),
                TextEntry::make('started_at')->dateTime()->placeholder('—'),
                TextEntry::make('ended_at')->dateTime()->placeholder('—'),
            ]),

            Section::make('Results')->schema([
                TextEntry::make('variants_summary')
                    ->label('Variants')
                    ->state(function (PromotionExperiment $record): string {
                        $variants = $record->variants()->with('usages')->get();
                        if ($variants->isEmpty()) {
                            return 'No promotions assigned to this experiment yet.';
                        }

                        return $variants->map(function ($promo): string {
                            $usages = $promo->usages()->count();
                            $revenue = $promo->usages()->join('orders', 'promotion_usages.order_id', '=', 'orders.id')
                                ->sum('orders.total_cents');

                            return "Variant {$promo->variant}: {$promo->name} — {$usages} uses, revenue: ".number_format($revenue / 100, 2);
                        })->implode("\n");
                    })
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('variants_count')
                    ->label('Variants')
                    ->counts('variants')
                    ->badge(),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('started_at')->date()->sortable()->placeholder('—'),
                TextColumn::make('ended_at')->date()->placeholder('—'),
            ])
            ->actions([
                Action::make('view')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (PromotionExperiment $record) => self::getUrl('view', ['record' => $record])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPromotionExperiments::route('/'),
            'view' => ViewPromotionExperiment::route('/{record}'),
        ];
    }
}
