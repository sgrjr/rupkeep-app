{{--
    Branded, self-contained error layout (TASK-065).

    Deliberately has NO dependency on Vite, the app layout, the session, or the
    database — an error page must render even when those are the thing that
    failed. All CSS is inline (via the shared partial); the only external
    reference is the static logo in public/ (not a Vite-built asset).

    App-level {code}.blade.php pages extend this for tailored copy. Codes without
    a custom page fall back to the vendor page → errors::minimal, which is also
    branded (see minimal.blade.php).

    Sections: title, code, heading, message, actions (optional override).
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', __('Something went wrong')) · {{ config('app.name', 'Casco Bay Pilot Car') }}</title>
    @include('errors.partials.brand-css')
</head>
<body>
    <main class="card" role="main">
        <img class="logo" src="{{ url('/images/logo.svg') }}" alt="{{ config('app.name', 'Casco Bay Pilot Car') }}">

        <p class="code">@yield('code', '500')</p>
        <h1 class="heading">@yield('heading', __('Something went wrong'))</h1>
        <p class="message">@yield('message', __('An unexpected error occurred. Please try again in a moment.'))</p>

        <div class="actions">
            @section('actions')
                <a class="btn btn-primary" href="{{ url('/') }}">{{ __('Back to safety') }}</a>
                <a class="btn btn-secondary" href="#" onclick="if(history.length>1){history.back();return false;}">{{ __('Go back') }}</a>
            @show
        </div>

        <p class="footer">
            {{ __('Casco Bay Pilot Car') }} — {{ __('need a hand?') }}
            <a href="{{ url('/feedback') }}">{{ __('let us know') }}</a>.
        </p>
    </main>
</body>
</html>
