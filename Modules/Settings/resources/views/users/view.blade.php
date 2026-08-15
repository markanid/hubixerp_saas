@extends('layout')

@section('content-header')
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0"></h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{route('profile.dashboard')}}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{route('users.index')}}">Users</a></li>
                    <li class="breadcrumb-item active">{{$title}}</li>
                </ol>
            </div>
        </div>
    </div>
</div>
@endsection

@section('body')
<div class="card card-primary card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-user-cog"></i> {{$title}}</h3>
        <div class="card-tools">
            <a class="btn btn-success btn-sm btn-flat" href="{{route('users.edit', $user->id)}}"><i class="fas fa-edit"></i> Edit</a>
            <a class="btn btn-dark btn-sm btn-flat" href="{{route('users.index')}}"><i class="fas fa-arrow-alt-circle-left"></i> Back</a>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-6">
        <div class="card card-navy">
            <div class="card-header">
                <h3 class="card-title"><i class="far fa-file-alt"></i> Basic Details</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <tbody>
                            <tr>
                                <td style="width: 126px;height: 203px;">User Image</td>
                                <td>
                                    @if(!empty($user) && !empty($user->user_logo))
                                        <p><img src="{{tenant_asset('user_logos/'.$user->user_logo)}}" alt="User Photo" style="width: 150px; height: 150px;"></p>
                                    @else
                                        <p><img src="{{asset('uploads/avatar.png')}}" alt="User Photo" style="width: 150px; height: 150px;"></p>
                                    @endif 
                                </td>
                            </tr>
                            <tr>
                                <td style="height: 69px;">User Name</td>
                                <td style="color: #007bff;">{{ $user->user_name }}</td>
                            </tr>
                            <tr>
                                <td>Email ID</td>
                                <td>{{ $user->email }}</td>
                            </tr>
                            <tr>
                                <td>User Type</td>
                                <td>{{ $user->user_role }}</td>
                            </tr>
                            <tr>
                                <td>Created On</td>
                                <td>{{ date('d/m/Y', strtotime($user->created_at)) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function(){
        var Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000
        });
        // Check for the flash message and display the SweetAlert2 popup
        @if(session('success'))
            Toast.fire({
                icon: 'success',
                title: '{{ session('success') }}'
            });
        @endif
        @if(session('info'))
            Toast.fire({
                icon: 'info',
                title: '{{ session('info') }}'
            });
        @endif
    });
</script>
@endsection