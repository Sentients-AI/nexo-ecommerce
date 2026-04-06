<?php

declare(strict_types=1);

namespace App\Domain\Refund\Enums;

enum ReturnReason: string
{
    case ChangedMind = 'changed_mind';
    case Defective = 'defective';
    case WrongItem = 'wrong_item';
    case NotAsDescribed = 'not_as_described';
    case DamagedInShipping = 'damaged_in_shipping';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::ChangedMind => 'Changed my mind',
            self::Defective => 'Defective / broken',
            self::WrongItem => 'Wrong item received',
            self::NotAsDescribed => 'Not as described',
            self::DamagedInShipping => 'Damaged in shipping',
            self::Other => 'Other',
        };
    }
}
