<?php

namespace App\Livewire;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;
use Livewire\Component;

class RestoreButton extends Component
{
    use AuthorizesRequests;

    public string $actionUrl;
    public string $buttonText;
    public ?string $redirectRoute;
    public ?string $modelClass;
    public ?string $resource;
    public ?string $recordId;

    public bool $confirmingRestore = false;

    public function mount(
        string $actionUrl,
        string $buttonText = 'Restore',
        ?string $redirectRoute = null,
        ?string $modelClass = null,
        ?string $resource = null,
        ?string $recordId = null
    ): void {
        $this->actionUrl = $actionUrl;
        $this->buttonText = $buttonText;
        $this->redirectRoute = $redirectRoute;
        $this->modelClass = $modelClass;
        $this->resource = $resource;
        $this->recordId = $recordId;
    }

    public function confirmRestore(): void
    {
        $this->confirmingRestore = true;
    }

    public function restore()
    {
        try {
            $model = $this->resolveTargetModel();

            if (! $model instanceof Model) {
                session()->flash('error', 'Unable to locate the record to restore.');
                $this->confirmingRestore = false;
                return null;
            }

            $this->authorize('restore', $model);

            if ($this->usesSoftDeletes($model)) {
                $model->restore();
                session()->flash('message', $this->successMessage('restored'));
            } else {
                session()->flash('error', 'This record does not support restoration.');
            }
        } catch (\Throwable $e) {
            report($e);
            session()->flash('error', 'Failed to restore item: '.$e->getMessage());
        }

        $this->confirmingRestore = false;

        if ($this->redirectRoute) {
            return redirect()->route($this->redirectRoute);
        }

        return redirect()->back();
    }

    protected function resolveTargetModel(): ?Model
    {
        $modelClass = $this->modelClass ?? $this->inferModelClassFromUrl();

        if (! $modelClass || ! class_exists($modelClass)) {
            return null;
        }

        $query = $modelClass::query();

        if ($this->usesSoftDeletes($modelClass)) {
            $query->withTrashed();
        }

        $id = $this->recordId ?? $this->extractIdFromActionUrl();

        if (! $id) {
            return null;
        }

        return $query->find($id);
    }

    protected function inferModelClassFromUrl(): ?string
    {
        $path = parse_url($this->actionUrl, PHP_URL_PATH) ?? '';
        $segments = array_values(array_filter(explode('/', $path)));
        $resource = $segments[count($segments) - 2] ?? null;

        return match ($resource) {
            'jobs', 'my' => \App\Models\PilotCarJob::class,
            'vehicles' => \App\Models\Vehicle::class,
            'users' => \App\Models\User::class,
            'logs' => \App\Models\UserLog::class,
            'customers' => \App\Models\Customer::class,
            'invoices' => \App\Models\Invoice::class,
            default => null,
        };
    }

    protected function extractIdFromActionUrl(): ?string
    {
        $path = parse_url($this->actionUrl, PHP_URL_PATH) ?? '';
        $segments = array_values(array_filter(explode('/', $path)));

        return $segments[count($segments) - 1] ?? null;
    }

    protected function successMessage(string $action): string
    {
        $label = $this->resource
            ? Str::headline(Str::singular($this->resource))
            : __('record');

        return "{$label} {$action} successfully.";
    }

    protected function usesSoftDeletes(Model|string $model): bool
    {
        $class = $model instanceof Model ? $model::class : $model;

        return in_array(SoftDeletes::class, class_uses_recursive($class), true);
    }

    public function render()
    {
        return view('livewire.restore-button');
    }
}
