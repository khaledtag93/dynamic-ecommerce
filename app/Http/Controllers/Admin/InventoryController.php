<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Product;

class InventoryController extends Controller
{
    public function index()
    {
        $movements = InventoryMovement::with(['product', 'variant', 'purchase', 'order'])->latest('id')->paginate(20);
        $lowStockProducts = Product::query()
            ->where(function ($query) {
                $query->where('has_variants', false)->whereColumn('quantity', '<=', 'reorder_point');
            })
            ->orWhere(function ($query) {
                $query->where('has_variants', false)->whereColumn('quantity', '<=', 'low_stock_threshold');
            })
            ->latest('id')
            ->take(12)
            ->get();

        $nearExpiryProducts = Product::query()
            ->whereNotNull('expiration_date')
            ->whereDate('expiration_date', '<=', now()->addDays(30))
            ->orderBy('expiration_date')
            ->take(12)
            ->get();

        return view('admin.inventory.index', compact('movements', 'lowStockProducts', 'nearExpiryProducts'));
    }
}
