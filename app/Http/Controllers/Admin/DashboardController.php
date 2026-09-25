<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Services\Access;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, Access $access): Response
    {
        $user = $request->user();
        abort_unless($access->any($user, 'dashboard.view'), 403);
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'], 'segment_id' => ['nullable', 'integer'], 'branch_id' => ['nullable', 'integer']]);
        $from = $data['from'] ?? now()->subDays(29)->toDateString();
        $to = $data['to'] ?? now()->toDateString();
        $orders = $access->scope($user, Order::query(), 'orders.view')->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59']);
        $stock = $access->scope($user, Inventory::query(), 'stock.view');
        $products = $access->scope($user, Product::query(), 'products.view')->where('active', true);
        if (! empty($data['segment_id'])) {
            $orders->where('segment_id', $data['segment_id']);
            $products->where('segment_id', $data['segment_id']);
            $stock->whereHas('product', fn ($q) => $q->where('segment_id', $data['segment_id']));
        }
        if (! empty($data['branch_id'])) {
            $orders->where('branch_id', $data['branch_id']);
            $stock->where('branch_id', $data['branch_id']);
            $products->whereHas('inventories', fn ($q) => $q->where('branch_id', $data['branch_id']));
        }
        $days = (clone $orders)->selectRaw('DATE(created_at) as day, COUNT(*) as total')->groupByRaw('DATE(created_at)')->orderBy('day')->get();
        $bySegment = (clone $orders)->selectRaw('segment_id, COUNT(*) as total')->groupBy('segment_id')->with('segment:id,name')->get();

        return Inertia::render('Admin/Dashboard', [
            'stats' => ['products' => $products->count(), 'low' => (clone $stock)->where('quantity', '>', 0)->whereColumn('quantity', '<=', 'minimum_quantity')->count(), 'empty' => (clone $stock)->where('quantity', 0)->count(), 'orders' => (clone $orders)->count()],
            'days' => $days, 'bySegment' => $bySegment, 'from' => $from, 'to' => $to,
            'lowStock' => (clone $stock)->with(['product:id,name', 'branch:id,name'])->whereColumn('quantity', '<=', 'minimum_quantity')->limit(8)->get(),
            'latestOrders' => (clone $orders)->with('branch:id,name')->latest()->limit(5)->get(['id', 'public_id', 'status', 'branch_id', 'created_at']),
        ]);
    }
}
