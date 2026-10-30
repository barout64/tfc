<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Collection;

abstract class Controller
{
    /**
     * Warehouse ids the current user is restricted to, or null for "all".
     *
     * @return list<int>|null
     */
    protected function warehouseScope(): ?array
    {
        $user = auth()->user();

        return $user?->isScopedToWarehouse() ? [$user->warehouse_id] : null;
    }

    /**
     * @return Collection<int, Warehouse>
     */
    protected function scopedWarehouses(): Collection
    {
        return Warehouse::active()
            ->where('type', '!=', 'agro_dealer')
            ->when($this->warehouseScope(), fn ($q, $ids) => $q->whereIn('id', $ids))
            ->orderBy('name')
            ->get();
    }

    protected function authorizeWarehouse(Warehouse $warehouse): void
    {
        $scope = $this->warehouseScope();
        abort_if($scope !== null && ! in_array($warehouse->id, $scope, true), 403, __('This warehouse is outside your assignment.'));
    }
}
