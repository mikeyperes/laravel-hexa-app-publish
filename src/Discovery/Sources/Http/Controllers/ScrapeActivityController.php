<?php

namespace hexa_app_publish\Discovery\Sources\Http\Controllers;

use hexa_app_publish\Discovery\Sources\Models\BannedSource;
use hexa_app_publish\Discovery\Sources\Models\ScrapeLog;
use hexa_app_publish\Discovery\Sources\Models\SourceNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ScrapeActivityController extends Controller
{
    /**
     * Scrape activity page.
     */
    public function index(Request $request)
    {
        $query = ScrapeLog::orderByDesc('created_at');

        if ($domain = $request->input('domain')) {
            $query->where('domain', $domain);
        }
        if ($request->input('failures_only')) {
            $query->where('success', false);
        }

        $logs = $query->paginate(50);

        // Domain stats
        $domainStats = ScrapeLog::selectRaw('domain, COUNT(*) as total, SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as fails, SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as passes')
            ->groupBy('domain')
            ->orderByDesc('fails')
            ->limit(50)
            ->get();

        $bannedDomains = BannedSource::pluck('domain')->toArray();
        $sourceNotes = SourceNote::pluck('notes', 'domain')->toArray();

        return view('app-publish::discovery.scrape-activity.index', [
            'logs' => $logs,
            'domainStats' => $domainStats,
            'bannedDomains' => $bannedDomains,
            'sourceNotes' => $sourceNotes,
            'filterDomain' => $domain,
        ]);
    }

    /**
     * Ban a source domain.
     */
    public function ban(Request $request): JsonResponse
    {
        $request->validate(['domain' => 'required|string|max:255', 'reason' => 'nullable|string|max:500']);

        BannedSource::updateOrCreate(
            ['domain' => $request->input('domain')],
            ['reason' => $request->input('reason', 'Blocked from scrape activity page'), 'banned_by' => auth()->id()]
        );

        return response()->json(['success' => true, 'message' => 'Source banned: ' . $request->input('domain')]);
    }

    /**
     * Unban a source domain.
     */
    public function unban(Request $request): JsonResponse
    {
        BannedSource::where('domain', $request->input('domain'))->delete();
        return response()->json(['success' => true, 'message' => 'Source unbanned.']);
    }

    /**
     * Save or update notes for a source domain.
     */
    public function saveNote(Request $request): JsonResponse
    {
        $request->validate([
            'domain' => 'required|string|max:255',
            'notes' => 'nullable|string|max:5000',
            'recommended_method' => 'nullable|string|max:50',
            'recommended_ua' => 'nullable|string|max:50',
            'working_instructions' => 'nullable|string|max:5000',
        ]);

        SourceNote::updateOrCreate(
            ['domain' => $request->input('domain')],
            [
                'notes' => $request->input('notes'),
                'recommended_method' => $request->input('recommended_method'),
                'recommended_ua' => $request->input('recommended_ua'),
                'working_instructions' => $request->input('working_instructions'),
                'updated_by' => auth()->id(),
            ]
        );

        return response()->json(['success' => true, 'message' => 'Notes saved.']);
    }

    /**
     * Get note for a domain (AJAX).
     */
    public function getNote(Request $request): JsonResponse
    {
        $note = SourceNote::where('domain', $request->input('domain'))->first();
        return response()->json(['success' => true, 'note' => $note]);
    }
}
