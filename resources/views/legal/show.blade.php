<x-public-layout :title="$title . ' - ' . $company">
    <div class="mx-auto w-full max-w-3xl px-4 py-10 sm:px-6">
        <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10">
            <div class="prose prose-slate max-w-none prose-headings:tracking-tight prose-a:text-orange-600">
                {!! $html !!}
            </div>
        </article>
        <p class="mt-6 text-center text-xs text-slate-500">
            <a href="{{ route('terms.show') }}" class="underline hover:text-orange-600">{{ __('Terms of Service') }}</a>
            &middot;
            <a href="{{ route('policy.show') }}" class="underline hover:text-orange-600">{{ __('Privacy Policy') }}</a>
            &middot;
            <a href="{{ route('documentation.roadmap') }}" class="underline hover:text-orange-600">{{ __('Public Roadmap') }}</a>
        </p>
    </div>
</x-public-layout>
