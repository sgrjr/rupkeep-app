@props(['title'=>'delete', 'action'=>null, 'redirect_to_route' => false])

<form action="{{$action}}" method="post">
   @csrf
   <input type="hidden" name="_method" value="delete" />
   <input type="hidden" name="redirect_to_route" value="{{$redirect_to_route}}" />
   <button class="btn-base btn-action-danger" type="submit" value="{{$title}}">
      <x-svg-delete/>{{$title}}
   </button>
</form>