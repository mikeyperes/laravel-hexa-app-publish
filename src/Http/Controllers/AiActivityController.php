<?php

namespace hexa_app_publish\Http\Controllers;

use hexa_app_publish\Models\AiActivityLog;
use hexa_core\Models\User;
use hexa_core\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

/**
 * AI Activity log — tracks every AI API request with cost and content.
 */
class AiActivityController extends Controller
{
    /**
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $query = AiActivityLog::with('user')->orderByDesc('created_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }
        if ($request->filled('model')) {
            $query->where('model', $request->input('model'));
        }
        if ($request->filled('agent')) {
            $query->where('agent', $request->input('agent'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $logs = $query->paginate(50);

        // Summary stats
        $summaryQuery = AiActivityLog::query();
        if ($request->filled('user_id')) $summaryQuery->where('user_id', $request->input('user_id'));
        if ($request->filled('model')) $summaryQuery->where('model', $request->input('model'));
        if ($request->filled('agent')) $summaryQuery->where('agent', $request->input('agent'));
        if ($request->filled('date_from')) $summaryQuery->whereDate('created_at', '>=', $request->input('date_from'));
        if ($request->filled('date_to')) $summaryQuery->whereDate('created_at', '<=', $request->input('date_to'));

        $summary = [
            'total_requests'  => $summaryQuery->count(),
            'total_tokens'    => $summaryQuery->sum('total_tokens'),
            'total_cost'      => $summaryQuery->sum('cost'),
            'success_count'   => (clone $summaryQuery)->where('success', true)->count(),
            'error_count'     => (clone $summaryQuery)->where('success', false)->count(),
        ];

        $users = User::orderBy('name')->get(['id', 'name']);
        $models = AiActivityLog::distinct()->pluck('model')->sort()->values();
        $agents = AiActivityLog::distinct()->pluck('agent')->sort()->values();

        return view('app-publish::ai-activity.index', [
            'logs'    => $logs,
            'summary' => $summary,
            'users'   => $users,
            'models'  => $models,
            'agents'  => $agents,
            'filters' => $request->only(['user_id', 'model', 'agent', 'date_from', 'date_to']),
        ]);
    }
}
