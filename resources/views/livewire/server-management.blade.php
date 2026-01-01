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
