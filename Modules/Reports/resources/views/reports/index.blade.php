@extends('layout')

@section('content-header')
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">{{ $page_title }}</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{ route('profile.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">{{ $page_title }}</li>
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
                    <h3 class="card-title"><i class="fas fa-chart-bar"></i> Report Center</h3>
                    <div class="card-tools">
                        <a href="{{ route('dailyreports.index') }}" class="btn btn-primary btn-sm btn-flat">
                            <i class="far fa-calendar-alt"></i> Daily Report
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        @foreach($reports as $type => $report)
                            <div class="col-xl-3 col-lg-4 col-md-6">
                                <div class="card card-{{ $report['color'] }} card-outline h-100">
                                    <div class="card-body">
                                        <div class="d-flex align-items-start">
                                            <div class="mr-3 text-{{ $report['color'] }}">
                                                <i class="{{ $report['icon'] }} fa-2x"></i>
                                            </div>
                                            <div>
                                                <h5 class="mb-1">{{ $report['label'] }}</h5>
                                                <p class="text-muted mb-3">{{ $report['description'] }}</p>
                                                <a href="{{ route('reports.summary', $type) }}" class="btn btn-sm btn-outline-{{ $report['color'] }} btn-flat">
                                                    Open Report
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if((\Modules\Settings\app\Models\Company::query()->value('inventory_mode') ?? 'standard') === 'batch')
                        <hr>
                        <h5 class="mb-3"><i class="fas fa-pills"></i> Batch Reports</h5>
                        <div class="row">
                            @foreach([
                                'stock' => 'Batch Stock',
                                'near-expiry' => 'Near Expiry',
                                'expired' => 'Expired Stock',
                                'movements' => 'Batch Movement',
                                'ledger' => 'Batch Ledger',
                                'profitability' => 'Batch Profitability',
                            ] as $type => $label)
                                <div class="col-lg-2 col-md-4 col-sm-6">
                                    <a class="btn btn-outline-secondary btn-block btn-flat mb-2" href="{{ route('batch-reports.index', $type) }}">
                                        {{ $label }}
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    @if((\Modules\Settings\app\Models\Company::query()->value('inventory_mode') ?? 'standard') === 'mrp')
                        <hr>
                        <h5 class="mb-3"><i class="fas fa-tags"></i> MRP Stock Reports</h5>
                        <div class="row">
                            @foreach([
                                'stock' => 'MRP-wise Stock',
                                'movements' => 'MRP Movements',
                                'profitability' => 'MRP Profitability',
                            ] as $type => $label)
                                <div class="col-lg-3 col-md-4 col-sm-6">
                                    <a class="btn btn-outline-secondary btn-block btn-flat mb-2" href="{{ route('mrp-reports.index', $type) }}">
                                        {{ $label }}
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection