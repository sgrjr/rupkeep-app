<div>
    <div class="mx-auto space-y-8">
        <section class="relative overflow-hidden bg-gradient-to-r from-orange-500 via-orange-400 to-orange-300 p-6 text-white shadow-xl">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(255,255,255,0.25),_transparent_60%)] opacity-70"></div>
            <div class="relative flex flex-wrap items-center justify-between gap-4">
                <div class="space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-wider text-white/75">{{ __('Create Organization') }}</p>
                    <h1 class="text-3xl font-bold tracking-tight">{{ __('New Organization') }}</h1>
                </div>
                <a href="{{route('organizations.index')}}" class="inline-flex items-center gap-2 rounded-full border border-white/25 bg-white/10 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white/85 shadow-sm backdrop-blur transition hover:bg-white/20">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                    {{ __('Back') }}
                </a>
            </div>
        </section>

        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm">
                <form method="POST" action="{{ route('organizations.store') }}" class="grid grid-cols-1 gap-4">
    @csrf

    <div>
        <x-label for="name" value="{{ __('Name') }}" />
        <x-input id="name" class="block mt-1 w-full"  name="name" :value="old('name')" required autofocus autocomplete="name" placeholder="name"/>
    </div>

    <div>
        <x-label for="primary_contact" value="{{ __('Primary Contact') }}" />
        <x-input id="primary_contact" class="block mt-1 w-full"  name="primary_contact" required autocomplete="primary_contact" placeholder="primary contact"/>
    </div>

    <div>
        <x-label for="telephone" value="{{ __('Telephone #') }}" />
        <x-input id="telephone" class="block mt-1 w-full"  name="telephone" autocomplete="telephone" placeholder="telephone"/>
    </div>

    <div>
        <x-label for="fax" value="{{ __('Fax #') }}" />
        <x-input id="fax" class="block mt-1 w-full"  name="fax" autocomplete="fax" placeholder="fax"/>
    </div>

    <div>
        <x-label for="email" value="{{ __('Email') }}" />
        <x-input id="email" class="block mt-1 w-full"  name="email" autocomplete="email" placeholder="email"/>
    </div>

    <div>
        <x-label for="street" value="{{ __('street') }}" />
        <x-input id="street" class="block mt-1 w-full"  name="street" autocomplete="street" placeholder="street"/>
    </div>

    <div>
        <x-label for="city" value="{{ __('city') }}" />
        <x-input id="city" class="block mt-1 w-full"  name="city" autocomplete="city" placeholder="city"/>
    </div>

    <div>
        <x-label for="state" value="{{ __('State') }}" />
        <x-input id="state" class="block mt-1 w-full"  name="state" autocomplete="state" placeholder="state"/>
    </div>
    <div>
        <x-label for="zip" value="{{ __('Zip Code') }}" />
        <x-input id="zip" class="block mt-1 w-full"  name="zip" autocomplete="zip" placeholder="zip code"/>
    </div>

    <div>
        <x-label for="logo_url" value="{{ __('Logo Url') }}" />
        <x-input id="logo_url" class="block mt-1 w-full"  name="logo_url" autocomplete="logo_url" placeholder="logo url"/>
    </div>

    <div>
        <x-label for="website_url" value="{{ __('Website URL') }}" />
        <x-input id="website_url" class="block mt-1 w-full"  name="website_url" autocomplete="website_url" placeholder="website_url contact"/>
    </div>

    <div>
        <x-label for="owner_email" value="{{ __('Owner Email') }}" />
        <x-input id="owner_email" class="block mt-1 w-full"  name="owner_email" autocomplete="owner_email" placeholder="owner_email"/>
    </div>

                    <x-button>
                        {{ __('Create') }}
                    </x-button>
                </form>
            </div>
        </div>
    </div>
</div>