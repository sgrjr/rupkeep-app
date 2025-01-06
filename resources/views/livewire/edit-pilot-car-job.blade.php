@props(['customers'=>[], 'rates'=>[]])
<div class="max-w-7xl mx-auto p-2">

@if($job->customer_id)
<div class="flex">
<a class="button w-full block text-center" href="{{route('my.customers.show', ['customer'=>$job->customer_id])}}">&larr;{{$job->customer->name}}</a>
<a class="button w-full block text-center" href="{{route('my.jobs.show', ['job'=>$job->id])}}">&larr;Job Summary: {{$job->job_no}} ({{$job->id}})</a>
</div>
@endif

    <form wire:submit="saveJob" method="post" class="pretty-form">
        @csrf

        <div class="grid grid-cols-2">
            <div class="group">
                <label class="input-label">For Customer:</label>
                <select wire:model.blur="form.customer_id">
                    @foreach($customers as $customer)
                    <option value="{{$customer->id}}">{{$customer->name}}</option>
                    @endforeach
                </select>
                @error('form.customer_id')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="group">
                <label class="input-label"><i>New Customer Name:</i></label>
                <input type="text" id="new_customer_name" wire:model.blur="form.new_customer_name" placeholder="use only if customer does not exist in list!">
                @error('form.new_customer_name')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-2">
            <div class="group">
                <label for="job_no" class="input-label">Job Number:</label>
                <input type="text" id="job_no" wire:model="form.job_no" placeholder="job number">
                @error('form.job_no')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="group">
                <label for="load_no" class="input-label">Load Number:</label>
                <input type="text" id="load_no" wire:model="form.load_no" placeholder="load number">
                @error('form.load_no')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-2">
            <div class="group">
                <label for="scheduled_pickup_at">Pickup Time</label>
                <input type="text" id="scheduled_pickup_at" wire:model="form.scheduled_pickup_at" placeholder="select date+tine">
                @error('form.scheduled_pickup_at')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="group">
                <label for="scheduled_delivery_at">Delivery Time</label>
                <input type="text" id="scheduled_delivery_at" wire:model="form.scheduled_delivery_at" placeholder="select date+time">
                @error('form.scheduled_delivery_at')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror            
            </div>
        </div>

        <div class="grid grid-cols-2">

            <div class="group">
                <label for="pickup_address">Pickup Address</label>
                <textarea type="text" id="pickup_address" wire:model="form.pickup_address" placeholder="pickup address"></textarea>
                @error('form.pickup_address')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror            
            </div>

            <div class="group">
                <label for="delivery_address">Delivery Address</label>
                <textarea type="text" id="delivery_address" wire:model="form.delivery_address" placeholder="delivery address"></textarea> 
                @error('form.delivery_address')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror           
            </div>

        </div>

        <div class="group">
            <label for="memo">Memo</label>
            <textarea type="text" id="memo" wire:model="form.memo" placeholder="memo"></textarea> 
            @error('form.memo')
                <div class="alert alert-danger">{{ $message }}</div>
            @enderror           
        </div>

        <div class="group">
            <label class="input-label">Rate Code:</label>
            <select wire:model="form.rate_code">
                @foreach($rates as $rate)
                <option value="{{$rate->value}}">{{$rate->title}}</option>
                @endforeach
            </select>
            @error('form.rate_code')
                <div class="alert alert-danger">{{ $message }}</div>
            @enderror
        </div>

        <div class="group">
            <label for="flat_rate_value" class="input-label">Flat Rate Value:</label>
            <input type="text" id="flat_rate_value" wire:model="form.flat_rate_value" placeholder="$ flate rate value = 0.00">
        </div>

        <div class="flex items-center justify-start mt-12">

            <x-action-message class="me-3" on="saved">
                {{ __('Saved.') }}
            </x-action-message>

            <x-button>
                {{ __('Save Changes') }}
            </x-button>
        </div>

    </form>
</div>

@script
<script>

flatpickr('#scheduled_pickup_at', {
    "dateFormat": "Y-m-d H:i",
    "enableTime":true,
    "defaultHour": 6
});

flatpickr('#scheduled_delivery_at', {
    //"dateFormat": "m/d/y H:i",
    "enableTime":true,
    "defaultHour": 6
});

</script>
@endscript