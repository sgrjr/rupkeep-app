@props(['title'=>'delete', 'action'=>null])

<form action="{{$action}}" method="post">
   @csrf
   <input type="hidden" name="_method" value="delete" />
   <input type="submit" value="{{$title}}"/>
</form>
