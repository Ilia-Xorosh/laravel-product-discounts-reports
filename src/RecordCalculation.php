<?php

namespace ProductDiscounts\Reports;

use Illuminate\Support\Facades\DB;
use ProductDiscounts\Events\CalculationCompleted;
use ProductDiscounts\Reports\Models\Basket;

class RecordCalculation
{
    public function handle(CalculationCompleted $event): void
    {
        if (!config('product-discounts-reports.recording')) return;
        DB::transaction(function () use ($event) {
            // Atomic creation + row lock serialize concurrent recalculations of the same basket.
            $now = now();
            Basket::upsert([[
                'basket_guid' => $event->basketGuid, 'order_from' => $event->orderFrom,
                'start_price' => $event->startTotal, 'final_price' => $event->result['totalPrice'],
                'discount' => $event->result['discount'], 'items' => json_encode($event->result['items'], JSON_THROW_ON_ERROR),
                'status' => 'new', 'calculated_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]], ['basket_guid'], ['basket_guid']);
            $basket = Basket::where('basket_guid', $event->basketGuid)->lockForUpdate()->firstOrFail();
            // Paid/refunded order snapshots are immutable under later calculation requests.
            if (in_array($basket->status, ['paid', 'refund'], true)) return;
            $basket->update([
                'order_from' => $event->orderFrom, 'start_price' => $event->startTotal,
                'final_price' => $event->result['totalPrice'], 'discount' => $event->result['discount'],
                'items' => $event->result['items'], 'applied_promocode' => $event->result['appliedPromocode'],
                'calculated_at' => $now,
            ]);
            $basket->actions()->delete();
            foreach ($event->actions as $trace) {
                $action = $basket->actions()->create([
                    'name' => $trace['snapshot']['name'], 'snapshot' => $trace['snapshot'],
                    'start_price' => $trace['start_price'], 'final_price' => $trace['final_price'], 'discount' => $trace['discount'],
                ]);
                foreach ($trace['doings'] as $step) {
                    $doing = $action->doings()->create([
                        ...array_intersect_key($step['snapshot'], array_flip(['name', 'type', 'applie', 'type_discount', 'condition_id'])),
                        'snapshot' => $step['snapshot'], 'start_price' => $step['start_price'],
                        'final_price' => $step['final_price'], 'discount' => $step['discount'],
                    ]);
                    $doing->products()->createMany($step['products']);
                }
            }
        }, 3);
    }
}
