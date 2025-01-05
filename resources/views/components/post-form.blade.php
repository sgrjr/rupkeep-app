@props(['title'=>'submit', 'action'=>null])

<form action="{{$action}}" method="post">
   @csrf
   <input type="hidden" name="_method" value="post" />
   <input type="submit" value="{{$title}}"/>
</form>
