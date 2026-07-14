{{--
    Branded fallback for any HTTP error code without a custom app-level page
    (TASK-065). Laravel's built-in error pages do @extends('errors::minimal'),
    which resolves here first. Standalone (not @extends('errors.layout')) because
    vendor pages only provide a single `message` string and child sections win
    over parents — so remapping them cleanly requires rendering them here.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', __('Error')) · {{ config('app.name', 'Casco Bay Pilot Car') }}</title>
    @include('errors.partials.brand-css')
</head>
<body>
    <main class="card" role="main">
        <img class="logo" src="{{ url('/images/logo.svg') }}" alt="{{ config('app.name', 'Casco Bay Pilot Car') }}">

        <p class="code">@yield('code')</p>
        <h1 class="heading">@yield('message', __('Something went wrong'))</h1>
        <p class="message">{{ __('Sorry, an error occurred while handling your request.') }}</p>

        <div class="actions">
            <a class="btn btn-primary" href="{{ url('/') }}">{{ __('Back to safety') }}</a>
            <a class="btn btn-secondary" href="#" onclick="if(history.length>1){history.back();return false;}">{{ __('Go back') }}</a>
        </div>

        <p class="footer">
            {{ __('Casco Bay Pilot Car') }} — {{ __('need a hand?') }}
            <a href="{{ url('/feedback') }}">{{ __('let us know') }}</a>.
        </p>
    </main>
</body>
</html>
