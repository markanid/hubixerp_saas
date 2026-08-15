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
                    <li class="breadcrumb-item active">{{$title}}</li>
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
                    <h3 class="card-title"><i class="fa fa-th"></i> {{$title}}</h3>
                </div>
                <div class="card-body">
                    <div class="box box-primary">
                        <form action="{{ route('profile.restoreDB') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="box-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="backup_file">Select Backup File (SQL):</label>
                                        <input type="file" name="backup_file" id="backup_file" required accept=".sql">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="box-footer">
                            <div class="row">
                                <div class="col-md-12">
                                    <input class="btn-large btn-primary btn" type="submit" value="Restore Backup">
                                </div>
                            </div>
                        </div>
                        </form>
                    </div>
                </div>
 
            </div>
        </div>
    </div>
</div>

@endsection
@section('scripts')
<script>
    function readURL(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function (e) {
                $('#blah')
                .attr('src', e.target.result)
                .width(180)
                .height(65);
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>
@endsection