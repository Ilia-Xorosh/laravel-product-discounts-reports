<?php

namespace ProductDiscounts\Reports\Models;

use Illuminate\Database\Eloquent\Model;

class Basket extends Model
{
    protected $table = 'pd_report_baskets';
    protected $guarded = ['id'];
    protected $casts = ['items' => 'array', 'paid_at' => 'datetime', 'calculated_at' => 'datetime', 'start_price' => 'integer', 'final_price' => 'integer', 'discount' => 'integer'];
    public function actions() { return $this->hasMany(ReportedAction::class, 'basket_id')->orderBy('id'); }
}
