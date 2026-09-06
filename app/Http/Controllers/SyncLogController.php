<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SyncLogController extends Controller
{
    public function index(Request $request): Response
    {
        $logs = $request->user()->syncLogs()
            ->with('directoryUser:id,display_name')
            ->latest()
            ->paginate(50);

        return Inertia::render('Logs/Index', [
            'logs' => $logs,
        ]);
    }
}
