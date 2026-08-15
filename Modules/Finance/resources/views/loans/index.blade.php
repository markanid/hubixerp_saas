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
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-hand-holding-usd"></i> {{$page_title}}</h3>
                    <a class="btn btn-primary btn-sm btn-flat float-right" href="{{ route('loans.create') }}"><i class="fas fa-plus-circle"></i> Create</a>
                </div>
                <div class="card-body">
                    <table id="loan_table" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>SNo</th>
                                <th>Date</th>
                                <th>Loan No.</th>
                                <th>Type</th>
                                <th>Financier/Beneficiary</th>
                                <th>Bank</th>
                                <th>Amount</th>
                                <th>Options</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($loans as $i => $row)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>{{ \Carbon\Carbon::parse($row->loan_date)->format('d/m/Y') }}</td>
                                    <td><a href="{{ route('loans.show', $row->id) }}">{{ $row->loan_vno }}</a></td>
                                    <td>{{ ucfirst($row->loan_type ?? 'liability') }}</td>
                                    <td>
                                        @if(($row->loan_type ?? 'liability') == 'asset' && $row->customer)
                                            <a href="{{ route('customers.show', $row->customer_id) }}">{{ $row->customer->customer }}</a>
                                        @elseif($row->vendor)
                                            <a href="{{ route('vendors.show', $row->vendor_id) }}">{{ $row->vendor->cp_name }}</a>
                                        @endif
                                    </td>
                                    <td>
                                        @if($row->banking)
                                            <a href="{{ route('banks.show', $row->bank_id) }}">{{ $row->banking->bk_bank }}</a>
                                        @endif
                                    </td>
                                    <td class="text-right">{{ number_format($row->amount, 2) }}</td>
                                    <td>
                                        <a href="#" class="btn btn-danger btn-sm btn-flat delete-btn" data-url="{{ route('loans.delete', ['id' => $row->id]) }}"><i class="fas fa-trash"></i></a>
                                    </td>
                                </tr>
                            @empty
                            @endforelse
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

        $("#loan_table").DataTable({
            "responsive": true, "lengthChange": false, "autoWidth": false,
            "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
        }).buttons().container().appendTo('#loan_table_wrapper .col-md-6:eq(0)');

        @if(session('success'))
            Toast.fire({
                icon: 'success',
                title: '{{ session('success') }}'
            });
        @endif
    });
</script>
@endsection
