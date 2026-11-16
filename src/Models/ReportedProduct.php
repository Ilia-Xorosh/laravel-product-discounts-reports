<?php

namespace ProductDiscounts\Reports\Models;

use Illuminate\Database\Eloquent\Model;

class ReportedProduct extends Model
{
    protected $table = 'pd_report_products';
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['quantity' => 'integer', 'start_subtotal' => 'integer', 'final_subtotal' => 'integer', 'discount' => 'integer'];
}
