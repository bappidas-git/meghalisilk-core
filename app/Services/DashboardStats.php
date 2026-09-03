<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReturn;
use App\Models\User;

/**
 * GET /admin/dashboard/stats — the mock's arithmetic (guide §30).
 */
class DashboardStats
{
    public function compute(): array
    {
        $revenue = Order::query();
        if (config('store.revenue_excludes_cancelled')) {
            $revenue->where('fulfillment_status', '!=', 'cancelled')->whereNotIn('payment_status', ['refunded', 'failed']);
        }

        return [
            'totalProducts' => Product::query()->count(),
            'totalOrders' => Order::query()->count(),
            'totalRevenue' => (int) $revenue->sum('total'),
            'totalUsers' => User::query()->count(),
            'pendingOrders' => Order::query()
                ->where(fn ($q) => $q->where('fulfillment_status', 'unfulfilled')->orWhere('payment_status', 'pending'))
                ->count(),
            'pendingReturns' => ProductReturn::query()
                ->whereNotIn('status', ['rejected', 'refunded'])
                ->whereNotIn('refund_status', ['processed', 'completed'])
                ->count(),
            'lowStockProducts' => Product::query()
                ->whereRaw('stock <= COALESCE(NULLIF(low_stock_threshold, 0), 10)')
                ->count(),
            'activeCoupons' => Coupon::query()->where('is_active', true)->count(),
        ];
    }
}
