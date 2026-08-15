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
                    <li class="breadcrumb-item active">{{$page_title}}</li>
                </ol>
            </div>
        </div>
    </div>
</div>
@endsection

@section('body')
<div class="card card-primary card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="far fa-building"></i> {{$page_title}}</h3>
        <div class="card-tools">
            <a class="btn btn-settings btn-sm btn-flat" href="{{route('company.settings')}}" title="Company Settings"><i class="fas fa-cog"></i> Settings</a>
            <a class="btn btn-success btn-sm btn-flat" href="{{route('company.edit')}}"><i class="fas fa-edit"></i> Edit</a>
            <a href="#" class="btn btn-danger btn-sm btn-flat delete-btn" data-url="{{ route('company.delete') }}"><i class="fas fa-trash"></i> Delete</a>
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
                                <td style="width: 135px;height: 69px;">Company Name</td>
                                <td style="color: #007bff;">{{ $name }}</td>
                            </tr>
                            <tr>
                                <td>Tag Line</td>
                                <td>{{ $tags }}</td>
                            </tr>
                            <tr>
                                <td>Phone</td>
                                <td>{{ $phone_number }}</td>
                            </tr>
                            <tr>
                                <td style="height: 103px;">Address</td>
                                <td>{{ $address }}</td>
                            </tr>
                            <tr>
                                <td>Email</td>
                                <td>{{ $email }}</td>
                            </tr>
                            <tr>
                                <td>Website</td>
                                <td>{{ $website_url }}</td>
                            </tr>
                            <tr>
                                <td>Licence No</td>
                                <td>{{ $license_number }}</td>
                            </tr>
                            <tr>
                                <td>GST No</td>
                                <td>{{ $gst_number }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card card-navy">
            <div class="card-header">
                <h3 class="card-title"><i class="far fa-image"></i> Images</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <tbody>
                            <tr>
                                <td style="width: 135px;height: 120px;">Company Logo</td>
                                <td>
                                    @if(!empty($logo))
                                        <p><img src="{{tenant_asset('company_logos/'.$logo)}}" alt="Company Logo" style="width: 200px; height: 100px;"></p>
                                    @else
                                        <p><img src="{{asset('uploads/demo_company_logo.png')}}" alt="Company Logo" style="width: 200px; height: 100px;"></p>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td style="height: 120px;">QR Code</td>
                                <td>
                                    @if(!empty($qr_code))
                                        <p><img src="{{tenant_asset('company_qr_codes/'.$qr_code)}}" alt="Company QR Code" style="width: 100px; height: 100px;"></p>
                                    @else
                                        <p><img src="{{asset('uploads/demo_company_qr.png')}}" alt="Company QR Code" style="width: 100px; height: 100px;"></p>
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="card card-navy">
            <div class="card-header">
                <h3 class="card-title"><i class="fa-solid fa-building-columns"></i> Bank Details</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <tbody>
                            <tr>
                                <td style="width: 135px;">Bank Name</td>
                                <td>{{ $bank_name }}</td>
                            </tr>
                            <tr>
                                <td>Bank IFSC</td>
                                <td>{{ $bank_ifsc }}</td>
                            </tr>
                            <tr>
                                <td>Bank Acno.</td>
                                <td>{{ $bank_acno }}</td>
                            </tr>
                            <tr>
                                <td>Bank Branch</td>
                                <td>{{ $bank_branch }}</td>
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
document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function(event) {
            event.preventDefault();
            let deleteUrl = this.getAttribute('data-url');

            Swal.fire({
                title: "Are you sure?",
                text: "This action cannot be undone!",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#d33",
                cancelButtonColor: "#3085d6",
                confirmButtonText: "Yes, delete it!"
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = deleteUrl;
                }
            });
        });
    });
});
</script>
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
