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
                    <li class="breadcrumb-item"><a href="">Dashboard</a></li>
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
            <a class="btn btn-warning btn-sm btn-flat" href="{{route('users.changePasswordForm')}}"><i class="fas fa-key"></i> Change Password</a>
            <a class="btn btn-info btn-sm btn-flat" href="{{route('users.edit', $user->id)}}"><i class="fas fa-edit"></i> Edit</a>
            <a class="btn btn-dark btn-sm btn-flat" href="{{route('users.index')}}"><i class="fas fa-arrow-alt-circle-left"></i> Back</a>
        </div>
    </div>
    <div class="card-body">
        
        <div class="table-responsive">
            <table class="table table-bordered" style="margin-bottom: 10px;">
                <tbody>
                    <tr>
                        <td style="background-color:#096ca5; color:#fff;">Name</td>
                        <td><b style="color:#096ca5;">{{ $user->user_name }}</b>
                        </td>
                    </tr>
                </tbody>
            </table>
       </div>
        <div class="table-responsive">
            <table class="table table-bordered">
                <tbody>
                    <tr>
                        <td rowspan="3">
                            <div>
                                @if(!empty($user) && !empty($user->user_logo))
                                    <p><img src="{{tenant_asset('user_logos/'.$user->user_logo)}}" alt="User Photo" style="width: 150px; height: 150px;"></p>
                                @else
                                    <p><img src="{{asset('uploads/avatar.png')}}" alt="User Photo" style="width: 150px; height: 150px;"></p>
                                @endif 
                            </div>
                        </td>
                        <td>
                            <span>Created On :</span>
                            <label>{{ date('d/m/Y', strtotime($user->created_at)) }}</label>
                        </td>
                        <td>
                            <span>Email ID :</span>
                            <label>{{ $user->email }}</label>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <span>User Type :</span>
                            <label>{{ $user->user_role }}</label>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
       
    </div>
   
</div>
@endsection
