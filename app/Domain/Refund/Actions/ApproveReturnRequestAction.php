<?php

declare(strict_types=1);

namespace App\Domain\Refund\Actions;

use App\Domain\Refund\Models\ReturnRequest;
use App\Domain\User\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class ApproveReturnRequestAction
{
    public function __construct(
        private RequestRefundAction $requestRefund,
        private ApproveRefundAction $approveRefund,
    ) {}

    public function execute(ReturnRequest $returnRequest, User $admin, ?string $adminNotes): ReturnRequest
    {
        if (! $returnRequest->isPending()) {
            throw new DomainException('Only pending return requests can be approved.');
        }

        $returnRequest->loadMissing('items.orderItem', 'order');

        $refundCents = $returnRequest->totalRefundCents();

        if ($refundCents <= 0) {
            throw new DomainException('Return request has no returnable items.');
        }

        return DB::transaction(function () use ($returnRequest, $admin, $adminNotes, $refundCents) {
            $refund = $this->requestRefund->execute(
                $returnRequest->order,
                $refundCents,
                "Return request #{$returnRequest->id} approved",
            );

            $this->approveRefund->execute($refund, $admin);

            $returnRequest->update([
                'status' => 'approved',
                'admin_notes' => $adminNotes,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'refund_id' => $refund->id,
            ]);

            return $returnRequest->fresh();
        });
    }
}
