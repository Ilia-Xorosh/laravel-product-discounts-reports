<?php

namespace ProductDiscounts\Reports\Models;

use Illuminate\Database\Eloquent\Model;

class ReportedDoing extends Model
{
    protected $table = 'pd_report_doings';
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['snapshot' => 'array', 'condition_id' => 'integer', 'start_price' => 'integer', 'final_price' => 'integer', 'discount' => 'integer'];
    public function products() { return $this->hasMany(ReportedProduct::class, 'doing_id')->orderBy('id'); }
}
