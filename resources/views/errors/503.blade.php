@extends('errors.layout')

@section('title', 'Scheduled Maintenance')
@section('code', '503')
@section('heading', 'Scheduled Maintenance')
@section('message', 'AURA LUXE is temporarily unavailable while we improve your experience.')

@section('icon')
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.67 2.67 0 0 0 21 17.25l-5.83-5.83m0 0a5.333 5.333 0 0 0-7.5-7.5l2.09 2.09a2.67 2.67 0 0 1 3.75 3.75l-2.09 2.09m0 0L7.5 13.5m0 0L3 18l3 3 4.5-4.5m-3-3 2.09-2.09" />
    </svg>
@endsection

@section('actions')
    <a href="javascript:window.location.reload()" class="btn btn-primary">Check Again</a>
@endsection

