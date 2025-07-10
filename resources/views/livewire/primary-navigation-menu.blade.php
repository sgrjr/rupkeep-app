<nav x-data="{ open: false }" class="primary-navigation-menu">
    <!-- Impersonation Bar (if session()->get('impersonate') is true) -->
    @if(session()->has('impersonate') && session()->get('impersonate'))
        {{-- The 'impersonation-bar' class from app.css handles background and text --}}
        <div class="impersonation-bar p-2 text-center text-sm font-semibold">
            {{ __('You are currently impersonating: ') }} {{ session()->get('impersonate') }}
        </div>
    @endif

    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="max-w-64 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <img src="{{ Auth::user()->organization->logo }}" class="block h-9 w-auto" alt="{{ Auth::user()->organization->name }} Logo" />
                    </a>
                </div>
                <!-- Navigation Links (Desktop) -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex relative">
                    {{-- x-nav-link component is assumed to apply 'nav-link' class internally,
                         and its active state is handled by '.nav-link.active-nav-link' in app.css.
                         No explicit text-white/hover classes needed here if nav-link handles it. --}}
                    <x-nav-link href="{{ route('dashboard') }}" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>

                    @if(auth()->user()->can('createJob', auth()->user()->organization))
                        {{-- x-dropdown is assumed to apply 'menu-drop-down' to its trigger internally --}}
                        <x-dropdown :active="request()->routeIs('my.jobs.*')">
                            <x-slot name="trigger">
                                {{-- The <p> tag here will inherit color from .primary-navigation-menu p in app.css --}}
                                <p>{{ __('Jobs') }}</p>
                            </x-slot>

                            <x-slot name="content">
                                {{-- x-dropdown-link is assumed to apply 'dropdown-link' class internally --}}
                                <x-dropdown-link href="{{ route('my.jobs.index') }}">
                                    {{ __('Jobs Search') }}
                                </x-dropdown-link>
                                <div class="border-t border-gray-200 dark:border-gray-600"></div>
                                <x-dropdown-link href="{{ route('my.jobs.create') }}">
                                    {{ __('+Create New') }}
                                </x-dropdown-link>
                            </x-slot>
                        </x-dropdown>
                    @endif

                    @if(auth()->user()->can('createCustomer', auth()->user()->organization))
                        <x-dropdown :active="request()->routeIs('my.customers.*')">
                            <x-slot name="trigger">
                                <p>{{ __('Customers') }}</p>
                            </x-slot>
                            <x-slot name="content">
                                <x-dropdown-link href="{{ route('my.customers.index') }}">
                                    {{ __('Customers Search') }}
                                </x-dropdown-link>
                                <div class="border-t border-gray-200 dark:border-gray-600"></div>
                                <x-dropdown-link href="{{ route('my.customers.create') }}">
                                    {{ __('+Create New') }}
                                </x-dropdown-link>
                            </x-slot>
                        </x-dropdown>
                    @endif

                    @if(auth()->user()->can('createUser', auth()->user()->organization))
                        <x-dropdown :active="request()->routeIs('my.users.*')">
                            <x-slot name="trigger">
                                <p>{{ __('Users') }}</p>
                            </x-slot>
                            <x-slot name="content">
                                <x-dropdown-link href="{{ route('my.users.index') }}">
                                    {{ __('Users Search') }}
                                </x-dropdown-link>
                                <div class="border-t border-gray-200 dark:border-gray-600"></div>
                                <x-dropdown-link href="{{ route('my.users.create') }}">
                                    {{ __('+Create New') }}
                                </x-dropdown-link>
                            </x-slot>
                        </x-dropdown>
                    @endif

                    @if(auth()->user()->can('createVehicle', auth()->user()->organization))
                        <x-dropdown :active="request()->routeIs('my.vehicles.*')">
                            <x-slot name="trigger">
                                <p>{{ __('Vehicles') }}</p>
                            </x-slot>
                            <x-slot name="content">
                                <x-dropdown-link href="{{ route('my.vehicles.index') }}">
                                    {{ __('Vehicles Search') }}
                                </x-dropdown-link>
                                <div class="border-t border-gray-200 dark:border-gray-600"></div>
                                <x-dropdown-link href="{{ route('my.vehicles.create') }}">
                                    {{ __('+Create New') }}
                                </x-dropdown-link>
                            </x-slot>
                        </x-dropdown>
                    @endif

                    @can('viewAny', new \App\Models\Organization)
                        <x-dropdown :active="request()->routeIs('organizations.*')">
                            <x-slot name="trigger">
                                <p>{{ __('Organizations') }}</p>
                            </x-slot>
                            <x-slot name="content">
                                <x-dropdown-link href="{{ route('organizations.index') }}">
                                    {{ __('Search') }}
                                </x-dropdown-link>
                                <div class="border-t border-gray-200 dark:border-gray-600"></div>
                                <x-dropdown-link href="{{ route('organizations.create') }}">
                                    {{ __('+Create New') }}
                                </x-dropdown-link>
                                <div class="border-t border-gray-200 dark:border-gray-600"></div>
                                {{-- Use the $organizations property passed from the Livewire component --}}
                                @foreach($organizations as $org)
                                    <x-dropdown-link href="{{ route('organizations.show', ['organization'=> $org->id]) }}">
                                        {{ __('view: ') }} {{ $org->name }}
                                    </x-dropdown-link>
                                @endforeach
                            </x-slot>
                        </x-dropdown>
                    @endcan
                </div>
            </div>

            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <!-- Settings Dropdown -->
                <div class="ms-3 relative h-full">
                    <x-dropdown align="right" width="48" class="no-border">
                        <x-slot name="trigger">
                            @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
                                <button class="flex text-sm rounded-full focus:outline-none focus:border-indigo-300 transition">
                                    <img class="h-8 w-8 rounded-full object-cover" src="{{ Auth::user()->profile_photo_url }}" alt="{{ Auth::user()->name }}" />
                                </button>
                            @else
                                <span class="inline-flex rounded-md">
                                    {{-- This button will pick up the global 'button' styles --}}
                                    <button type="button" class="button inline-flex items-center px-3 py-2 text-sm leading-4 font-medium transition ease-in-out duration-150">
                                        {{ Auth::user()->name }}
                                        <svg class="ms-2 -me-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                        </svg>
                                    </button>
                                </span>
                            @endif
                        </x-slot>

                        <x-slot name="content">
                            {{-- Dropdown content background and text are typically handled by Jetstream's x-dropdown component
                                 or by your .menu-drop-down-content class from app.css --}}
                            <div class="block px-4 py-2 text-xs text-gray-400">
                                {{ Auth::user()->organization->name }} ({{ Auth::user()->organization_role }})
                            </div>

                            <!-- Account Management -->
                            <x-dropdown-link href="{{ route('my.profile') }}">
                                {{ __('Profile') }}
                            </x-dropdown-link>

                            @if (Laravel\Jetstream\Jetstream::hasApiFeatures())
                                <x-dropdown-link href="{{ route('api-tokens.index') }}">
                                    {{ __('API Tokens') }}
                                </x-dropdown-link>
                            @endif

                            <div class="border-t border-gray-200 dark:border-gray-600"></div>

                            <!-- Authentication -->
                            <form method="POST" action="{{ route('logout') }}" x-data>
                                @csrf
                                <x-dropdown-link href="{{ route('logout') }}"
                                                 @click.prevent="$root.submit();">
                                    {{ __('Log Out') }}
                                </x-dropdown-link>
                            </form>

                             @if (!empty($history))
                                <div class="pt-4 pb-1 border-t border-gray-200 dark:border-gray-600">
                                    <div class="px-4">
                                        {{-- This text color will be handled by the default/dark theme classes from app.css --}}
                                        <div class="font-medium text-base text-gray-800 dark:text-gray-200">{{ __('Previously Visited') }}</div>
                                    </div>

                                    <div class="mt-3 space-y-1">
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
            </div>

            <!-- Hamburger (Mobile Menu Toggle) -->
            <div class="-me-2 flex items-center sm:hidden">
                {{-- This button will pick up the global 'button' styles --}}
                <button @click="open = ! open" class="button inline-flex items-center justify-center p-2 rounded-md focus:outline-none transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link href="{{ route('dashboard') }}" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>

            @if(auth()->user()->can('createJob', auth()->user()->organization))
                <x-responsive-nav-link href="{{ route('my.jobs.index') }}" :active="request()->routeIs('my.jobs.*')">
                    {{ __('Jobs') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('my.jobs.create') }}">
                    {{ __('+Create New Job') }}
                </x-responsive-nav-link>
            @endif
            @if(auth()->user()->can('createCustomer', auth()->user()->organization))
                <x-responsive-nav-link href="{{ route('my.customers.index') }}" :active="request()->routeIs('my.customers.*')">
                    {{ __('Customers') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('my.customers.create') }}">
                    {{ __('+Create New Customer') }}
                </x-responsive-nav-link>
            @endif
            @if(auth()->user()->can('createUser', auth()->user()->organization))
                <x-responsive-nav-link href="{{ route('my.users.index') }}" :active="request()->routeIs('my.users.*')">
                    {{ __('Users') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('my.users.create') }}">
                    {{ __('+Create New User') }}
                </x-responsive-nav-link>
            @endif
            @if(auth()->user()->can('createVehicle', auth()->user()->organization))
                <x-responsive-nav-link href="{{ route('my.vehicles.index') }}" :active="request()->routeIs('my.vehicles.*')">
                    {{ __('Vehicles') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('my.vehicles.create') }}">
                    {{ __('+Create New Vehicle') }}
                </x-responsive-nav-link>
            @endif
            @if(auth()->user()->can('createOrganization', auth()->user()->organization))
                <x-responsive-nav-link href="{{ route('my.organizations.index') }}" :active="request()->routeIs('my.organizations.*')">
                    {{ __('Organizations') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('my.organizations.create') }}">
                    {{ __('+Create New Organization') }}
                </x-responsive-nav-link>
            @endif
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200 dark:border-gray-600">
            <div class="flex items-center px-4">
                @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
                    <div class="shrink-0 me-3">
                        <img class="h-10 w-10 rounded-full object-cover" src="{{ Auth::user()->profile_photo_url }}" alt="{{ Auth::user()->name }}" />
                    </div>
                @endif

                <div>
                    {{-- These text colors will be handled by the default/dark theme classes from app.css --}}
                    <div class="font-medium text-base text-gray-800 dark:text-gray-200">{{ Auth::user()->name }}</div>
                    <div class="font-medium text-sm text-gray-500 dark:text-gray-400">{{ Auth::user()->email }}</div>
                </div>
            </div>

            <div class="mt-3 space-y-1">
                <!-- Account Management -->
                <x-responsive-nav-link href="{{ route('my.profile') }}" :active="request()->routeIs('my.profile')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                @if (Laravel\Jetstream\Jetstream::hasApiFeatures())
                    <x-responsive-nav-link href="{{ route('api-tokens.index') }}" :active="request()->routeIs('api-tokens.index')">
                        {{ __('API Tokens') }}
                    </x-responsive-nav-link>
                @endif

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}" x-data>
                    @csrf
                    <x-responsive-nav-link href="{{ route('logout') }}"
                                           @click.prevent="$root.submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>

                
            </div>

            @can('viewAny', new \App\Models\Organization)
                <div class="mt-3 space-y-1" >
                    {{-- This text color will be handled by the default/dark theme classes from app.css --}}
                    <div class="block px-4 py-2 text-xs text-gray-400">
                        {{ __('Organizations') }}
                    </div>

                    <x-responsive-nav-link href="{{ route('organizations.index') }}">
                        {{ __('Search') }}
                    </x-responsive-nav-link>

                    <div class="border-t border-gray-200 dark:border-gray-600"></div>

                    <x-responsive-nav-link href="{{ route('organizations.create') }}">
                        {{ __('+Create New') }}
                    </x-responsive-nav-link>

                    <div class="border-t border-gray-200 dark:border-gray-600"></div>

                    {{-- Use the $organizations property passed from the Livewire component --}}
                    @foreach($organizations as $org)
                        <x-responsive-nav-link href="{{ route('organizations.show', ['organization'=> $org->id]) }}">
                            {{ __('view: ') }} {{ $org->name }}
                        </x-responsive-nav-link>
                    @endforeach
                </div>
            @endcan
        </div>

        @if (!empty($history))
            <div class="pt-4 pb-1 border-t border-gray-200 dark:border-gray-600">
                <div class="px-4">
                    {{-- This text color will be handled by the default/dark theme classes from app.css --}}
                    <div class="font-medium text-base text-gray-800 dark:text-gray-200">{{ __('Previously Visited') }}</div>
                </div>

                <div class="mt-3 space-y-1">
                    @foreach ($history as $item)
                        <x-responsive-nav-link href="{{ $item['url'] }}">
                            {{ $item['title'] }}
                        </x-responsive-nav-link>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</nav>