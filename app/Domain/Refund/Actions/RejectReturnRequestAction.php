<?php

declare(strict_types=1);

namespace App\Domain\Refund\Actions;

use App\Domain\Refund\Models\ReturnRequest;
use App\Domain\User\Models\User;
use DomainException;

final class RejectReturnRequestAction
{
    public function execute(ReturnRequest $returnRequest, User $admin, ?string $adminNotes): ReturnRequest
    {
        if (! $returnRequest->isPending()) {
            throw new DomainException('Only pending return requests can be rejected.');
        }

        $returnRequest->update([
            'status' => 'rejected',
            'admin_notes' => $adminNotes,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        return $returnRequest->fresh();
    }
}
