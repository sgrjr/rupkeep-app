@if(true || request()->has('customer_id'))
<img alt="{{ config('app.name') }}" src="{{url('/images/organization-logo-2.avif')}}" style="max-width:300px;"/>
@else
<img alt="{{ config('app.name') }}" src="{{url('/images/logo.webp')}}" style="max-width:300px;"/>
@endif