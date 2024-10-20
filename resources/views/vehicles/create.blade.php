<x-app-layout>
    <div>
        <div class="max-w-7xl mx-auto p-2">
            <form action="{{route('my.vehicles.store')}}" method="post">
                @csrf
                
                <div class="mt-4">
                    <x-label for="name" value="{{ __('Name') }}" />
                    <x-input id="name" class="block mt-1 w-full"  name="name" required />
                </div>

                <div class="mt-4">
                    <x-label for="odometer" value="{{ __('Odometer') }}" />
                    <x-input id="odometer" class="block mt-1 w-full"  name="odometer" required />
                </div>

                <div class="flex items-center justify-end mt-4">
                <x-button>
                    {{ __('+Create Vehicle') }}
                </x-button>
            </div>
            </form>
        </div>
    </div>
</x-app-layout>