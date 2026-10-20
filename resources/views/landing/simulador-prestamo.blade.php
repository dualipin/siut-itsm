@extends('landing.layout')

@section('content')
    <div
        data-vue="loans/simulator-form"
        data-client="load"
        data-props="{{ json_encode(['benefits' => $benefits->toArray()]) }}"
    ></div>
@endsection
