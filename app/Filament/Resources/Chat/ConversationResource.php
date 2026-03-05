<?php

declare(strict_types=1);

namespace App\Filament\Resources\Chat;

use App\Domain\Chat\Enums\ConversationType;
use App\Domain\Chat\Models\Conversation;
use App\Filament\Resources\Chat\Pages\ListConversations;
use App\Filament\Resources\Chat\Pages\ViewConversation;
use App\Filament\Resources\Chat\RelationManagers\MessagesRelationManager;
use App\Filament\Resources\Chat\Schemas\ConversationInfolist;
use App\Filament\Resources\Chat\Tables\ConversationsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class ConversationResource extends Resource
{
    protected static ?string $model = Conversation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'subject';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user?->isSuperAdmin()) {
            return $query->withoutTenancy()->where('type', ConversationType::Support);
        }

        return $query;
    }

    public static function infolist(Schema $schema): Schema
    {
        return ConversationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConversationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            MessagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConversations::route('/'),
            'view' => ViewConversation::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
