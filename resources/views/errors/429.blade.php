@extends('errors.layout')

@section('title', 'Too Many Requests')
@section('code', '429')
@section('heading', 'Too Many Requests')
@section('message', 'Too many requests were received. Please wait before trying again.')

@section('icon')
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
    </svg>
@endsection

@section('actions')
    <a href="javascript:window.location.reload()" class="btn btn-primary">Refresh Page</a>
    <a href="/" class="btn btn-secondary">Return to Storefront</a>
@endsection

