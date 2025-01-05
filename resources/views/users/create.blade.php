<x-app-layout>
    <div>
        <div class="max-w-7xl mx-auto p-2">
            <form action="{{route('my.users.store')}}" method="post" autocomplete="off">
                @csrf
                
                <div class="mt-4">
                    <x-label for="name" value="{{ __(' Name') }}" />
                    <x-input id="name" class="block mt-1 w-full"  name="name" required value="{{$user->name}}" placeholder="your first and last name here" />
                </div>

                <div class="mt-4">
                    <x-label for="email" value="{{ __('Email') }}" />
                    <x-input id="email" class="block mt-1 w-full" type="email" name="email" required  value="{{$user->email}}" placeholder="email"/>
                </div>

                <div class="mt-4">
                    <x-label for="password" value="{{ __('Password') }}" />
                    <x-input id="password" class="block mt-1 w-full" type="password" name="password" required value="{{$user->password}}" />
                </div>

                <!-- Theme -->
                <div class="col-span-6 sm:col-span-4">
                    <x-label for="theme" value="{{ __('Theme/Color Scheme') }}" />
                    <select id="theme" class="mt-1 block w-full" value="{{$user->theme}}"  >
                        @foreach($themes as $theme)
                        <option value="{{$theme->value}}">{{$theme->title}}</option>
                        @endforeach
                    </select>
                    <x-input-error for="theme" class="mt-2" />
                </div>

                <!-- Role -->
                <div class="col-span-6 sm:col-span-4">
                    <x-label for="organization_role" value="{{ __('Organization Role') }}" />
                    <select id="organization_role" name="organization_role" class="mt-1 block w-full" value="{{$user->organization_role}}"  >
                        @foreach($roles as $role)
                        <option value="{{$role['id']}}">{{$role['name']}} ({{$role['short_description']}})</option>
                        @endforeach
                    </select>
                    <x-input-error for="organization_role" class="mt-2" />
                </div>


                <div class="flex items-center justify-end mt-4">
                <x-button>
                    {{ __('+Create User') }}
                </x-button>
            </div>
            </form>
        </div>
    </div>
</x-app-layout>
