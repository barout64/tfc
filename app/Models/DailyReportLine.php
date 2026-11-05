<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyReportLine extends Model
{
    /** Who bought: agro dealers and farmers pay different prices. Lines from before this have no buyer. */
    public const BUYERS = ['agro_dealer' => 'Agro dealers', 'farmer' => 'Farmers'];

    protected $guarded = ['id'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(DailyReport::class, 'daily_report_id');
    }
}
