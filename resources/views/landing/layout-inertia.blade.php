@extends('landing.layout')

@section('base-head')
    @include('partials.inertia-head')
@endsection

@section('content')
    <x-inertia::app />
@endsection