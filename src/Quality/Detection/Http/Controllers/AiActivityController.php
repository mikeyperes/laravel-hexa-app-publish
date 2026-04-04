<?php

namespace hexa_app_publish\Quality\Detection\Http\Controllers;

use hexa_app_publish\Models\AiActivityLog;
use hexa_core\Models\User;
use hexa_core\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Carbon\Carbon;

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

        if ($request->filled('user_id')) $query->where('user_id', $request->input('user_id'));
        if ($request->filled('model')) $query->where('model', $request->input('model'));
        if ($request->filled('agent')) $query->where('agent', $request->input('agent'));
        if ($request->filled('date_from')) $query->whereDate('created_at', '>=', $request->input('date_from'));
        if ($request->filled('date_to')) $query->whereDate('created_at', '<=', $request->input('date_to'));

        $logs = $query->paginate(50);

        // Top summary stats
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

        // Cost summary by model and time period
        $periods = [
            '4h'    => Carbon::now()->subHours(4),
            '24h'   => Carbon::now()->subHours(24),
            '1w'    => Carbon::now()->subWeek(),
            '1m'    => Carbon::now()->subMonth(),
            '1y'    => Carbon::now()->subYear(),
        ];

        $allModels = AiActivityLog::distinct()->pluck('model')->sort()->values();
        $costSummary = [];

        // Provider totals
        foreach (['anthropic' => 'Claude', 'openai' => 'OpenAI'] as $provider => $label) {
            $row = ['label' => $label, 'type' => 'provider'];
            foreach ($periods as $key => $since) {
                $row[$key] = AiActivityLog::where('provider', $provider)->where('created_at', '>=', $since)->sum('cost');
            }
            $costSummary[] = $row;
        }

        // Per-model totals
        foreach ($allModels as $model) {
            $row = ['label' => $model, 'type' => 'model'];
            foreach ($periods as $key => $since) {
                $row[$key] = AiActivityLog::where('model', $model)->where('created_at', '>=', $since)->sum('cost');
            }
            $costSummary[] = $row;
        }

        $users = User::orderBy('name')->get(['id', 'name', 'email']);
        $agents = AiActivityLog::distinct()->pluck('agent')->sort()->values();

        return view('app-publish::ai-activity.index', [
            'logs'        => $logs,
            'summary'     => $summary,
            'costSummary' => $costSummary,
            'users'       => $users,
            'models'      => $allModels,
            'agents'      => $agents,
            'filters'     => $request->only(['user_id', 'model', 'agent', 'date_from', 'date_to']),
        ]);
    }
}
