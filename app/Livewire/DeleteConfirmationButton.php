<?php

namespace App\Livewire;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;
use Livewire\Component;

class DeleteConfirmationButton extends Component
{
    use AuthorizesRequests;

    public string $actionUrl;
    public string $buttonText;
    public ?string $redirectRoute;
    public ?string $modelClass;
    public ?string $resource;
    public ?string $recordId;
    public bool $force = false;

    public bool $confirmingDelete = false;

    public function mount(
        string $actionUrl,
        string $buttonText = 'Delete',
        ?string $redirectRoute = null,
        ?string $modelClass = null,
        ?string $resource = null,
        ?string $recordId = null,
        bool $force = false
    ): void {
        $this->actionUrl = $actionUrl;
        $this->buttonText = $buttonText;
        $this->redirectRoute = $redirectRoute;
        $this->modelClass = $modelClass;
        $this->resource = $resource;
        $this->recordId = $recordId;
        $this->force = $force;
    }

    public function confirmDelete(): void
    {
        $this->confirmingDelete = true;
    }

    public function delete()
    {
        $modelId = null;
        $wasSoftDeleted = false;
        $jobId = null; // Store job_id for log deletions
        
        try {
            $model = $this->resolveTargetModel();

            if (! $model instanceof Model) {
                session()->flash('error', 'Unable to locate the record to delete.');
                $this->confirmingDelete = false;
                return null;
            }

            $modelId = $model->id;
            $wasSoftDeleted = $this->usesSoftDeletes($model) && !$this->force;

            // If deleting a log from a job page, store the job_id before deletion
            if ($model instanceof \App\Models\UserLog && $model->job_id) {
                $jobId = $model->job_id;
            }

            $ability = $this->force ? 'forceDelete' : 'delete';
            $this->authorize($ability, $model);

            if ($this->force && $this->usesSoftDeletes($model)) {
                $model->forceDelete();
                session()->flash('message', $this->successMessage('permanently deleted'));
            } else {
                $model->delete();
                session()->flash('message', $this->successMessage('archived'));
            }
        } catch (\Throwable $e) {
            report($e);
            session()->flash('error', 'Failed to delete item: '.$e->getMessage());
        }

        $this->confirmingDelete = false;

        if ($this->redirectRoute) {
            try {
                // Try to generate the route - if it requires parameters and model is soft-deleted, it will fail
                $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName($this->redirectRoute);
                
                // Check if route requires parameters
                $parameters = $route->parameterNames();
                
                // Special handling: if deleting a log from a job page, redirect back to that job
                if ($jobId && (str_contains($this->redirectRoute, 'jobs.show'))) {
                    // Determine which route name to use based on the current route
                    $jobRouteName = str_contains($this->redirectRoute, 'my.jobs') ? 'my.jobs.show' : 'jobs.show';
                    return redirect()->route($jobRouteName, ['job' => $jobId]);
                }
                
                if (!empty($parameters) && $wasSoftDeleted) {
                    // Route requires parameters but model is soft-deleted
                    // Try to infer the index route from the show route
                    $indexRoute = $this->inferIndexRoute($this->redirectRoute);
                    if ($indexRoute) {
                        return redirect()->route($indexRoute);
                    }
                    // Fallback to jobs index if it's a job
                    if (str_contains($this->redirectRoute, 'jobs')) {
                        return redirect()->route('my.jobs.index');
                    }
                }
                
                // If route doesn't need parameters or we have them, try to redirect
                if (empty($parameters)) {
                    return redirect()->route($this->redirectRoute);
                } else {
                    // Route needs parameters - try with stored ID if available
                    if ($modelId) {
                        try {
                            return redirect()->route($this->redirectRoute, ['job' => $modelId]);
                        } catch (\Exception $e) {
                            // If that fails, redirect to index
                            $indexRoute = $this->inferIndexRoute($this->redirectRoute);
                            if ($indexRoute) {
                                return redirect()->route($indexRoute);
                            }
                            return redirect()->route('my.jobs.index');
                        }
                    }
                }
            } catch (\Exception $e) {
                // If route generation fails, redirect to index
                $indexRoute = $this->inferIndexRoute($this->redirectRoute);
                if ($indexRoute) {
                    return redirect()->route($indexRoute);
                }
                if (str_contains($this->redirectRoute, 'jobs')) {
                    return redirect()->route('my.jobs.index');
                }
            }
        }

        return redirect()->back();
    }
    
    protected function inferIndexRoute(string $routeName): ?string
    {
        // Convert show routes to index routes
        return match(true) {
            str_contains($routeName, 'jobs.show') => 'my.jobs.index',
            str_contains($routeName, 'customers.show') => 'my.customers.index',
            str_contains($routeName, 'invoices.edit') => 'my.invoices.index',
            default => null,
        };
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
            'attachments' => \App\Models\Attachment::class,
            'logs' => \App\Models\UserLog::class,
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
        return view('livewire.delete-confirmation-button');
    }
}
