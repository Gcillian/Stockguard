<?php

namespace App\Http\Controllers;

use App\Models\Anomaly;
use App\Services\WithdrawalWorkflow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    public function index()
    {
        return view('reviews.index', ['anomalies' => Anomaly::with(['transaction.user', 'transaction.warehouse', 'transaction.items.product', 'transaction.visionEvents'])->where('status', 'pending')->latest('id')->get()]);
    }

    public function update(Request $request, Anomaly $review, WithdrawalWorkflow $workflow)
    {
        $data = $request->validate([
            'decision' => 'required|in:approve,reject',
            'note' => ['nullable', 'string', 'max:500', Rule::requiredIf($request->input('decision') === 'reject')],
        ]);
        if ($data['decision'] === 'approve') $workflow->approve($review, $request->user()->id, $data['note'] ?? null);
        else $workflow->reject($review, $request->user()->id, $data['note']);
        return back()->with('success', 'Keputusan review tersimpan.');
    }
}
