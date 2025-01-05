@if(request()->has('customer_id'))
<img  src="{{url('/images/organization-logo-2.avif')}}" style="max-width:300px;"/>
@else
<img  src="{{url('/images/logo.webp')}}" style="max-width:300px;"/>
@endif