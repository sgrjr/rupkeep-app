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

        $tasks = Task::query()
            ->with('labels')
            ->where('is_public', true)
            ->when($orgId, fn ($q) => $q->where(function ($w) use ($orgId) {
                $w->whereNull('organization_id')->orWhere('organization_id', $orgId);
            }))
            ->orderByRaw("CASE status
                WHEN 'in_progress' THEN 1
                WHEN 'verifying' THEN 2
                WHEN 'open' THEN 3
                WHEN 'triage' THEN 4
                WHEN 'done' THEN 5
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

        return view('documentation.roadmap', [
            'document' => 'roadmap',
            'tasksByStatus' => $tasks,
        ]);
    }
}
