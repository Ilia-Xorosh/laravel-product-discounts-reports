<?php

namespace ProductDiscounts\Reports;

use Illuminate\Database\Eloquent\Builder;
use ProductDiscounts\Reports\Models\Basket;

class ReportQuery
{
    public function build(array $filters): Builder
    {
        $filters = array_filter($filters, fn ($v) => $v !== null && $v !== '');
        $query = Basket::query();
        foreach (['basket_guid', 'order_from', 'status'] as $field) {
            if (isset($filters[$field])) $query->where($field, $filters[$field]);
        }
        $date = ($filters['date_field'] ?? '') === 'paid_at' ? 'paid_at' : 'calculated_at';
        if (isset($filters['start'])) $query->where($date, '>=', $filters['start'].' 00:00:00');
        if (isset($filters['end'])) $query->where($date, '<', \Carbon\Carbon::parse($filters['end'])->addDay()->startOfDay());
        if (isset($filters['discount'])) $query->where('discount', '>=', $filters['discount']);
        $stepFilters = array_intersect_key($filters, array_flip(['type', 'applie', 'type_discount', 'condition_id']));
        if (isset($filters['action_name_search']) || isset($filters['product_name_search']) || $stepFilters) {
            $query->whereHas('actions', function ($actions) use ($filters, $stepFilters) {
                if (isset($filters['action_name_search'])) $actions->where('name', 'like', '%'.$filters['action_name_search'].'%');
                if ($stepFilters || isset($filters['product_name_search'])) {
                    $actions->whereHas('doings', function ($doings) use ($filters, $stepFilters) {
                        foreach ($stepFilters as $field => $value) $doings->where($field, $value);
                        if (isset($filters['product_name_search'])) {
                            $doings->whereHas('products', fn ($p) => $p->where('product_name', 'like', '%'.$filters['product_name_search'].'%'));
                        }
                    });
                }
            });
        }
        return $query;
    }
}
