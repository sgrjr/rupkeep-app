<nav x-data="{ open: false }" class="primary-navigation-menu sticky top-0 z-40">
    @if(session()->has('impersonate') && session()->get('impersonate'))
        <div class="bg-slate-900 text-orange-100 text-xs tracking-wide">
            <div class="mx-auto flex max-w-7xl items-center justify-center gap-2 px-4 py-2 sm:px-6 lg:px-8">
                <span class="inline-flex items-center justify-center rounded-full bg-orange-400/90 px-2 py-0.5 text-[10px] font-semibold uppercase text-slate-900">
                    {{ __('Impersonating') }}
                </span>
                <span class="font-medium">{{ session()->get('impersonate') }}</span>
            </div>
        </div>
    @endif

    <div class="relative text-white shadow-lg shadow-orange-500/10">
        <div class="absolute inset-0 bg-gradient-to-r from-orange-600 via-orange-500 to-orange-400"></div>
        <div class="absolute inset-0 opacity-10 bg-[radial-gradient(circle_at_top,_white,_transparent_55%)]"></div>
        <div class="relative">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-2 px-4 sm:px-6 lg:px-8">
                <div class="flex flex-1 items-center gap-3 min-w-0">
                    <a href="{{ route('dashboard') }}" style="text-decoration: none;" class="flex items-center gap-2 sm:gap-3 rounded-xl border border-white/30 bg-white/20 backdrop-blur-sm px-2 sm:px-3 py-1.5 sm:py-2 shadow-md transition hover:bg-white/30 hover:shadow-lg hover:scale-[1.02] shrink-0">
                        <div class="flex h-8 w-8 sm:h-9 sm:w-9 shrink-0 items-center justify-center rounded-lg bg-orange-500 text-white shadow-md ring-2 ring-white/50">
                            @if(Auth::user()->organization?->logo)
                                <img src="{{ Auth::user()->organization->logo }}" alt="{{ Auth::user()->organization->name }} Logo" class="h-6 w-6 sm:h-7 sm:w-7 object-contain">
                            @else
                                <span class="text-xs sm:text-sm font-bold">RP</span>
                            @endif
                        </div>
                        <div class="hidden sm:block min-w-0">
                            <p class="text-[10px] uppercase tracking-widest text-white/70">Rupkeep</p>
                            <p class="truncate text-sm font-semibold text-white max-w-[140px] lg:max-w-none">{{ Auth::user()->organization->name }}</p>
                        </div>
                    </a>

                    <div class="hidden lg:flex items-center gap-2">

                        @if(auth()->user()->can('createJob', auth()->user()->organization))
                            <x-dropdown :active="request()->routeIs('my.jobs.*')" dropdownClasses="bg-white/95 text-slate-700 ring-1 ring-slate-900/10 shadow-xl" contentClasses="py-2 space-y-1">
                                <x-slot name="trigger">
                                    @php $jobsActive = request()->routeIs('my.jobs.*'); @endphp
                                    <button type="button" class="inline-flex items-center gap-1 rounded-full px-3 py-2 text-sm font-medium transition {{ $jobsActive ? 'bg-white text-orange-600 shadow-sm ring-1 ring-white/60' : 'text-white/85 hover:bg-white/15 hover:text-white' }}">
                                        <span>{{ __('Jobs') }}</span>
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                                        </svg>
                                    </button>
                                </x-slot>

                                <x-slot name="content">
                                    <x-dropdown-link href="{{ route('my.jobs.index') }}">
                                        {{ __('Job Search') }}
                                    </x-dropdown-link>
                                    <div class="border-t border-slate-100"></div>
                                    <x-dropdown-link href="{{ route('my.jobs.create') }}">
                                        {{ __('Create Job') }}
                                    </x-dropdown-link>
                                </x-slot>
                            </x-dropdown>
                        @endif

                        @if(auth()->user()->can('createCustomer', auth()->user()->organization))
                            <x-dropdown :active="request()->routeIs('my.customers.*')" dropdownClasses="bg-white/95 text-slate-700 ring-1 ring-slate-900/10 shadow-xl" contentClasses="py-2 space-y-1">
                                <x-slot name="trigger">
                                    @php $customersActive = request()->routeIs('my.customers.*'); @endphp
                                    <button type="button" class="inline-flex items-center gap-1 rounded-full px-3 py-2 text-sm font-medium transition {{ $customersActive ? 'bg-white text-orange-600 shadow-sm ring-1 ring-white/60' : 'text-white/85 hover:bg-white/15 hover:text-white' }}">
                                        <span>{{ __('Customers') }}</span>
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                                        </svg>
                                    </button>
                                </x-slot>

                                <x-slot name="content">
                                    <x-dropdown-link href="{{ route('my.customers.index') }}">
                                        {{ __('Customer Directory') }}
                                    </x-dropdown-link>
                                    <div class="border-t border-slate-100"></div>
                                    <x-dropdown-link href="{{ route('my.customers.create') }}">
                                        {{ __('Add Customer') }}
                                    </x-dropdown-link>
                                </x-slot>
                            </x-dropdown>
                        @endif

                        @if(auth()->user()->can('createUser', auth()->user()->organization))
                            <x-dropdown :active="request()->routeIs('my.users.*')" dropdownClasses="bg-white/95 text-slate-700 ring-1 ring-slate-900/10 shadow-xl" contentClasses="py-2 space-y-1">
                                <x-slot name="trigger">
                                    @php $usersActive = request()->routeIs('my.users.*'); @endphp
                                    <button type="button" class="inline-flex items-center gap-1 rounded-full px-3 py-2 text-sm font-medium transition {{ $usersActive ? 'bg-white text-orange-600 shadow-sm ring-1 ring-white/60' : 'text-white/85 hover:bg-white/15 hover:text-white' }}">
                                        <span>{{ __('Team') }}</span>
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                                        </svg>
                                    </button>
                                </x-slot>

                                <x-slot name="content">
                                    <x-dropdown-link href="{{ route('my.users.index') }}">
                                        {{ __('Team Directory') }}
                                    </x-dropdown-link>
                                    <div class="border-t border-slate-100"></div>
                                    <x-dropdown-link href="{{ route('my.users.create') }}">
                                        {{ __('Invite Member') }}
                                    </x-dropdown-link>
                                </x-slot>
                            </x-dropdown>
                        @endif

                        @if(auth()->user()->can('createVehicle', auth()->user()->organization))
                            <x-dropdown :active="request()->routeIs('my.vehicles.*')" dropdownClasses="bg-white/95 text-slate-700 ring-1 ring-slate-900/10 shadow-xl" contentClasses="py-2 space-y-1">
                                <x-slot name="trigger">
                                    @php $vehiclesActive = request()->routeIs('my.vehicles.*'); @endphp
                                    <button type="button" class="inline-flex items-center gap-1 rounded-full px-3 py-2 text-sm font-medium transition {{ $vehiclesActive ? 'bg-white text-orange-600 shadow-sm ring-1 ring-white/60' : 'text-white/85 hover:bg-white/15 hover:text-white' }}">
                                        <span>{{ __('Vehicles') }}</span>
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                                        </svg>
                                    </button>
                                </x-slot>

                                <x-slot name="content">
                                    <x-dropdown-link href="{{ route('my.vehicles.index') }}">
                                        {{ __('Fleet Overview') }}
                                    </x-dropdown-link>
                                    <div class="border-t border-slate-100"></div>
                                    <x-dropdown-link href="{{ route('my.vehicles.create') }}">
                                        {{ __('Add Vehicle') }}
                                    </x-dropdown-link>
                                </x-slot>
                            </x-dropdown>
                        @endif

                        @if(auth()->user()->can('createJob', auth()->user()->organization))
                            <x-nav-link href="{{ route('my.pricing.index') }}" :active="request()->routeIs('my.pricing.*')">
                                {{ __('Pricing') }}
                            </x-nav-link>
                        @endif

                @can('viewAny', new \App\Models\Organization)
                            <x-dropdown :active="request()->routeIs('organizations.*')" dropdownClasses="bg-white/95 text-slate-700 ring-1 ring-slate-900/10 shadow-xl max-h-96 overflow-y-auto" contentClasses="py-2 space-y-1">
                                <x-slot name="trigger">
                                    @php $organizationsActive = request()->routeIs('organizations.*'); @endphp
                                    <button type="button" class="inline-flex items-center gap-1 rounded-full px-3 py-2 text-sm font-medium transition {{ $organizationsActive ? 'bg-white text-orange-600 shadow-sm ring-1 ring-white/60' : 'text-white/85 hover:bg-white/15 hover:text-white' }}">
                                        <span>{{ __('Organizations') }}</span>
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                                        </svg>
                                    </button>
                                </x-slot>

                                <x-slot name="content">
                                    <x-dropdown-link href="{{ route('organizations.index') }}">
                                        {{ __('Browse Organizations') }}
                                    </x-dropdown-link>
                                    <div class="border-t border-slate-100"></div>
                                    <x-dropdown-link href="{{ route('organizations.create') }}">
                                        {{ __('Create Organization') }}
                                    </x-dropdown-link>
                                    <div class="border-t border-slate-100"></div>
                                    @foreach($organizations as $org)
                                        <x-dropdown-link href="{{ route('organizations.show', ['organization'=> $org->id]) }}">
                                            {{ $org->name }}
                                        </x-dropdown-link>
                                    @endforeach
                                </x-slot>
                            </x-dropdown>
                        @endcan
                    </div>
                </div>

                <div class="hidden lg:flex items-center gap-3 shrink-0">
                    <x-dropdown align="right" width="48" class="no-border" dropdownClasses="bg-white/95 text-slate-700 ring-1 ring-slate-900/10 shadow-xl" contentClasses="py-2 space-y-1">
                        <x-slot name="trigger">
                            <button class="flex items-center gap-2 rounded-xl border border-white/30 bg-white/20 backdrop-blur-sm px-2 py-1.5 shadow-md transition hover:bg-white/30 hover:shadow-lg hover:scale-[1.02] focus:outline-none shrink-0">
                                <img class="h-8 w-8 shrink-0 rounded-lg object-cover ring-2 ring-white/50" src="{{ Auth::user()->profile_photo_url }}" alt="{{ Auth::user()->name }}" />
                                <span class="hidden xl:inline-flex flex-col text-left leading-tight min-w-0">
                                    <span class="text-[10px] text-white/70 truncate">{{ Auth::user()->role_label }}</span>
                                    <span class="text-xs font-semibold text-white truncate max-w-[120px]">{{ Auth::user()->name }}</span>
                                </span>
                                <svg class="h-4 w-4 shrink-0 text-white/80" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <div class="px-4 pb-2 pt-1 text-xs font-semibold uppercase tracking-widest text-orange-500">
                                {{ Auth::user()->organization->name }}
                            </div>

                            <x-dropdown-link href="{{ route('my.profile') }}">
                                {{ __('Profile') }}
                            </x-dropdown-link>

                            @if (Laravel\Jetstream\Jetstream::hasApiFeatures())
                                <x-dropdown-link href="{{ route('api-tokens.index') }}">
                                    {{ __('API Tokens') }}
                                </x-dropdown-link>
                            @endif

                            <x-dropdown-link href="{{ route('feedback.index') }}">
                                {{ __('Send Feedback') }}
                            </x-dropdown-link>

                            <form method="POST" action="{{ route('logout') }}" x-data>
                                @csrf
                                <x-dropdown-link href="{{ route('logout') }}"
                                                 @click.prevent="$root.submit();">
                                    {{ __('Log Out') }}
                                </x-dropdown-link>
                            </form>

                            @if (!empty($history))
                                <div class="border-t border-slate-100 pt-3">
                                    <div class="px-4 pb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">
                                        {{ __('Recently Viewed') }}
                                    </div>
                                    <div class="space-y-1">
                                        @foreach ($history as $item)
                                            <x-responsive-nav-link href="{{ $item['url'] }}">
                                                {{ $item['title'] }}
                                            </x-responsive-nav-link>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </x-slot>
                    </x-dropdown>
                </div>

                <div class="flex items-center lg:hidden shrink-0">
                    <button @click="open = ! open" type="button" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border-2 border-white/40 bg-white/20 backdrop-blur-sm shadow-md transition-all hover:bg-white/30 hover:border-white/60 hover:shadow-lg active:scale-95 focus:outline-none focus:ring-2 focus:ring-white/50 focus:ring-offset-1">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="#f9b104">
                            <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                            <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div :class="{'block': open, 'hidden': ! open}" class="hidden border-t border-slate-200 bg-white/95 backdrop-blur lg:hidden">
        <div class="mx-auto max-w-7xl px-4 pb-6 sm:px-6 lg:px-8">
            <div class="pt-4 pb-3 space-y-1">
                <x-responsive-nav-link href="{{ route('dashboard') }}" :active="request()->routeIs('dashboard')">
                    {{ __('Dashboard') }}
                </x-responsive-nav-link>

                @if(auth()->user()->can('createJob', auth()->user()->organization))
                    <x-responsive-nav-link href="{{ route('my.pricing.index') }}" :active="request()->routeIs('my.pricing.*')">
                        {{ __('Pricing') }}
                    </x-responsive-nav-link>
                @endif

                @if(auth()->user()->can('createJob', auth()->user()->organization))
                    <x-responsive-nav-link href="{{ route('my.jobs.index') }}" :active="request()->routeIs('my.jobs.*')">
                        {{ __('Jobs') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link href="{{ route('my.jobs.create') }}">
                        {{ __('Create Job') }}
                    </x-responsive-nav-link>
                @endif

                @if(auth()->user()->can('createCustomer', auth()->user()->organization))
                    <x-responsive-nav-link href="{{ route('my.customers.index') }}" :active="request()->routeIs('my.customers.*')">
                        {{ __('Customers') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link href="{{ route('my.customers.create') }}">
                        {{ __('Add Customer') }}
                    </x-responsive-nav-link>
                @endif

                @if(auth()->user()->can('createUser', auth()->user()->organization))
                    <x-responsive-nav-link href="{{ route('my.users.index') }}" :active="request()->routeIs('my.users.*')">
                        {{ __('Team') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link href="{{ route('my.users.create') }}">
                        {{ __('Invite Member') }}
                    </x-responsive-nav-link>
                @endif

                @if(auth()->user()->can('createVehicle', auth()->user()->organization))
                    <x-responsive-nav-link href="{{ route('my.vehicles.index') }}" :active="request()->routeIs('my.vehicles.*')">
                        {{ __('Vehicles') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link href="{{ route('my.vehicles.create') }}">
                        {{ __('Add Vehicle') }}
                    </x-responsive-nav-link>
                @endif

                @if(auth()->user()->can('createOrganization', auth()->user()->organization))
                    <x-responsive-nav-link href="{{ route('organizations.index') }}" :active="request()->routeIs('organizations.*')">
                        {{ __('Organizations') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link href="{{ route('organizations.create') }}">
                        {{ __('Create Organization') }}
                    </x-responsive-nav-link>
                @endif
            </div>

            <div class="border-t border-slate-200 pt-4">
                <div class="flex items-center gap-3 px-4">
                    <img class="h-10 w-10 rounded-full object-cover ring-2 ring-orange-200" src="{{ Auth::user()->profile_photo_url }}" alt="{{ Auth::user()->name }}" />
                    <div>
                        <div class="text-base font-semibold text-slate-900">{{ Auth::user()->name }}</div>
                        <div class="text-sm text-slate-500">{{ Auth::user()->email }}</div>
                    </div>
                </div>

                <div class="mt-3 space-y-1">
                    <x-responsive-nav-link href="{{ route('my.profile') }}" :active="request()->routeIs('my.profile')">
                        {{ __('Profile') }}
                    </x-responsive-nav-link>

                    @if (Laravel\Jetstream\Jetstream::hasApiFeatures())
                        <x-responsive-nav-link href="{{ route('api-tokens.index') }}" :active="request()->routeIs('api-tokens.index')">
                            {{ __('API Tokens') }}
                        </x-responsive-nav-link>
                    @endif

                    <x-responsive-nav-link href="{{ route('feedback.index') }}" :active="request()->routeIs('feedback.index')">
                        {{ __('Send Feedback') }}
                    </x-responsive-nav-link>

                    <form method="POST" action="{{ route('logout') }}" x-data>
                        @csrf
                        <x-responsive-nav-link href="{{ route('logout') }}"
                                                @click.prevent="$root.submit();">
                            {{ __('Log Out') }}
                        </x-responsive-nav-link>
                    </form>
                </div>

                @can('viewAny', new \App\Models\Organization)
                    <div class="mt-4 space-y-1 rounded-lg border border-slate-100 bg-white p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Organizations') }}</p>
                        <x-responsive-nav-link href="{{ route('organizations.index') }}">
                            {{ __('Browse Organizations') }}
                        </x-responsive-nav-link>
                        <x-responsive-nav-link href="{{ route('organizations.create') }}">
                            {{ __('Create Organization') }}
                        </x-responsive-nav-link>
                        <div class="border-t border-slate-100"></div>
                        @foreach($organizations as $org)
                            <x-responsive-nav-link href="{{ route('organizations.show', ['organization'=> $org->id]) }}">
                                {{ $org->name }}
                            </x-responsive-nav-link>
                        @endforeach
                    </div>
                @endcan
            </div>

            @if (!empty($history))
                <div class="mt-4 space-y-1 rounded-lg border border-slate-100 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Recently Viewed') }}</p>
                    @foreach ($history as $item)
                        <x-responsive-nav-link href="{{ $item['url'] }}">
                            {{ $item['title'] }}
                        </x-responsive-nav-link>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</nav>