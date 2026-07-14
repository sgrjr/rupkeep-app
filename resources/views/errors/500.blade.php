@extends('errors.layout')

@section('title', __('Server error'))
@section('code', '500')
@section('heading', __('Something went wrong on our end'))
@section('message', __('An unexpected error occurred. Our team has been notified — please try again in a moment.'))
