<?php

namespace App\Livewire;

use App\Models\NotificationDelivery;
use App\Models\PilotCarJob;
use App\Models\UserLog;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Every text, email and push sent to the organization's people (TASK-498),
 * with what each person did about it where that can be known: a driver told
 * about a job either confirmed it, denied it, or has not answered yet.
 *
 * Admins and managers only. A text or an email cannot report that it was
 * read, so the timeline never claims it.
 */
class NotificationTimeline extends Component
{
    use WithPagination;

    #[Url(as: 'nq')]
    public string $search = '';

    #[Url(as: 'nchannel')]
    public string $channel = '';

    #[Url(as: 'nstatus')]
    public string $status = '';

    #[Url(as: 'nkind')]
    public string $kind = '';

    public function mount(): void
    {
        $this->authorizeViewer();
    }

    public function updating($property): void
    {
        if (in_array($property, ['search', 'channel', 'status', 'kind'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        abort_unless($this->viewerMayRead(), 403);

        $this->reset('search', 'channel', 'status', 'kind');
        $this->resetPage();
    }

    private function viewerMayRead(): bool
    {
        $viewer = Auth::user();

        return (bool) ($viewer && ($viewer->isSuper() || $viewer->isAdmin() || $viewer->isManager()));
    }

    private function authorizeViewer(): void
    {
        abort_unless($this->viewerMayRead(), 403);
    }

    public function render()
    {
        // render() runs on every request, including actions: authorize here too.
        $this->authorizeViewer();

        $organizationId = Auth::user()->organization_id;

        $deliveries = NotificationDelivery::query()
            ->with(['user', 'related'])
            ->where('organization_id', $organizationId)
            ->when($this->channel !== '', fn ($q) => $q->where('channel', $this->channel))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->kind !== '', fn ($q) => $q->where('kind', $this->kind))
            ->when(trim($this->search) !== '', function ($q) {
                $term = '%'.trim($this->search).'%';
                $q->where(function ($q) use ($term) {
                    $q->where('recipient', 'like', $term)
                        ->orWhere('subject', 'like', $term)
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term));
                });
            })
            ->latest('id')
            ->paginate(25);

        return view('livewire.notification-timeline', [
            'deliveries' => $deliveries,
            'responses' => $this->responsesFor($deliveries->getCollection()),
            'kinds' => NotificationDelivery::KIND_LABELS,
        ]);
    }

    /**
     * For a notice about a job sent to one of its drivers: that driver's log
     * on the job says what they did. Keyed by delivery id.
     *
     * @return array<int, array{label: string, tone: string}>
     */
    private function responsesFor($deliveries): array
    {
        $pairs = $deliveries
            ->filter(fn ($d) => $d->user_id && $d->related_type === (new PilotCarJob)->getMorphClass() && $d->related_id)
            ->map(fn ($d) => [$d->related_id, $d->user_id]);

        if ($pairs->isEmpty()) {
            return [];
        }

        $logs = UserLog::query()
            ->whereIn('job_id', $pairs->pluck(0)->unique())
            ->whereIn('car_driver_id', $pairs->pluck(1)->unique())
            ->get(['id', 'job_id', 'car_driver_id', 'approval_status', 'completed_at'])
            ->keyBy(fn ($log) => $log->job_id.':'.$log->car_driver_id);

        $responses = [];
        foreach ($deliveries as $delivery) {
            $log = $logs->get($delivery->related_id.':'.$delivery->user_id);
            if (! $log) {
                continue;
            }

            $responses[$delivery->id] = match (true) {
                $log->completed_at !== null => ['label' => __('Completed the log'), 'tone' => 'emerald'],
                $log->approval_status === 'confirmed' => ['label' => __('Confirmed the job'), 'tone' => 'emerald'],
                $log->approval_status === 'denied' => ['label' => __('Denied the job'), 'tone' => 'red'],
                default => ['label' => __('No answer yet'), 'tone' => 'amber'],
            };
        }

        return $responses;
    }
}
