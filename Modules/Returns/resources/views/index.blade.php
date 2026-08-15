@extends('returns::layouts.master')

@section('content')
    <h1>Hello World</h1>

    <p>Module: {!! config('returns.name') !!}</p>
@endsection
