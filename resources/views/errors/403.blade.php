@extends('errors.layout')

@section('title', __('Access restricted'))
@section('code', '403')
@section('heading', __('Access restricted'))
@section('message', (isset($exception) ? $exception->getMessage() : '') ?: __('You don’t have permission to view this page. If you think that’s a mistake, let us know and we’ll sort it out.'))
