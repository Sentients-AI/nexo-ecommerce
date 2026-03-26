<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reviews\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

final class ReviewRepliesRelationManager extends RelationManager
{
    protected static string $relationship = 'replies';

    protected static ?string $recordTitleAttribute = 'body';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('Author')->sortable(),
                TextColumn::make('body')->label('Reply')->limit(80),
                IconColumn::make('is_merchant_reply')->label('Merchant')->boolean(),
                TextColumn::make('created_at')->label('Posted')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'asc')
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataBeforeCreate(function (array $data): array {
                        $user = Auth::user();
                        $data['user_id'] = $user->id;
                        $data['is_merchant_reply'] = $user->isAdmin() || $user->isSuperAdmin();

                        return $data;
                    }),
            ])
            ->paginated(false);
    }

    protected function getFormSchema(): array
    {
        return [
            Textarea::make('body')->required()->maxLength(2000)->rows(4),
        ];
    }
}
