<div>
@if(false)<div class="impersonation-bar">{{session()->get('impersonate')}}</div>@endif
<nav x-data="{ open: false }" class="primary-navigation-menu">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="max-w-64 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <img src="{{Auth::user()->organization->logo}}" class="block h-9 w-auto" />
                    </a>
                </div>
                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex relative">
                    <x-nav-link href="{{ route('dashboard') }}" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>

                    @if(auth()->user()->can('createJob', auth()->user()->organization))
                        <x-dropdown :active="request()->routeIs('my.jobs.*')">
                            <x-slot name="trigger">
                                <p>{{ __('Jobs') }}</p>
                            </x-slot>

                            <x-slot name="content">
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

                                @foreach(\App\Models\Organization::select('id','name')->get() as $org)
                                <x-dropdown-link  href="{{ route('organizations.show', ['organization'=> $org->id]) }}">
                                    {{ __('view: ') }} {{$org->name}}
                                </x-dropdown-link >
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
                                <button class="flex text-sm rounded-full focus:outline-none focus:border-gray-300 transition">
                                    <img class="h-8 w-8 rounded-full object-cover" src="{{ Auth::user()->profile_photo_url }}" alt="{{ Auth::user()->name }}" />
                                </button>
                            @else
                                <span class="inline-flex rounded-md">
                                    <button type="button" class="inline-flex items-center px-3 py-2 text-sm leading-4 font-medium rounded-md transition ease-in-out duration-150">
                                        {{ Auth::user()->name }}

                                        <svg class="ms-2 -me-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                        </svg>
                                    </button>
                                </span>
                            @endif
                        </x-slot>

                        <x-slot name="content">
                            <div class="block px-4 py-2 text-xs text-gray-400">
                                {{ Auth::user()->organization->name }} ({{Auth::user()->organization_role}})
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

                        </x-slot>
                    </x-dropdown>
                </div>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 dark:text-gray-500 hover:text-gray-500 dark:hover:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-900 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-900 focus:text-gray-500 dark:focus:text-gray-400 transition duration-150 ease-in-out">
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
                    <div class="font-medium text-base text-gray-800 dark:text-gray-200">{{ Auth::user()->name }}</div>
                    <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
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
                <!-- Account Management -->
                <div active="{{request()->routeIs('organizations.*')}}">
                    {{ __('Organizations') }}
                </div>

                <x-responsive-nav-link href="{{ route('organizations.index') }}">
                    {{ __('Search') }}
                </x-responsive-nav-link>

                <div class="border-t border-gray-200 dark:border-gray-600"></div>

                @foreach(\App\Models\Organization::select('id','name')->get() as $org)
                <x-responsive-nav-link href="{{ route('organizations.show', ['organization'=> $org->id]) }}">
                    {{ __('view: ') }} {{$org->name}}
                </x-responsive-nav-link>
                @endforeach

                <div class="border-t border-gray-200 dark:border-gray-600"></div>

                <x-responsive-nav-link href="{{ route('organizations.create') }}">
                    {{ __('+Create New') }}
                </x-responsive-nav-link>

                <x-responsive-nav-link href="{{ route('organizations.index') }}">
                    {{ __('Search') }}
                </x-responsive-nav-link>

                <div class="border-t border-gray-200 dark:border-gray-600"></div>
                @foreach(\App\Models\Organization::select('id','name')->get() as $org)
                <x-responsive-nav-link href="{{ route('organizations.show', ['organization'=> $org->id]) }}">
                    {{ __('view: ') }} {{$org->name}}
                </x-responsive-nav-link>
                @endforeach
            </div>
            @endcan
        </div>
    </div>
</nav>
</div>