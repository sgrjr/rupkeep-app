<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;

class DocumentationController extends Controller
{
    public function index()
    {
        return view('documentation.index');
    }

    public function show($document = 'onboarding')
    {
        $availableDocuments = [
            'onboarding' => 'onboarding',
            'roadmap' => 'roadmap',
        ];

        $documentName = $availableDocuments[$document] ?? 'onboarding';

        if ($documentName === 'roadmap') {
            return $this->roadmap();
        }

        return view("documentation.{$documentName}", [
            'document' => $documentName,
        ]);
    }

    public function roadmap()
    {
        $user = auth()->user();
        $orgId = $user?->organization_id;

        $orgScope = fn ($q) => $q->where('is_public', true)
            ->when($orgId, fn ($w) => $w->where(function ($scope) use ($orgId) {
                $scope->whereNull('organization_id')->orWhere('organization_id', $orgId);
            }));

        $tasks = Task::query()
            ->with('labels')
            ->tap($orgScope)
            ->orderByRaw("CASE status
                WHEN 'in_progress' THEN 1
                WHEN 'triage' THEN 2
                WHEN 'done' THEN 3
                WHEN 'open' THEN 4
                WHEN 'verifying' THEN 5
                WHEN 'declined' THEN 6
                ELSE 99 END")
            ->orderByRaw("CASE priority
                WHEN 'blocker' THEN 1
                WHEN 'high' THEN 2
                WHEN 'medium' THEN 3
                WHEN 'low' THEN 4
                ELSE 99 END")
            ->orderBy('code')
            ->get()
            ->groupBy('status');

        $lastUpdatedAt = Task::query()->tap($orgScope)->max('updated_at');

        // Cumulative-progress counter: every "done" task ever, regardless of
        // is_public, scoped to the viewer's org. Gives a sense of "we've
        // shipped N things" without exposing the individual items.
        $totalShipped = Task::query()
            ->where('status', 'done')
            ->when($orgId, fn ($q) => $q->where(function ($scope) use ($orgId) {
                $scope->whereNull('organization_id')->orWhere('organization_id', $orgId);
            }))
            ->count();

        return view('documentation.roadmap', [
            'document' => 'roadmap',
            'tasksByStatus' => $tasks,
            'lastUpdatedAt' => $lastUpdatedAt ? \Carbon\Carbon::parse($lastUpdatedAt) : null,
            'totalPublic' => $tasks->sum->count(),
            'totalShipped' => $totalShipped,
        ]);
    }
}
