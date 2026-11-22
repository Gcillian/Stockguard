<?php

namespace App\Http\Controllers;

use App\Models\Anomaly;
use App\Models\Camera;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = request()->user();
        $transactions = Transaction::query();
        if ($user->role === 'staff') $transactions->where('user_id', $user->id);
        $recent = (clone $transactions)->with(['user', 'warehouse'])->latest()->limit(8)->get();
        return view('dashboard.index', [
            'user' => $user,
            'metrics' => [
                'transactions' => (clone $transactions)->whereDate('created_at', today())->count(),
                'pending' => (clone $transactions)->whereIn('status', ['submitted', 'scanned', 'verifying', 'review'])->count(),
                'products' => Product::count(),
                'anomalies' => $user->role === 'staff' ? Anomaly::whereHas('transaction', fn ($query) => $query->where('user_id', $user->id))->where('status', 'pending')->count() : Anomaly::where('status', 'pending')->count(),
                'verified' => (clone $transactions)->whereDate('created_at', today())->where('status', 'completed')->count(),
                'cameras_online' => Camera::where('status', 'online')->count(),
            ],
            'recent' => $recent,
            'cameras' => Camera::with('warehouse')->orderBy('name')->limit(4)->get(),
        ]);
    }
}
