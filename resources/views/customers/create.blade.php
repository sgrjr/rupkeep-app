<x-app-layout>
    <div>
        <div class="max-w-7xl mx-auto p-2">
            <form action="{{route('customers.store')}}" method="post">
                @csrf
                
                <div class="mt-4">
                    <x-label for="name" value="{{ __('Company/Customer Name') }}" />
                    <x-input id="name" class="block mt-1 w-full"  name="name" required />
                </div>

                <div class="mt-4">
                    <x-label for="street" value="{{ __('Street Address') }}" />
                    <x-input id="street" class="block mt-1 w-full"  name="street" required />
                </div>

                <div class="mt-4">
                    <x-label for="city" value="{{ __('City') }}" />
                    <x-input id="city" class="block mt-1 w-full"  name="city" required />
                </div>

                <div class="mt-4">
                    <x-label for="state" value="{{ __('State') }}" />
                    <x-input id="state" class="block mt-1 w-full"  name="state" required />
                </div>

                <div class="mt-4">
                    <x-label for="zip" value="{{ __('Zip') }}" />
                    <x-input id="zip" class="block mt-1 w-full"  name="zip" required />
                </div>

                <div class="flex items-center justify-end mt-4">
                <x-button>
                    {{ __('+Create Customer') }}
                </x-button>
            </div>
            </form>
        </div>
    </div>
</x-app-layout>
