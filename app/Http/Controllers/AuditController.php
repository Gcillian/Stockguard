<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;

class AuditController extends Controller
{
    public function index()
    {
        return view('audits.index', ['logs' => AuditLog::with(['user', 'transaction'])->orderByDesc('id')->paginate(30)]);
    }
}
