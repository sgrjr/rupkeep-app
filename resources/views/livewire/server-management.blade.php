@php use Illuminate\Support\Str; @endphp
<div class="space-y-6">
    <div class="rounded-3xl border border-slate-200 bg-white/80 p-6 shadow-sm">
        <header class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900">{{ __('Server Management') }}</h1>
            <p class="mt-2 text-sm text-slate-600">{{ __('Execute server management commands without SSH access. All commands include full output buffers.') }}</p>
        </header>

        <!-- Command Buttons -->
        <div class="space-y-6">
            <!-- Git Commands -->
            <div>
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500">{{ __('Git Commands') }}</h2>
                <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3">
                    <p class="text-sm text-amber-800">
                        <strong>{{ __('Note:') }}</strong> {{ __('Git pull and git-related commands are not functioning correctly yet and need to be troubleshooted and fixed.') }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button wire:click="executeCommand('git_pull')" 
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 rounded-full border border-orange-200 bg-orange-50 px-4 py-2 text-sm font-semibold text-orange-600 transition hover:bg-orange-500 hover:text-white disabled:opacity-50">
                        <svg wire:loading.remove wire:target="executeCommand('git_pull')" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v6h6M20 20v-6h-6M5.17 18.83A9 9 0 0 0 18.83 5.17M18 9V4h-5"/>
                        </svg>
                        <span wire:loading wire:target="executeCommand('git_pull')" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-orange-600 border-t-transparent"></span>
                        {{ __('Pull Latest Code') }}
                    </button>
                    <button wire:click="executeCommand('git_add_all')" 
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 rounded-full border border-orange-200 bg-orange-50 px-4 py-2 text-sm font-semibold text-orange-600 transition hover:bg-orange-500 hover:text-white disabled:opacity-50">
                        <span wire:loading wire:target="executeCommand('git_add_all')" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-orange-600 border-t-transparent"></span>
                        {{ __('Stage All Changes') }}
                    </button>
                    <button wire:click="executeCommand('git_commit')" 
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 rounded-full border border-orange-200 bg-orange-50 px-4 py-2 text-sm font-semibold text-orange-600 transition hover:bg-orange-500 hover:text-white disabled:opacity-50">
                        <span wire:loading wire:target="executeCommand('git_commit')" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-orange-600 border-t-transparent"></span>
                        {{ __('Commit (server:update)') }}
                    </button>
                    <button wire:click="executeCommand('git_push')" 
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 rounded-full border border-orange-200 bg-orange-50 px-4 py-2 text-sm font-semibold text-orange-600 transition hover:bg-orange-500 hover:text-white disabled:opacity-50">
                        <span wire:loading wire:target="executeCommand('git_push')" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-orange-600 border-t-transparent"></span>
                        {{ __('Push to Remote') }}
                    </button>
                </div>
            </div>

            <!-- Artisan Commands -->
            <div>
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500">{{ __('Artisan Commands') }}</h2>
                <div class="flex flex-wrap gap-2">
                    <button wire:click="executeCommand('artisan_assets_build')" 
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 rounded-full border border-orange-200 bg-orange-50 px-4 py-2 text-sm font-semibold text-orange-600 transition hover:bg-orange-500 hover:text-white disabled:opacity-50">
                        <span wire:loading wire:target="executeCommand('artisan_assets_build')" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-orange-600 border-t-transparent"></span>
                        {{ __('Build Assets') }}
                    </button>
                    <button wire:click="executeCommand('artisan_optimize_clear')" 
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 rounded-full border border-orange-200 bg-orange-50 px-4 py-2 text-sm font-semibold text-orange-600 transition hover:bg-orange-500 hover:text-white disabled:opacity-50">
                        <span wire:loading wire:target="executeCommand('artisan_optimize_clear')" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-orange-600 border-t-transparent"></span>
                        {{ __('Clear All Caches') }}
                    </button>
                    <button wire:click="executeCommand('artisan_optimize')" 
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 rounded-full border border-orange-200 bg-orange-50 px-4 py-2 text-sm font-semibold text-orange-600 transition hover:bg-orange-500 hover:text-white disabled:opacity-50">
                        <span wire:loading wire:target="executeCommand('artisan_optimize')" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-orange-600 border-t-transparent"></span>
                        {{ __('Optimize Application') }}
                    </button>
                    <button wire:click="executeCommand('artisan_config_clear')" 
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 rounded-full border border-orange-200 bg-orange-50 px-4 py-2 text-sm font-semibold text-orange-600 transition hover:bg-orange-500 hover:text-white disabled:opacity-50">
                        <span wire:loading wire:target="executeCommand('artisan_config_clear')" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-orange-600 border-t-transparent"></span>
                        {{ __('Clear Config Cache') }}
                    </button>
                    <button wire:click="executeCommand('artisan_cache_clear')" 
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 rounded-full border border-orange-200 bg-orange-50 px-4 py-2 text-sm font-semibold text-orange-600 transition hover:bg-orange-500 hover:text-white disabled:opacity-50">
                        <span wire:loading wire:target="executeCommand('artisan_cache_clear')" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-orange-600 border-t-transparent"></span>
                        {{ __('Clear Application Cache') }}
                    </button>
                    <button wire:click="executeCommand('artisan_view_clear')" 
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 rounded-full border border-orange-200 bg-orange-50 px-4 py-2 text-sm font-semibold text-orange-600 transition hover:bg-orange-500 hover:text-white disabled:opacity-50">
                        <span wire:loading wire:target="executeCommand('artisan_view_clear')" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-orange-600 border-t-transparent"></span>
                        {{ __('Clear View Cache') }}
                    </button>
                    <button wire:click="executeCommand('artisan_migrate')" 
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 rounded-full border border-orange-200 bg-orange-50 px-4 py-2 text-sm font-semibold text-orange-600 transition hover:bg-orange-500 hover:text-white disabled:opacity-50">
                        <span wire:loading wire:target="executeCommand('artisan_migrate')" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-orange-600 border-t-transparent"></span>
                        {{ __('Run Migrations') }}
                    </button>
                    <button wire:click="executeCommand('artisan_migrate_rollback')" 
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 rounded-full border border-orange-200 bg-orange-50 px-4 py-2 text-sm font-semibold text-orange-600 transition hover:bg-orange-500 hover:text-white disabled:opacity-50">
                        <span wire:loading wire:target="executeCommand('artisan_migrate_rollback')" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-orange-600 border-t-transparent"></span>
                        {{ __('Rollback Migration') }}
                    </button>
                    <button wire:click="executeCommand('artisan_redis_health')" 
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 rounded-full border border-purple-200 bg-purple-50 px-4 py-2 text-sm font-semibold text-purple-600 transition hover:bg-purple-500 hover:text-white disabled:opacity-50">
                        <svg wire:loading.remove wire:target="executeCommand('artisan_redis_health')" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span wire:loading wire:target="executeCommand('artisan_redis_health')" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-purple-600 border-t-transparent"></span>
                        {{ __('Redis Health Check') }}
                    </button>
                </div>
            </div>

            <!-- Workflow Groups -->
            <div>
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500">{{ __('Workflow Groups') }}</h2>
                <div class="flex flex-wrap gap-2">
                    <button wire:click="executeWorkflow('deploy_update')" 
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-500 hover:text-white disabled:opacity-50">
                        <span wire:loading wire:target="executeWorkflow('deploy_update')" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-emerald-600 border-t-transparent"></span>
                        {{ __('Deploy Update') }}
                    </button>
                    <button wire:click="executeWorkflow('full_deploy')" 
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-500 hover:text-white disabled:opacity-50">
                        <span wire:loading wire:target="executeWorkflow('full_deploy')" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-emerald-600 border-t-transparent"></span>
                        {{ __('Full Deploy') }}
                    </button>
                    <button wire:click="executeWorkflow('clear_all')" 
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-700 transition hover:bg-amber-500 hover:text-white disabled:opacity-50">
                        <span wire:loading wire:target="executeWorkflow('clear_all')" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-amber-600 border-t-transparent"></span>
                        {{ __('Clear All Caches') }}
                    </button>
                </div>
            </div>

            <!-- Queue Jobs -->
            <div>
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">{{ __('Queue Jobs') }}</h2>
                    @if(!$queueJobsLoaded)
                        <button wire:click="loadQueueJobs" 
                                class="inline-flex items-center gap-2 rounded-full border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-600 transition hover:bg-blue-500 hover:text-white">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/>
                            </svg>
                            {{ __('Load Queue Jobs') }}
                        </button>
                    @endif
                </div>

                @if($queueJobsLoaded)
                    <div class="mb-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="font-semibold text-slate-700">Total Pending Jobs:</span>
                                <span class="ml-2 text-slate-900">{{ $queueStats['total_jobs'] }}</span>
                            </div>
                            <div>
                                <span class="font-semibold text-red-700">Total Failed Jobs:</span>
                                <span class="ml-2 text-red-900">{{ $queueStats['total_failed'] }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Pending Jobs -->
                    @if(count($queueJobs) > 0)
                        <div class="mb-6">
                            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-600">Pending Jobs (Latest 50)</h3>
                            <div class="rounded-lg border border-slate-200 bg-white overflow-hidden">
                                <div class="max-h-[400px] overflow-y-auto">
                                    <table class="min-w-full divide-y divide-slate-200">
                                        <thead class="bg-slate-50 sticky top-0">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-700">ID</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-700">Job</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-700">Queue</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-700">Attempts</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-700">Available At</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-slate-200">
                                            @foreach($queueJobs as $job)
                                                <tr class="hover:bg-slate-50">
                                                    <td class="px-4 py-3 text-sm text-slate-900">{{ $job['id'] }}</td>
                                                    <td class="px-4 py-3 text-sm text-slate-900 font-mono text-xs">{{ $job['display_name'] }}</td>
                                                    <td class="px-4 py-3 text-sm text-slate-600">{{ $job['queue'] }}</td>
                                                    <td class="px-4 py-3 text-sm text-slate-600">{{ $job['attempts'] }}</td>
                                                    <td class="px-4 py-3 text-sm text-slate-600">{{ $job['available_at'] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="mb-6 rounded-lg border border-slate-200 bg-white p-4">
                            <p class="text-sm text-slate-600">No pending jobs in the queue.</p>
                        </div>
                    @endif

                    <!-- Failed Jobs -->
                    @if(count($failedJobs) > 0)
                        <div class="mb-6">
                            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-red-600">Failed Jobs (Latest 50)</h3>
                            <div class="rounded-lg border border-red-200 bg-white overflow-hidden">
                                <div class="max-h-[400px] overflow-y-auto">
                                    <div class="divide-y divide-red-100">
                                        @foreach($failedJobs as $job)
                                            <div class="p-4 hover:bg-red-50">
                                                <div class="flex items-start justify-between mb-2">
                                                    <div class="flex-1">
                                                        <div class="flex items-center gap-2 mb-1">
                                                            <span class="text-xs font-semibold text-slate-500">ID:</span>
                                                            <span class="text-sm font-mono text-slate-900">{{ $job['id'] }}</span>
                                                            <span class="text-xs text-slate-400">|</span>
                                                            <span class="text-xs font-semibold text-slate-500">UUID:</span>
                                                            <span class="text-xs font-mono text-slate-700">{{ $job['uuid'] }}</span>
                                                        </div>
                                                        <div class="text-sm font-semibold text-slate-900 font-mono mb-1">{{ $job['display_name'] }}</div>
                                                        <div class="text-xs text-slate-600 mb-1">
                                                            <span class="font-semibold">Queue:</span> {{ $job['queue'] }} | 
                                                            <span class="font-semibold">Failed At:</span> {{ $job['failed_at'] }}
                                                        </div>
                                                        <div class="mt-2 p-2 bg-red-50 rounded border border-red-200">
                                                            <div class="text-xs font-semibold text-red-800 mb-1">Exception:</div>
                                                            <div class="text-xs font-mono text-red-900">{{ $job['exception_class'] }}</div>
                                                            <div class="text-xs text-red-700 mt-1">{{ Str::limit($job['exception'], 200) }}</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 p-4">
                            <p class="text-sm text-green-800">No failed jobs. All clear!</p>
                        </div>
                    @endif
                @else
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-center">
                        <p class="text-sm text-slate-600">Click "Load Queue Jobs" to view queue and failed jobs information.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Output Display -->
        <div class="mt-8">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">{{ __('Command Output') }}</h2>
                @if(count($output) > 0)
                    <button wire:click="clearOutput" 
                            class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600 transition hover:bg-slate-100">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        {{ __('Clear') }}
                    </button>
                @endif
            </div>

            <div class="rounded-xl border border-slate-900 bg-[#0d1117] p-4 font-mono text-sm">
                @if(count($output) === 0)
                    <p class="text-slate-400">{{ __('No commands executed yet. Click a command button above to get started.') }}</p>
                @else
                    <div class="space-y-4 max-h-[600px] overflow-y-auto">
                        @foreach($output as $index => $result)
                            <div class="space-y-2">
                                <!-- Command Header -->
                                <div class="flex items-center justify-between border-b border-slate-800 pb-2">
                                    <div class="flex items-center gap-2">
                                        <span class="text-slate-400">$</span>
                                        <span class="text-emerald-400">{{ $result['command'] ?? 'Unknown command' }}</span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="text-xs text-slate-500">{{ $result['timestamp'] ?? '' }}</span>
                                        @if(isset($result['exit_code']))
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $result['exit_code'] === 0 ? 'bg-emerald-900/50 text-emerald-300' : 'bg-red-900/50 text-red-300' }}">
                                                Exit: {{ $result['exit_code'] }}
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <!-- stdout -->
                                @if(!empty($result['stdout']))
                                    <pre class="whitespace-pre-wrap text-slate-300">{{ $result['stdout'] }}</pre>
                                @endif

                                <!-- stderr -->
                                @if(!empty($result['stderr']))
                                    <pre class="whitespace-pre-wrap text-red-400">{{ $result['stderr'] }}</pre>
                                @endif

                                @if(empty($result['stdout']) && empty($result['stderr']))
                                    <p class="text-slate-500 italic">{{ __('No output') }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                @if($isExecuting)
                    <div class="mt-4 flex items-center gap-2 text-emerald-400">
                        <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-emerald-400 border-t-transparent"></span>
                        <span>{{ __('Executing command...') }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
