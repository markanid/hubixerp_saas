@extends('layout')

@section('content-header')
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"></div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item">Dashboard</li>
                    <li class="breadcrumb-item active">{{ $page_title }}</li>
                </ol>
            </div>
        </div>
    </div>
</div>
@endsection

@section('body')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-8">
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-cash-register"></i> {{ $counter->name }}</h3>
                    <a class="btn btn-dark btn-sm btn-flat float-right" href="{{ route('counters.index') }}"><i class="fas fa-arrow-alt-circle-left"></i> Back</a>
                    <a class="btn btn-info btn-sm btn-flat float-right mr-1" href="{{ route('counters.edit', $counter->id) }}"><i class="fas fa-edit"></i> Edit</a>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <tr><th style="width: 180px;">Counter Name</th><td>{{ $counter->name }}</td></tr>
                        <tr><th>User</th><td>{{ $counter->user?->user_name ?? 'Any user' }}</td></tr>
                        <tr><th>Status</th><td>{{ $counter->is_active ? 'Active' : 'Inactive' }}</td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
