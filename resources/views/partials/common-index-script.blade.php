@php 
$tableId = $tableId ?? 'brand_table'; 
$noButtons = in_array($tableId, ['sale_table', 'purchase_table', 'estimation_table', 'consumption_table', 'return_table', 'service_table', 'expense_table']);
@endphp

<script>
$(document).ready(function(){
    var Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000
    });

    var table = $("#{{ $tableId }}").DataTable({
        "scrollX": true,
        "responsive": false,
        "lengthChange": false,
        "autoWidth": false,
        @if($noButtons)
            // hide search box + buttons
            "dom": 't<"bottom"ip>'
        @else
            // show buttons + search
            "dom": 'Bfrtip',
            "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
        @endif
    });

    @unless($noButtons)
        table.buttons().container().appendTo('#{{ $tableId }}_wrapper .col-md-6:eq(0)');
    @endunless

    @if(session('success'))
        Toast.fire({ icon: 'success', title: '{{ session('success') }}' });
    @endif

    @if(session('info'))
        Toast.fire({ icon: 'info', title: '{{ session('info') }}' });
    @endif

    $('#reservation').daterangepicker({
        autoUpdateInput: false,
        locale: {
            cancelLabel: 'Clear',
            format: 'DD/MM/YYYY'
        },
        minDate: moment(fyStart),
        maxDate: moment(fyEnd),
    });

    $('#reservation').on('apply.daterangepicker', function(ev, picker) {
        $(this).val(picker.startDate.format('DD/MM/YYYY') + ' - ' + picker.endDate.format('DD/MM/YYYY'));
    });

    $('#reservation').on('cancel.daterangepicker', function(ev, picker) {
        $(this).val('');
    });
});
</script>