<?php

namespace ProductDiscounts\Reports;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use ProductDiscounts\Reports\Models\Basket;

class BasketStatus
{
    /** Call only from trusted order/payment processing code, after verifying its association to the basket. */
    public function update(string $basketGuid, string $status, ?CarbonInterface $paidAt = null): Basket
    {
        if (!in_array($status, ['new', 'paid', 'failed', 'refund', 'error'], true)) {
            throw new \InvalidArgumentException('Invalid basket status.');
        }
        return DB::transaction(function () use ($basketGuid, $status, $paidAt) {
            $basket = Basket::where('basket_guid', $basketGuid)->lockForUpdate()->firstOrFail();
            if (in_array($basket->status, ['paid', 'refund'], true) && !in_array($status, ['paid', 'refund'], true)) {
                throw new \LogicException('A paid/refunded basket cannot be reopened. Use a new basket GUID.');
            }
            $basket->status = $status;
            if ($status === 'paid' && $basket->paid_at === null) $basket->paid_at = $paidAt ?? now();
            $basket->save();
            return $basket;
        }, 3);
    }
}
