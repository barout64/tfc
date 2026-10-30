<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payment_method' => PaymentMethod::class,
            'sold_at' => 'datetime',
            'total_amount' => 'decimal:2',
            'subsidy_amount' => 'decimal:2',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }

    public function agroDealer(): BelongsTo
    {
        return $this->belongsTo(AgroDealer::class);
    }

    public function cooperative(): BelongsTo
    {
        return $this->belongsTo(Cooperative::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sold_by');
    }

    public function customerName(): string
    {
        return match ($this->customer_type) {
            'farmer' => $this->farmer?->fullName() ?? '—',
            'agro_dealer' => $this->agroDealer?->business_name ?? '—',
            'cooperative' => $this->cooperative?->name ?? '—',
            default => '—',
        };
    }

    public function amountPaid(): float
    {
        return (float) $this->payments->where('status', 'confirmed')->sum('amount');
    }
}
