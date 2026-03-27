<?php

declare(strict_types=1);

namespace App\Domain\Order\Listeners;

use App\Domain\Order\Events\OrderPaid;
use App\Domain\Product\Actions\GenerateDownloadTokenAction;

final readonly class GenerateDownloadsOnOrderPaid
{
    public function __construct(
        private GenerateDownloadTokenAction $generateDownloadToken,
    ) {}

    public function handle(OrderPaid $event): void
    {
        $this->generateDownloadToken->execute($event->orderId);
    }
}
