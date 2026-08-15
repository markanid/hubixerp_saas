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
                    <h3 class="card-title"><i class="fas fa-user-cog"></i> {{$title}}</h3>
                    <a class="btn btn-primary btn-sm btn-flat float-right" href="{{route('users.create')}}"><i class="fas fa-plus-circle"></i> Create</a>
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="user_table" class="table table-bordered table-striped text-nowrap">
                            <thead>
                                <tr>
                                    <th>SNo</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Type</th> 
                                    <th>Created On</th>
                                    <th>Options</th> 
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i=1;?>
                                @foreach($users as $user)
                                    <tr>
                                        <td>{{$i++;}}</td>
                                        <td><a href="{{route('users.show', $user->id)}}">{{$user->user_name}}</a></td>
                                        <td>{{$user->email}}</td>
                                        <td>{{$user->user_role}}</td>
                                        <td>{{ date('d/m/Y', strtotime($user->created_at)) }}</td>
                                        <td>
                                        <a class="btn btn-app" href="{{route('users.edit', $user->id)}}"><i class="far fa-edit"></i></a>
                                        <a href="#" class="btn btn-app-delete delete-btn" data-url="{{ route('users.delete', ['id' => $user->id]) }}"><i class="far fa-trash-alt"></i></a>
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
<div class="modal fade" id="modal-sm">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Delete !</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to Delete ?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-info" data-dismiss="modal">No</button>
                <button type="button" class="btn btn-danger" id="delete_btn">Yes</button>
            </div>
        </div>
    <!-- /.modal-content -->
    </div>
<!-- /.modal-dialog -->
</div>
@endsection
@section('scripts')
<script>
document.addEventListener("DOMContentLoaded", function() {
    document.addEventListener('click', function(event) {
        // Check if the clicked element or its parent has the `delete-btn` class
        const deleteBtn = event.target.closest('.delete-btn');
        if (deleteBtn) {
            event.preventDefault();
            let deleteUrl = deleteBtn.getAttribute('data-url');

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
        }
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
        var table = $("#user_table").DataTable({
            "responsive": false, "lengthChange": false, "autoWidth": false,
            "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
        }).buttons().container().appendTo('#user_table_wrapper .col-md-6:eq(0)');

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
