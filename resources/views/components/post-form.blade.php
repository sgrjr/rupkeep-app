@props(['title'=>'submit', 'action'=>null, 'buttonClass' => ''])

<form action="{{$action}}" method="post">
   @csrf
   <input type="hidden" name="_method" value="post" />
   @if($buttonClass)
       <button class="{{$buttonClass}}" type="submit">{{$title}}</button>
   @else
       <input type="submit" value="{{$title}}"/>
   @endif
</form>
