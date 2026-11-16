<?php

namespace ProductDiscounts\Reports\Models;

use Illuminate\Database\Eloquent\Model;

class ReportedAction extends Model
{
    protected $table = 'pd_report_actions';
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['snapshot' => 'array', 'start_price' => 'integer', 'final_price' => 'integer', 'discount' => 'integer'];
    public function doings() { return $this->hasMany(ReportedDoing::class, 'action_id')->orderBy('id'); }
}
