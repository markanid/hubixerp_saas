@extends('layout')

@php
    $formatValue = function ($value, $type = null) {
        if ($type === 'amount') {
            return 'Rs. ' . number_format((float) $value, 2);
        }

        if ($type === 'number') {
            $number = (float) $value;
            return fmod($number, 1.0) === 0.0 ? number_format($number, 0) : number_format($number, 2);
        }

        if ($type === 'date' && !empty($value)) {
            return date('d-m-Y', strtotime($value));
        }

        return $value;
    };
@endphp

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
                    <li class="breadcrumb-item"><a href="{{ route('reports.center') }}">Reports</a></li>
                    <li class="breadcrumb-item active">{{ $page_title }}</li>
                </ol>
            </div>
        </div>
    </div>
</div>
@endsection

@section('body')
<div class="container-fluid">
    <div class="card card-{{ $report['color'] }} card-outline">
        <div class="card-header">
            <h3 class="card-title"><i class="{{ $report['icon'] }}"></i> {{ $page_title }}</h3>
            <div class="card-tools">
                <a href="{{ route('reports.center') }}" class="btn btn-success btn-sm btn-flat">
                    <i class="fas fa-arrow-alt-circle-left"></i> Reports
                </a>
                <button onclick="window.print()" class="btn btn-warning btn-sm btn-flat">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>

        <div class="card-body">
            <form method="get" action="{{ route('reports.summary', $type) }}">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>From Date</label>
                            <input type="date" name="from_date" value="{{ $from_date }}" min="{{ $fy_start }}" max="{{ $fy_end }}" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>To Date</label>
                            <input type="date" name="to_date" value="{{ $to_date }}" min="{{ $fy_start }}" max="{{ $fy_end }}" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary btn-block btn-flat">
                                <i class="fas fa-filter"></i> Apply
                            </button>
                        </div>
                    </div>
                </div>
                <small class="text-muted">
                    Logged financial year: {{ session('financial_year') }} ({{ date('d-m-Y', strtotime($fy_start)) }} to {{ date('d-m-Y', strtotime($fy_end)) }})
                </small>
            </form>

            <div class="row">
                @foreach($cards as $card)
                    <div class="col-lg-3 col-md-4 col-sm-6">
                        <div class="small-box bg-{{ $report['color'] }}">
                            <div class="inner">
                                <h4>{{ $formatValue($card['value'] ?? 0, $card['type'] ?? null) }}</h4>
                                <p>{{ $card['label'] }}</p>
                            </div>
                            <div class="icon">
                                <i class="{{ $report['icon'] }}"></i>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @foreach($sections as $section)
                @php
                    $rows = collect($section['rows']);
                    $hasActions = $rows->contains(function ($row) {
                        $actions = is_array($row) ? ($row['_actions'] ?? []) : ($row->_actions ?? []);
                        return !empty($actions);
                    });
                @endphp
                <div class="card card-outline card-secondary">
                    <div class="card-header">
                        <h3 class="card-title">{{ $section['title'] }}</h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-sm mb-0">
                                <thead>
                                    <tr>
                                        @foreach($section['columns'] as $column)
                                            <th class="{{ in_array($column['type'] ?? '', ['amount', 'number']) ? 'text-right' : '' }}">
                                                {{ $column['label'] }}
                                            </th>
                                        @endforeach
                                        @if($hasActions)
                                            <th class="text-center" style="width: 130px;">Actions</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($rows as $row)
                                        <tr>
                                            @foreach($section['columns'] as $column)
                                                @php
                                                    $key = $column['key'];
                                                    $value = is_array($row) ? ($row[$key] ?? '') : ($row->{$key} ?? '');
                                                @endphp
                                                <td class="{{ in_array($column['type'] ?? '', ['amount', 'number']) ? 'text-right' : '' }}">
                                                    {{ $formatValue($value, $column['type'] ?? null) }}
                                                </td>
                                            @endforeach
                                            @if($hasActions)
                                                @php
                                                    $actions = is_array($row) ? ($row['_actions'] ?? []) : ($row->_actions ?? []);
                                                @endphp
                                                <td class="text-center text-nowrap">
                                                    @foreach($actions as $action)
                                                        <a href="{{ $action['url'] }}" class="btn btn-xs {{ $action['class'] }} btn-flat" title="{{ $action['label'] }}">
                                                            <i class="{{ $action['icon'] }}"></i>
                                                        </a>
                                                    @endforeach
                                                </td>
                                            @endif
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ count($section['columns']) + ($hasActions ? 1 : 0) }}" class="text-center text-muted">
                                                No records found.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
