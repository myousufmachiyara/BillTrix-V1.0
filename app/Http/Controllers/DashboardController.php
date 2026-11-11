<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(ReportService $reports): View
    {
        $user = auth()->user();

        return view('dashboard', [
            'stats' => $user->can('dashboard.view') ? $reports->dashboard() : null,
        ]);
    }
}
