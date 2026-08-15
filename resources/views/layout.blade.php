<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>HubixERP</title>
  
  {{-- <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/smoothness/jquery-ui.css"> --}}

  <!-- Google Font: Source Sans Pro -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">

  <!-- Font Awesome Icons -->
  {{-- <link rel="stylesheet" href="{{asset('admin-assets/plugins/fontawesome-free/css/all.min.css')}}"> --}}
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <link rel="stylesheet" href="{{asset('admin-assets/plugins/daterangepicker/daterangepicker.css')}}">
  {{-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" /> --}}

  <!-- overlayScrollbars -->
  <link rel="stylesheet" href="{{asset('admin-assets/plugins/overlayScrollbars/css/OverlayScrollbars.min.css')}}">
  {{-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars/css/OverlayScrollbars.min.css" /> --}}

  <!-- Tempusdominus Bootstrap 4 -->
  <link rel="stylesheet" href="{{asset('admin-assets/plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css')}}">
  {{-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tempusdominus-bootstrap-4/build/css/tempusdominus-bootstrap-4.min.css" /> --}}

  <!-- Theme style -->
  <link rel="stylesheet" href="{{asset('admin-assets/dist/css/adminlte.min.css')}}">
  {{-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css" /> --}}

  <!-- SweetAlert2 -->
  {{-- <link rel="stylesheet" href="{{asset('admin-assets/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css')}}"> --}}
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4/bootstrap-4.min.css" />

    <!-- DataTables -->
  <link rel="stylesheet" href="{{asset('admin-assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css')}}">
  <link rel="stylesheet" href="{{asset('admin-assets/plugins/datatables-responsive/css/responsive.bootstrap4.min.css')}}">
  <link rel="stylesheet" href="{{asset('admin-assets/plugins/datatables-buttons/css/buttons.bootstrap4.min.css')}}">
  {{-- <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css" />
  <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap4.min.css" />
  <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap4.min.css" /> --}}

  <link rel="shortcut icon" type="image/x-icon" href="{{asset('admin-assets/dist/img/favicon.png')}}">

  <!-- Select2 -->
  <link rel="stylesheet" href="{{asset('admin-assets/plugins/select2/css/select2.min.css')}}">
  <link rel="stylesheet" href="{{asset('admin-assets/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css')}}">
  {{-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap4-theme@1.5.2/dist/select2-bootstrap4.min.css" /> --}}

   {{-- Toastr alert --}}
  <link rel="stylesheet" href="{{ asset('admin-assets/plugins/toastr/toastr.min.css') }}">

   <style>
    .dataTables_filter,.dataTables_paginate {
      float:right;
    }
    #example1_paginate,#example1_filter{
      float:right;
    }
    .btn-settings {
      color: #fff;
      background-color: #516375;
      border-color: #516375;
    }
    .btn-settings:hover,
    .btn-settings:focus {
      color: #fff;
      background-color: #435260;
      border-color: #3d4b58;
    }
    .layout-fixed .main-sidebar .sidebar {
      overflow-y: auto;
      scrollbar-width: thin;
      scrollbar-color: rgba(108, 117, 125, .8) transparent;
    }
    .layout-fixed .main-sidebar .sidebar::-webkit-scrollbar {
      width: 8px;
    }
    .layout-fixed .main-sidebar .sidebar::-webkit-scrollbar-track {
      background: transparent;
    }
    .layout-fixed .main-sidebar .sidebar::-webkit-scrollbar-thumb {
      background-color: rgba(108, 117, 125, .75);
      border-radius: 4px;
    }
    .layout-fixed .main-sidebar .sidebar::-webkit-scrollbar-thumb:hover {
      background-color: rgba(52, 58, 64, .9);
    }
    .layout-fixed .main-sidebar .os-scrollbar-vertical {
      opacity: 1 !important;
      visibility: visible !important;
    }
    .layout-fixed .main-sidebar .os-scrollbar-handle {
      background-color: rgba(108, 117, 125, .75) !important;
    }
  </style>
</head>
{{-- <body class="hold-transition sidebar-mini layout-fixed layout-footer-fixed layout-navbar-fixed"> --}}
<body class="sidebar-mini layout-navbar-fixed layout-fixed layout-footer-fixed">
<div class="wrapper">

  <!-- Preloader -->
  <!-- Navbar -->
  @php
    $navbarPosSession = null;
    $navbarPosCounters = collect();
    if (Auth::check() && \Illuminate\Support\Facades\Schema::hasTable('pos_sessions')) {
        $navbarPosSession = \Modules\Pos\app\Models\PosSession::where('user_id', Auth::id())
            ->where('status', 'open')
            ->latest('id')
            ->first();
    }
    if (Auth::check() && \Illuminate\Support\Facades\Schema::hasTable('pos_counters')) {
        $navbarPosCounters = \Modules\Pos\app\Models\PosCounter::where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('user_id')
                    ->orWhere('user_id', Auth::id());
            })
            ->whereDoesntHave('sessions', function ($query) {
                $query->where('status', 'open');
            })
            ->orderBy('name')
            ->get();
    }
  @endphp
  <nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <!-- Left navbar links -->
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
      </li>
      @can('pos.access')
      <li class="nav-item">
        <a
          class="btn btn-success btn-sm ml-2 mt-1"
          href="{{ $navbarPosSession ? route('pos.index') : '#' }}"
          @if(!$navbarPosSession) data-toggle="modal" data-target="#navbarPosOpenModal" @endif
        >
          <i class="fas fa-cash-register mr-1"></i> POS
        </a>
      </li>
      @endcan
    </ul>
    <ul class="navbar-nav ml-auto">
      <!-- Messages Dropdown Menu -->
      <li class="nav-item dropdown user-menu">
        <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
          <img src="{{ asset('uploads/user.jpg') }}" class="img-sm img-circle" alt="User Image" style="margin-top: -4px;margin-right: -15px;">
          
        </a>
        <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right" style="left: inherit; right: 0px;">
          <!-- User image -->
          <li class="user-header bg-user">
            <img src="{{ tenant_asset('user_logos/'.Auth::user()->user_logo) }}" class="img-circle elevation-2" alt="User Image">

            <p>
              {{ Auth::user()->user_name }} - {{ Auth::user()->user_role }}
              <small>Financial Year :: {{ session('financial_year') }}</small>
            </p>
          </li>
          
          <!-- Menu Footer-->
          <li class="user-footer">
            <a href="{{route('users.showprofile',Auth::user()->id)}}" class="btn btn-default btn-flat">Profile</a>
            <a href="{{route('auth.logout')}}" class="btn btn-default btn-flat float-right">Log out</a>
          </li>
        </ul>
      </li>
      
      @php
        $purchaseDueNotifications = \Modules\Purchase\app\Models\Purchase::overduePaymentNotifications();
        $saleDueNotifications = \Modules\Sale\app\Models\Sale::overduePaymentNotifications();
        $purchaseDueNotificationCount = $purchaseDueNotifications->count();
        $saleDueNotificationCount = $saleDueNotifications->count();
        $paymentDueNotificationCount = $purchaseDueNotificationCount + $saleDueNotificationCount;
      @endphp
      <li class="nav-item dropdown">
        <a class="nav-link" data-toggle="dropdown" href="#" aria-expanded="false">
          <i class="far fa-bell"></i>
          @if($paymentDueNotificationCount > 0)
            <span class="badge badge-warning navbar-badge">{{ $paymentDueNotificationCount }}</span>
          @endif
        </a>
        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right" style="left: inherit; right: 0px;">
          <span class="dropdown-item dropdown-header">{{ $paymentDueNotificationCount }} Payment Due Notifications</span>
          @forelse($purchaseDueNotifications->take(5) as $duePurchase)
            <div class="dropdown-divider"></div>
            <a href="{{ route('purchases.show', $duePurchase->pu_id) }}" class="dropdown-item">
              <i class="fas fa-file-invoice-dollar mr-2 text-warning"></i>
              <span>Purchase {{ $duePurchase->pu_vno }} - {{ $duePurchase->vendor->cp_name ?? 'Supplier' }}</span>
              <span class="float-right text-muted text-sm">{{ \Carbon\Carbon::parse($duePurchase->pu_due_date)->format('d-m-Y') }}</span>
              <div class="text-sm text-muted">Due: Rs. {{ number_format($duePurchase->due_balance, 2) }}</div>
            </a>
          @empty
          @endforelse
          @foreach($saleDueNotifications->take(5) as $dueSale)
            <div class="dropdown-divider"></div>
            <a href="{{ route('sales.show', $dueSale->sa_id) }}" class="dropdown-item">
              <i class="fas fa-file-invoice mr-2 text-warning"></i>
              <span>Sale {{ $dueSale->sa_vno }} - {{ $dueSale->customer->customer ?? 'Customer' }}</span>
              <span class="float-right text-muted text-sm">{{ \Carbon\Carbon::parse($dueSale->sa_due_date)->format('d-m-Y') }}</span>
              <div class="text-sm text-muted">Due: Rs. {{ number_format($dueSale->due_balance, 2) }}</div>
            </a>
          @endforeach
          @if($paymentDueNotificationCount === 0)
            <div class="dropdown-divider"></div>
            <span class="dropdown-item text-muted">
              <i class="far fa-check-circle mr-2"></i> No overdue payments
            </span>
          @endif
          @if($purchaseDueNotificationCount > 5 || $saleDueNotificationCount > 5)
            <div class="dropdown-divider"></div>
            <a href="{{ route('purchases.index') }}" class="dropdown-item dropdown-footer">View Payment Lists</a>
          @endif
        </div>
      </li>

      <li class="nav-item">
        <a class="nav-link" data-widget="fullscreen" href="#" role="button">
          <i class="fas fa-expand-arrows-alt" style="font-size: medium;"></i>
        </a>
      </li>
    </ul>
  </nav>
  <!-- /.navbar -->

  @can('pos.access')
  <div class="modal fade" id="navbarPosOpenModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Open POS Counter</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          @if($navbarPosCounters->isEmpty())
            <div class="alert alert-warning mb-0">
              No available POS counter found. Create or activate one from Master > POS Counters.
            </div>
          @else
            <div class="form-group">
              <label>Counter</label>
              <select id="navbarPosCounterId" class="form-control">
                @foreach($navbarPosCounters as $counter)
                  <option value="{{ $counter->id }}">{{ $counter->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="form-group mb-0">
              <label>Opening Cash</label>
              <input id="navbarPosOpeningCash" type="number" min="0" step="0.01" class="form-control" value="0">
            </div>
          @endif
        </div>
        <div class="modal-footer">
          <a href="{{ route('counters.create') }}" class="btn btn-outline-secondary mr-auto">Create Counter</a>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button id="navbarOpenPosBtn" type="button" class="btn btn-success" {{ $navbarPosCounters->isEmpty() ? 'disabled' : '' }}>
            Open & Go POS
          </button>
        </div>
      </div>
    </div>
  </div>
  @endcan

  @include('sidebar')

  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
     @yield('content-header')

    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
        @yield('body')
        
      </div>
    </section>
     
  </div>
  <!-- /.content-wrapper -->
  <footer class="main-footer text-sm">
    <strong>Powered by <a href="https://apexsoftlabs.com">Apex Soft Labs</a>.</strong>
    <div class="float-right"><b>Version</b> 2.0</div>
  </footer>

  <!-- Control Sidebar -->
  <aside class="control-sidebar control-sidebar-dark">
    <!-- Control sidebar content goes here -->
  </aside>
  <!-- /.control-sidebar -->
</div>
<!-- ./wrapper -->

<!-- jQuery -->
{{-- <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script> --}}
<script src="{{asset('admin-assets/plugins/jquery/jquery.min.js')}}"></script>

<!-- jQuery UI 1.11.4 -->
{{-- <script src="https://code.jquery.com/ui/1.13.3/jquery-ui.min.js"></script> --}}
<script src="{{asset('admin-assets/plugins/jquery-ui/jquery-ui.min.js')}}"></script>

<!-- Resolve conflict in jQuery UI tooltip with Bootstrap tooltip -->
<script>
  $.widget.bridge('uibutton', $.ui.button)
</script>

<!-- Bootstrap 4 -->
{{-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script> --}}
<script src="{{asset('admin-assets/plugins/bootstrap/js/bootstrap.bundle.min.js')}}"></script>

<!-- ChartJS -->
<script src="{{asset('admin-assets/plugins/chart.js/Chart.min.js')}}"></script>
{{-- <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script> --}}

<!-- jQuery Knob Chart -->
{{-- <script src="https://cdn.jsdelivr.net/npm/jquery-knob@1.2.13/dist/jquery.knob.min.js"></script> --}}
<script src="{{asset('admin-assets/plugins/jquery-knob/jquery.knob.min.js')}}"></script>

<!-- InputMask -->
{{-- <script src="https://cdn.jsdelivr.net/npm/inputmask@5.0.8/dist/jquery.inputmask.min.js"></script> --}}
<script src="{{asset('admin-assets/plugins/inputmask/jquery.inputmask.min.js')}}"></script>

<!-- Moment.js -->
{{-- <script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script> --}}
<script src="{{asset('admin-assets/plugins/moment/moment.min.js')}}"></script>

<!-- daterangepicker -->
<script src="{{asset('admin-assets/plugins/daterangepicker/daterangepicker.js')}}"></script>
{{-- <script src="https://cdn.jsdelivr.net/npm/daterangepicker@3.1/daterangepicker.min.js"></script> --}}

<!-- Tempusdominus Bootstrap 4 -->
<script src="{{asset('admin-assets/plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js')}}"></script>
{{-- <script src="https://cdn.jsdelivr.net/npm/tempusdominus-bootstrap-4@5.39.0/build/js/tempusdominus-bootstrap-4.min.js"></script> --}}

<!-- Summernote -->
<script src="{{asset('admin-assets/plugins/summernote/summernote-bs4.min.js')}}"></script>
{{-- <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-bs4.min.js"></script> --}}

<!-- overlayScrollbars -->
<script src="{{asset('admin-assets/plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js')}}"></script>
{{-- <script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.1.1/js/OverlayScrollbars.min.js"></script> --}}

<!-- AdminLTE App -->
<script src="{{asset('admin-assets/dist/js/adminlte.js')}}"></script>
{{-- <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script> --}}

<!-- Datatable plugins -->
<script src="{{asset('admin-assets/plugins/datatables/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('admin-assets/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js')}}"></script>
<script src="{{asset('admin-assets/plugins/datatables-responsive/js/dataTables.responsive.min.js')}}"></script>
<script src="{{asset('admin-assets/plugins/datatables-responsive/js/responsive.bootstrap4.min.js')}}"></script>
<script src="{{asset('admin-assets/plugins/datatables-buttons/js/dataTables.buttons.min.js')}}"></script>
<script src="{{asset('admin-assets/plugins/datatables-buttons/js/buttons.bootstrap4.min.js')}}"></script>
<script src="{{asset('admin-assets/plugins/jszip/jszip.min.js')}}"></script>
<script src="{{asset('admin-assets/plugins/pdfmake/pdfmake.min.js')}}"></script>
<script src="{{asset('admin-assets/plugins/pdfmake/vfs_fonts.js')}}"></script>
<script src="{{asset('admin-assets/plugins/datatables-buttons/js/buttons.html5.min.js')}}"></script>
<script src="{{asset('admin-assets/plugins/datatables-buttons/js/buttons.print.min.js')}}"></script>
<script src="{{asset('admin-assets/plugins/datatables-buttons/js/buttons.colVis.min.js')}}"></script>
{{-- <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/pdfmake@0.2.7/build/pdfmake.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/pdfmake@0.2.7/build/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script> --}}

<!-- bs-custom-file-input -->
{{-- <script src="https://cdn.jsdelivr.net/npm/bs-custom-file-input@1.3.4/dist/bs-custom-file-input.min.js"></script> --}}
<script src="{{asset('admin-assets/plugins/bs-custom-file-input/bs-custom-file-input.min.js')}}"></script>

<!-- SweetAlert2 again (optional if you want the separate local version replaced too) -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
{{-- <script src="{{asset('admin-assets/plugins/sweetalert2/sweetalert2.min.js')}}"></script> --}}

<!-- jQuery Validate -->
{{-- <script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script> --}}
<script src="{{asset('admin-assets/dist/js/jquery.validate.min.js')}}"></script>

<!-- Select2 -->
{{-- <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.full.min.js"></script> --}}
<script src="{{asset('admin-assets/plugins/select2/js/select2.full.min.js')}}"></script>

{{-- Toastr alert --}}
<script src="{{ asset('admin-assets/plugins/toastr/toastr.min.js') }}"></script>


@yield('scripts')
<script>
  $(function () {
    $("#example1").DataTable({
      "responsive": true, "lengthChange": false, "autoWidth": false,
      "buttons": ["copy", "csv", "excel", "pdf", "print"]
    }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
    // "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
    // .buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)')
    // $('#example2').DataTable({
    //   "paging": true,
    //   "lengthChange": false,
    //   "searching": false,
    //   "ordering": true,
    //   "info": true,
    //   "autoWidth": false,
    //   "responsive": true,
    // });
    //Date and time picker
    $('#reservationdatetime').datetimepicker({ icons: { time: 'far fa-clock' } });
    $('#reservationdate').datetimepicker({
        format: 'DD/MM/YYYY'
    });
    
  });
</script>
@can('pos.access')
<script>
  $('#navbarOpenPosBtn').on('click', function () {
    const button = $(this);
    button.prop('disabled', true);

    $.ajax({
      url: "{{ route('pos.sessions.open') }}",
      method: 'POST',
      data: {
        _token: "{{ csrf_token() }}",
        pos_counter_id: $('#navbarPosCounterId').val(),
        opening_cash: $('#navbarPosOpeningCash').val()
      }
    }).done(function () {
      window.location.href = "{{ route('pos.index') }}";
    }).fail(function (xhr) {
      button.prop('disabled', false);
      const message = xhr.responseJSON && xhr.responseJSON.message
        ? xhr.responseJSON.message
        : 'Could not open POS counter.';
      if (typeof toastr !== 'undefined') {
        toastr.error(message);
      } else {
        alert(message);
      }
    });
  });
</script>
@endcan
<script>
    // Apply global setting to disable ordering
    $.extend(true, $.fn.dataTable.defaults, {
        ordering: false
    });
</script>
</body>
</html>
