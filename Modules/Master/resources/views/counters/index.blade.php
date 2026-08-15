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
        <div class="col-12">
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-cash-register"></i> {{ $page_title }}</h3>
                    <a class="btn btn-primary btn-sm btn-flat float-right" href="{{ route('counters.create') }}"><i class="fas fa-plus-circle"></i> Create</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="master_table" class="table table-bordered table-striped text-nowrap">
                            <thead>
                            <tr>
                                <th>Sl.No</th>
                                <th>Counter Name</th>
                                <th>User</th>
                                <th>Status</th>
                                <th>Options</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($counters as $row)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td><a href="{{ route('counters.show', $row->id) }}">{{ $row->name }}</a></td>
                                    <td>{{ $row->user?->user_name ?? 'Any user' }}</td>
                                    <td>
                                        <span class="badge {{ $row->is_active ? 'badge-success' : 'badge-secondary' }}">
                                            {{ $row->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td>
                                        <a class="btn btn-app" href="{{ route('counters.edit', $row->id) }}"><i class="far fa-edit"></i></a>
                                        <a href="#" class="btn btn-app-delete delete-btn" data-url="{{ route('counters.delete', ['id' => $row->id]) }}"><i class="far fa-trash-alt"></i></a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@include('partials.delete-modal')
@section('scripts')
@include('partials.delete-modal-script')
@include('partials.common-index-script', ['tableId' => 'master_table'])
@endsection
