@php
    use Illuminate\Support\Str;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide">{{ __('Super Admin') }}</p>
                <h1 class="text-xl font-semibold">{{ __('Feedback Submissions') }}</h1>
                <p class="text-xs">{{ __('View all user feedback submissions across all organizations.') }}</p>
            </div>
            <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 rounded-full border border-orange-200 bg-orange-50 px-3 py-2 text-xs font-semibold text-orange-600 transition hover:bg-orange-500 hover:text-white">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
                </svg>
                {{ __('Back to Dashboard') }}
            </a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <!-- Stats -->
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Total Submissions') }}</p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($totalFeedback) }}</p>
            </div>
            <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-blue-600">{{ __('Info Feedback') }}</p>
                <p class="mt-2 text-3xl font-bold text-blue-700">{{ number_format($infoFeedback) }}</p>
            </div>
            <div class="rounded-2xl border border-red-200 bg-red-50 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-red-600">{{ __('Error Feedback') }}</p>
                <p class="mt-2 text-3xl font-bold text-red-700">{{ number_format($errorFeedback) }}</p>
            </div>
        </div>

        <!-- Feedback List -->
        <div class="space-y-3">
            @forelse($feedback as $item)
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:shadow-md">
                    <div class="flex flex-col gap-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2 mb-3">
                                    <span class="text-sm font-semibold text-slate-900">
                                        {{ $item->user?->name ?? __('Anonymous User') }}
                                    </span>
                                    @if($item->user)
                                        <span class="text-xs text-slate-400">({{ $item->user->email }})</span>
                                    @endif
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2 py-1 text-[10px] font-semibold uppercase tracking-wide',
                                        'bg-red-100 text-red-700 border border-red-200' => $item->severity === 'error',
                                        'bg-blue-100 text-blue-700 border border-blue-200' => $item->severity === 'info',
                                    ])>
                                        {{ ucfirst($item->severity) }}
                                    </span>
                                    <span class="text-xs text-slate-400">{{ $item->created_at->diffForHumans() }}</span>
                                </div>
                                
                                @if(isset($item->context['feedback']))
                                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                                        <p class="text-sm text-slate-700 whitespace-pre-wrap">{{ $item->context['feedback'] }}</p>
                                    </div>
                                @else
                                    <p class="text-sm text-slate-400 italic">{{ __('No feedback text provided.') }}</p>
                                @endif
                            </div>
                        </div>
                        
                        <div class="flex flex-wrap items-center gap-4 text-xs text-slate-500 border-t border-slate-100 pt-3">
                            @if($item->user?->organization)
                                <span>{{ __('Organization') }}: {{ $item->user->organization->name }}</span>
                            @endif
                            @if($item->url)
                                <span class="truncate max-w-xs">{{ __('URL') }}: <a href="{{ $item->url }}" target="_blank" class="text-orange-600 hover:underline">{{ Str::limit($item->url, 50) }}</a></span>
                            @endif
                            @if($item->ip)
                                <span>{{ __('IP') }}: {{ $item->ip }}</span>
                            @endif
                            <span>{{ __('Submitted') }}: {{ $item->created_at->format('M j, Y g:ia') }}</span>
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center">
                    <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/>
                    </svg>
                    <p class="mt-4 text-sm font-semibold text-slate-900">{{ __('No feedback submissions yet.') }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ __('Feedback from users will appear here.') }}</p>
                </div>
            @endforelse
        </div>

        <!-- Pagination -->
        <div class="mt-6">
            {{ $feedback->links() }}
        </div>
    </div>
</x-app-layout>
