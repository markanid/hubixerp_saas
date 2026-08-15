@extends('layout')

@section('content-header')
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0"><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
            </div><!-- /.col -->
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="#">Dashboard</a></li>
                </ol>
            </div><!-- /.col -->
        </div><!-- /.row -->
    </div><!-- /.container-fluid -->
</div>
@endsection
<!-- /.content-header -->

@section('body')
<!-- Main row -->
<section class="content">
    <div class="container-fluid">
        <!-- Small boxes (Stat box) -->
        <div class="row">
            <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box" style="max-height: 80px;">
                    <span class="info-box-icon bg-info elevation-1">
                        <i class="fas fa-boxes"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Products</span>
                        <span class="d-block text-xs">
                            Total Products:
                            <span class="text-info font-weight-bold">{{ number_format($productsCount ?? 0) }}</span>
                        </span>
                        <span class="d-block text-xs">
                            Stock Value:
                            <span class="text-success font-weight-bold">{{ $currencySymbol ?? '₹' }} {{ number_format($stockValue ?? 0, 2) }}</span>
                        </span>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box" style="max-height: 80px;">
                    <span class="info-box-icon bg-primary elevation-1">
                        <i class="fas fa-user-tag"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Customers</span>
                        <span class="info-box-number">{{ number_format($customersCount ?? 0) }}</span>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box" style="max-height: 80px;">
                    <span class="info-box-icon bg-warning elevation-1">
                        <i class="fas fa-cart-arrow-down"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Purchase</span>
                        <span class="info-box-number">{{ number_format($purchaseCount ?? 0) }}</span>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box" style="max-height: 80px;">
                    <span class="info-box-icon bg-success elevation-1">
                        <i class="fas fa-cash-register"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Sale</span>
                        <span class="info-box-number">{{ number_format($saleCount ?? 0) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
