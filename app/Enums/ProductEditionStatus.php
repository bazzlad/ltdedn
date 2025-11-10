<?php

namespace App\Enums;

enum ProductEditionStatus: string
{
    case Available = 'available';
    case Sold = 'sold';
    case Redeemed = 'redeemed';
    case PendingTransfer = 'pending_transfer';
    case Invalidated = 'invalidated';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Sold => 'Sold',
            self::Redeemed => 'Redeemed',
            self::PendingTransfer => 'Pending Transfer',
            self::Invalidated => 'Invalidated',
        };
    }

    public static function options(): array
    {
        return array_map(
            static fn (self $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            self::cases()
        );
    }
}
