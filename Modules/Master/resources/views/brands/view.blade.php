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
                    <li class="breadcrumb-item"><a href="{{route('brands.index')}}">Brands</a></li>
                    <li class="breadcrumb-item active"> {{ $page_title }}</li>
                </ol>
            </div>
        </div>
    </div>
</div>
@endsection

@section('body')
<div class="card card-primary card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="far fa-star"></i> {{$page_title}}</h3>
        <div class="card-tools">
            <a class="btn btn-success btn-sm btn-flat" href="{{route('brands.edit', $brand->id)}}"><i class="fas fa-edit"></i> Edit</a>
            <a class="btn btn-dark btn-sm btn-flat" href="{{route('brands.index')}}"><i class="fas fa-arrow-alt-circle-left"></i> Back</a>
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
                                <td style="width: 126px;height: 203px;">Brand Image</td>
                                <td>
                                    @if(!empty($brand) && !empty($brand->brand_image))
                                        <p><img src="{{tenant_asset('brand_logos/'.$brand->brand_image)}}" alt="Brand Photo" style="width: 200px; height: 100px;"></p>
                                    @else
                                        <p><img src="{{asset('uploads/avatar.png')}}" alt="Brand Photo" style="width: 200px; height: 50px;"></p>
                                    @endif 
                                </td>
                            </tr>
                            <tr>
                                <td style="height: 69px;">Brand Name</td>
                                <td style="color: #007bff;">{{ $brand->brand }}</td>
                            </tr>
                            <tr>
                                <td>Created On</td>
                                <td>{{ date('d/m/Y', strtotime($brand->created_at)) }}</td>
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