@extends('landing.layout')

@section('content')
    <div data-vue="contact" data-props="{{ json_encode(['syndicate' => config('syndicate')]) }}"></div>
@endsection
