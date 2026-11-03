<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One station's sales report for one day. Totals always come from its lines, never stored separately. */
class DailyReport extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['report_date' => 'date'];
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DailyReportLine::class)->orderBy('sort');
    }

    public function totalAmount(): float
    {
        return (float) ($this->relationLoaded('lines') ? $this->lines : $this->lines()->get())->sum('amount');
    }
}
