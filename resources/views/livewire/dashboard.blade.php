@props(['organizations'=>[]])
<div id="dashboard" w-full flex flex-col gap-2 flex-col-reverse md:flex-row">
    <aside class="min-w-64">

    </aside>

    <main class="w-full">
        <h2 class="text-center font-bold text-2xl">{{$organization->name}}</h2>

        <div class="flex flex-wrap gap-2 p-1 justify-center md:grid md:grid-cols-3">
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
        <details class="mt-12 p-2">
            <summary class="m-6">Admin</summary>
            <button class="danger-button font-bold" wire:click="deleteJobs" wire:confirm="Are you sure you want to Delete ALL Jobs and Logs? This is not reversible!"><svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mr-2 stroke-white inline" fill="none" viewBox="0 0 24 24" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
    </svg>Nuclear: Delete ALL Jobs + LOGS</button>
</details>
        @endif

        @if($jobs && count($jobs) > 0)
        <div class="max-w-5xl m-auto p-2">
                <table class="w-full border">
                    <thead>
                        <tr class="bg-gray-200">
                            <th class="text-left">Job#</th>
                            <th class="text-left"></th>
                            <th class="text-left">Total Logs</th>
                            <th class="text-left">Logs</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($jobs as $job)
                            <tr class="border">
                                <td><a class="underline" href="{{route('jobs.show', ['job'=>$job->id])}}">view: {{$job->job_no}}</a></td>
                                <td>{{$job->customer?->name}}</td>
                                <td>{{count($job->logs)}}</td>
                               
                                <td>
                                    @foreach($job->logs as $log)
                                    <p class="border">
                                    <a class="underline" href="{{route('logs.edit', ['log'=>$log->id])}}">Log 
                                    </a> &rarr; {{$log->memo}}
                                    @endforeach
                                    </p>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </main>
</div>