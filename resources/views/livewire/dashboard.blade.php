@props(['organizations'=>[]])
<div id="dashboard" w-full flex flex-col gap-2 flex-col-reverse md:flex-row">
    <aside class="min-w-64">

    </aside>

    <main class="w-full">
        <h2 class="text-center font-bold text-2xl">{{$organization->name}}</h2>

        <div class="flex flex-wrap gap-2 justify-center md:grid md:grid-cols-3">
            @foreach($cards as $card)
            <div class="card">
                <div class="p-2">
                    <h2>{{$card->title}} ({{$card->count}})</h2>

                    @if($card->title === 'Jobs')
                    
                        <div class="md:grid md:grid-cols-2 gap-2">
                            <form wire:submit="uploadFile">
                                <input type="file" wire:model="file">
                                @error('file') <span class="error">{{ $message }}</span> @enderror

                                <x-action-message class="me-3" on="uploaded">
                                    {{ __('File Uploaded.') }}
                                </x-action-message>
                                <button type="submit">Upload Data File</button>
                            </form>
                        </div>

                    @elseif($card->title === 'Organizations')

                    <div class="md:grid md:grid-cols-2 gap-2">
                    @foreach($organizations as $organization)
                        <div>
                            <h2 class="text-center"><a href="{{route('organizations.show',['organization'=> $organization->id])}}">{{$organization->name}}</a></h2>

                            <div class="border flex flex-col text-left">
                                @foreach($organization->users as $user)
                                <h3 class="text-center">
                                    <a class="button-like" href="{{route('user.profile',['user'=> $user->id])}}">p</a>
                                    <a class="button-like" href="{{route('impersonate',['user'=> $user->id])}}">i</a>
                                    {{substr($user->name,0,9)}}
                                </h3>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                    </div>

                    @endif
                </div>
                <div class="card-actions">

                    @foreach($card->links as $link)
                        <a class="button" href="{{$link['url']}}">{{$link['title']}}</a>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
        @if(auth()->user()->is_super)
        <div>
            <button wire:click="deleteJobs">Nuclear: Delete ALL Jobs + LOGS</button>
        </div>
        @endif
    </main>
</div>