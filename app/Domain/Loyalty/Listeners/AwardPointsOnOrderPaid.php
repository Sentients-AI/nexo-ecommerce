<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Listeners;

use App\Domain\Loyalty\Actions\AwardPointsAction;
use App\Domain\Loyalty\DTOs\AwardPointsData;
use App\Domain\Order\Events\OrderPaid;
use Illuminate\Support\Facades\Context;

final readonly class AwardPointsOnOrderPaid
{
    public function __construct(
        private AwardPointsAction $awardPointsAction,
    ) {}

    /**
     * Handle the OrderPaid event and award loyalty points.
     */
    public function handle(OrderPaid $event): void
    {
        $pointsPerDollar = config('loyalty.points_per_dollar', 1);
        $points = (int) floor(($event->totalCents / 100) * $pointsPerDollar);

        if ($points <= 0) {
            return;
        }

        Context::add('tenant_id', $event->tenantId);

        $this->awardPointsAction->execute(new AwardPointsData(
            userId: $event->userId,
            points: $points,
            description: "Points earned for order #{$event->orderNumber}",
            referenceType: 'order',
            referenceId: $event->orderId,
        ));
    }
}
