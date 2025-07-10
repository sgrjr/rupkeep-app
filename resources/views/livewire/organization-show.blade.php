@props([
    'jobs_count'=>0,
    'users_count'=>0,
    'customers_count'=>0,
    'vehicles_count' => 0
    ])

<div class="max-w-5xl m-auto">
    <h1 class="text-center font-bold underline">
        @if(auth()->user()->can('update', $organization))
            <a href="{{route('organizations.edit',['organization'=>$organization->id])}}">{{$organization->name}}</a>
        @else
            {{$organization->name}}
        @endif

        @if(auth()->user()->is_super)
            <a href="{{route('jobs.index',['organization'=>$organization->id])}}"> | Jobs</a>
        @endif
        </h1>
    <img src="{{$organization->logo_url}}" class="w-64 m-auto"/>    
    <div>
        <div class="max-w-7xl mx-auto">
                <x-section-border />
            
                <div class="mt-10 sm:mt-0">
                    <div class="md:grid md:grid-cols-3 md:gap-6" >
                        <x-section-title>
                            <x-slot name="title">{{ __('Organization Contact Details') }}</x-slot>
                            <x-slot name="description">{{ __('Primary contact person, telephone, email, addresse, etc of the organization.') }}</x-slot>
                        </x-section-title>

                        <div class="mt-5 md:mt-0 md:col-span-2">
                            <div class="px-4 py-5 bg-white dark:bg-gray-800 sm:p-6 shadow {{ isset($actions) ? 'sm:rounded-tl-md sm:rounded-tr-md' : 'sm:rounded-md' }}">
                                @if(auth()->user()->can('update', $organization))
                                <a class="underline" href="{{route('organizations.edit',['organization'=>$organization->id])}}">edit</a>
                                @endif
                                <p>Owner: <b>{{$organization->owner?->name}}</b> | Primary Contact: <b>{{$organization->primary_contact}}</b> | Telephone: <b>{{$organization->telephone}}</b> | Fax: <b>{{$organization->fax? $organization->fax:'(none)'}}</b> | Contact Email: <b>{{$organization->email? $organization->email:'(none)'}}</b> | Website: <b><a class="underline" href="{{$organization->website_url}}">{{$organization->website_url? $organization->website_url:'(none)'}}</a></b></p>

                                <p>Address: <b>{{$organization->street}} {{$organization->city}}, {{$organization->state}} {{$organization->zip}}</b></p>
                            </div>
                        </div>
                    </div>
                </div>

                <x-section-border />


                <div class="mt-10 sm:mt-0">
                    <div class="md:grid md:grid-cols-3 md:gap-6" >
                        <x-section-title>
                            <x-slot name="title">Organization Users ({{count($organization->users)}})</x-slot>
                            <x-slot name="description">{{ 'All '.$organization->name.' users.' }}</x-slot>
                        </x-section-title>

                        <div class="mt-5 ml-1 mr-1 md:mt-0 md:col-span-2">
                            <div class="bg-white dark:bg-gray-800 sm:p-6 shadow {{ isset($actions) ? 'sm:rounded-tl-md sm:rounded-tr-md' : 'sm:rounded-md' }}">
                               
                                {{-- This div adds vertical space between user cards on mobile.
                                    On medium screens and up (md:), it removes vertical space and allows horizontal scrolling for table-like behavior if needed. --}}

                                <table class="hidden md:table w-full border-collapse">
                                    {{-- This table is shown only on medium screens and up (md:) --}}
                                    <thead>
                                        <tr class="bg-gray-100 border-b border-gray-300">
                                            <th class="p-3 text-left text-sm font-semibold text-gray-600">Name</th>
                                            <th class="p-3 text-left text-sm font-semibold text-gray-600">Contact</th>
                                            <th class="p-3 text-left text-sm font-semibold text-gray-600">Role</th>
                                            <th class="p-3 text-left text-sm font-semibold text-gray-600">Notifications</th>
                                            <th class="p-3 text-left text-sm font-semibold text-gray-600">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($organization->users as $user)
                                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                                            <td class="p-3 text-sm text-gray-800">{{ $user->name }}</td>
                                            <td class="p-3 text-sm text-gray-800 break-all">{{ $user->email }}</td>
                                            <td class="p-3 text-sm text-gray-800">{{ $user->organization_role }}</td>
                                            <td class="p-3 text-sm text-gray-800 break-all">{{ $user->notification_address }}</td>
                                            <td class="p-3 text-sm text-gray-800">
                                                <div class="flex flex-wrap gap-2">
                                                    @if(auth()->user()->can('createUser', $organization))
                                                    <a class="underline text-blue-600 hover:text-blue-800" href="{{ route('user.profile', ['user'=>$user->id]) }}">Profile</a>
                                                    @endif
                                                    @can('impersonate', $user)
                                                    <a href="{{route('impersonate', ['user'=>$user->id])}}" class="underline text-purple-600 hover:text-purple-800">Impersonate</a>
                                                    @endcan
                                                </div>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>

                                <div class="md:hidden">
                                    {{-- This div is shown only on mobile screens (hidden on medium screens and up) --}}
                                    @foreach($organization->users as $user)
                                    <div class="bg-white p-4 rounded-lg shadow-md border border-gray-200 mb-4">
                                        {{-- Card for each user --}}
                                        <h3 class="text-lg font-semibold text-gray-800 mb-2">{{ $user->name }}</h3>

                                        <div class="mb-2">
                                            <p class="text-sm text-gray-600">
                                                <span class="font-bold">Login:</span>
                                                <span class="break-all text-gray-800">{{ $user->email }}</span>
                                            </p>
                                        </div>

                                        <div class="mb-2">
                                            <p class="text-sm text-gray-600">
                                                <span class="font-bold">Role:</span>
                                                <span class="text-gray-800">{{ $user->organization_role }}</span>
                                            </p>
                                        </div>

                                        <div class="mb-4">
                                            <p class="text-sm text-gray-600">
                                                <span class="font-bold">Notifications:</span>
                                                <span class="break-all text-gray-800">{{ $user->notification_address }}</span>
                                            </p>
                                        </div>

                                        <div class="flex flex-wrap gap-2 text-sm">
                                            {{-- Actions --}}
                                            @if(auth()->user()->can('createUser', $organization))
                                            <a class="underline text-blue-600 hover:text-blue-800" href="{{ route('user.profile', ['user'=>$user->id]) }}">Profile</a>
                                            @endif
                                            @can('impersonate', $user)
                                            <a href="{{route('impersonate', ['user'=>$user->id])}}" class="underline text-purple-600 hover:text-purple-800">Impersonate</a>
                                            @endcan
                                        </div>
                                    </div>
                                    @endforeach
                                </div>

                            </div>
                        </div>


                        @if($deleted_users && count($deleted_users) > 0)
                        <div class="mt-5 md:mt-0 md:col-span-2 bg-red-200 border-red-200">
                            <h2>Deleted Users: </h2>
                            <div class="px-4 py-5 dark:bg-gray-800 sm:p-6 shadow {{ isset($actions) ? 'sm:rounded-tl-md sm:rounded-tr-md' : 'sm:rounded-md' }}">
                                <table class="w-full">
                                    <thead>
                                        <tr class="bg-gray-100 border">
                                            <th>restore</th>
                                            <th>destroy</th>
                                            <th>name</th>
                                            <th>email</th>
                                            <th>role</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($deleted_users as $user)
                                        <tr class="border">
                                            <td><x-post-form class="inline-block underline" action="{{route('user.restore', ['user'=> $user->id])}}" title="restore"/></td>
                                            <td><x-delete-form class="inline-block underline" action="{{route('user.delete', ['user'=> $user->id])}}" title="delete"/></td>
                                            <td>{{$user->name}}</td>
                                            <td>{{$user->email}}</td>
                                            <td>{{$user->organization_role}}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
                
                @if(auth()->user()->can('createUser', $organization))
                    <x-section-border />

                    <div class="mt-10 sm:mt-0">
                        <div class="md:grid md:grid-cols-3 md:gap-6" >
                            <x-section-title>
                                <x-slot name="title">{{ __('Create a New User') }}</x-slot>
                                <x-slot name="description">{{ __('') }}</x-slot>
                            </x-section-title>

                            <div class="mt-5 md:mt-0 md:col-span-2">
                                <div class="px-4 py-5 bg-white dark:bg-gray-800 sm:p-6 shadow {{ isset($actions) ? 'sm:rounded-tl-md sm:rounded-tr-md' : 'sm:rounded-md' }}">
                                    <div class="gap-6">

                                        <form class="w-full" 
                                            wire:submit="createUser"
                                        >
                                            @csrf
                                            <div>
                                                <x-label for="name" value="{{ __('Name') }}" />
                                                <x-input id="name" class="block mt-1 w-full"  name="name" wire:model.blur="form.name" required autofocus autocomplete="name" />
                                                @error('form.name')
                                                    <div class="alert alert-danger">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <div>
                                                <x-label for="email" value="{{ __('Email') }}" />
                                                <x-input id="email" class="block mt-1 w-full" type="email" name="email" wire:model.blur="form.email" required autofocus autocomplete="email" />
                                                @error('form.email')
                                                    <div class="alert alert-danger">{{ $message }}</div>
                                                @enderror
                                            </div>

                                            <div class="mt-4">
                                                <x-label for="role" value="{{ __('Organization Role') }}" />
                                                <select id="role" class="block mt-1 w-full" name="role"  wire:model="form.role">
                                                    @foreach($roles as $role)
                                                    <option value="{{$role['id']}}">{{$role['name']}} ({{$role['short_description']}})</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="mt-4">
                                                <x-label for="password" value="{{ __('Password') }}" />
                                                <x-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" wire:model.blur="form.password"/>
                                                @error('form.password')
                                                    <div class="alert alert-danger">{{ $message }}</div>
                                                @enderror
                                            </div>

                                            <div class="mt-4">
                                                <x-label for="password_confirmation" value="{{ __('Confirm Password') }}" />
                                                <x-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" wire:model.blur="form.password_confirmation" required autocomplete="new-password" />
                                                @error('form.password_confirmation')
                                                    <div class="alert alert-danger">{{ $message }}</div>
                                                @enderror
                                            </div>

                                            <div class="flex items-center justify-end mt-4">
                                                <x-button class="ms-4" type="submit">
                                                    {{ __('+Create User') }}
                                                </x-button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if(auth()->user()->can('resetOrganization', $organization))
                <x-section-border />

                <div class="mt-10 sm:mt-0">
                    <div class="md:grid md:grid-cols-3 md:gap-6" >
                        <x-section-title>
                            <x-slot name="title">{{ __('Reset Organization') }}</x-slot>
                            <x-slot name="description">{{ __('this is a nuclear option to empty organization jobs, customers and/or users.') }}</x-slot>
                        </x-section-title>

                        <div class="mt-5 md:mt-0 md:col-span-2">
                            <div class="px-4 py-5 bg-white dark:bg-gray-800 sm:p-6 shadow sm:rounded-tl-md sm:rounded-tr-md">
                                <div class="grid grid-cols-6 gap-6">
                                    <form class="w-full" wire:submit="deleteJobs">
                                        @csrf
                                        <button>Delete all Jobs ({{$jobs_count}})</button>
                                    </form>
                                    <form class="w-full" wire:submit="deleteCustomers">
                                        @csrf
                                        <button>Delete all Customers ({{$customers_count}})</button>
                                    </form>
                                    <form class="w-full" wire:submit="deleteUsers">
                                        @csrf
                                        <button>Delete all Users ({{$users_count}})</button>
                                    </form>
                                    <form class="w-full" wire:submit="deleteVehicles">
                                        @csrf
                                        <button>Delete all Vehicles ({{$vehicles_count}})</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                @if(auth()->user()->can('createJob', $organization))
                    <x-section-border />
                    <div class="mt-10 sm:mt-0">
                        <div class="md:grid md:grid-cols-3 md:gap-6" >
                            <x-section-title>
                                <x-slot name="title">{{ __('Upload Data from CSV') }}</x-slot>
                                <x-slot name="description">{{ __('Upload job data from csv backup.') }}</x-slot>
                            </x-section-title>

                            <div class="mt-5 md:mt-0 md:col-span-2">
                                <div class="px-4 py-5 bg-white dark:bg-gray-800 sm:p-6 shadow sm:rounded-tl-md sm:rounded-tr-md">
                                    <div class="grid grid-cols-6 gap-6">
                                    <form wire:submit="uploadFile">
                                <input type="file" wire:model="file">
                                @error('file') <span class="error">{{ $message }}</span> @enderror

                                <x-action-message class="me-3" on="uploaded">
                                    {{ __('File Uploaded.') }}
                                </x-action-message>
                                <button type="submit">Upload Data File</button>
                            </form>
                                   
                        </div>
                    </div>
                @endif

        </div>
    </div>

</div>

