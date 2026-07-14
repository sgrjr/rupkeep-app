@extends('errors.layout')

@section('title', __('Too many requests'))
@section('code', '429')
@section('heading', __('Slow down a moment'))
@section('message', __('You’ve made a lot of requests in a short time. Please wait a bit and try again.'))
