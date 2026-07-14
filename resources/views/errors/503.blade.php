@extends('errors.layout')

@section('title', __('Down for maintenance'))
@section('code', '503')
@section('heading', __('Down for a quick tune-up'))
@section('message', __('Casco Bay Pilot Car is briefly offline for maintenance. We’ll be back shortly — thanks for your patience.'))

@section('actions')
    <a class="btn btn-primary" href="#" onclick="window.location.reload();return false;">{{ __('Try again') }}</a>
@endsection
