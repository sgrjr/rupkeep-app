@extends('errors.layout')

@section('title', __('Page expired'))
@section('code', '419')
@section('heading', __('Your session expired'))
@section('message', __('For your security, this page timed out. Refresh and try again.'))

@section('actions')
    <a class="btn btn-primary" href="#" onclick="window.location.reload();return false;">{{ __('Refresh page') }}</a>
    <a class="btn btn-secondary" href="{{ url('/') }}">{{ __('Back to safety') }}</a>
@endsection
