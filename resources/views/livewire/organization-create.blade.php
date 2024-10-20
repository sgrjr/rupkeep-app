<div class="max-w-3xl flex w-full justify-center items-center m-auto py-12">
    <form method="POST" action="{{ route('organizations.store') }}" class="grid grid-cols-1 gap-2 w-full">
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