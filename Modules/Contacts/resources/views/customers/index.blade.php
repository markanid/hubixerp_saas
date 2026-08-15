@extends('layout')

@section('content-header')
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0"></h1>
            </div>
            <!-- /.col -->
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{route('profile.dashboard')}}">Dashboard</a></li>
                    <li class="breadcrumb-item active">{{$page_title}}</li>
                </ol>
            </div><!-- /.col -->
        </div><!-- /.row -->
    </div><!-- /.container-fluid -->
</div>
@endsection
<!-- /.content-header -->
@section('body')       
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-user-tag"></i> {{$page_title}}</h3>
                    <a class="btn btn-primary btn-sm btn-flat float-right" href="{{route('customers.create')}}"><i class="fas fa-plus-circle"></i> Create</a>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <table id="user_table" class="table table-hover text-nowrap">
                        <thead>
                            <tr>
                                <th>SNo</th>
                                <th>Customer</th>
                                <th>Phone</th>
                                <th>Options</th> 
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i=1;?>
                            @foreach($customers as $customer)
                                <tr>
                                    <td>{{$i++;}}</td>
                                    <td><a href="{{route('customers.show', $customer->id)}}">{{$customer->customer}}</a></td>
                                    <td>{{$customer->phone}}</td>
                                    <td>
                                    <a class="btn btn-app" href="{{route('customers.edit', $customer->id)}}"><i class="far fa-edit"></i></a>
                                    <a href="#" class="btn btn-app-delete delete-btn" data-url="{{ route('customers.delete', ['id' => $customer->id]) }}"><i class="far fa-trash-alt"></i></a>
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
@endsection

@include('partials.delete-modal')

@section('scripts')

@include('partials.delete-modal-script')
@include('partials.common-index-script', ['tableId' => 'user_table'])
@endsection
