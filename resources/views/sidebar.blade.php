@php
    use Modules\Settings\app\Models\Company;
    session()->forget('company_logo_url');
    $company = Company::first();
    if ($company && $company->logo) {
        $logoUrl = tenant_asset('company_logos/' . $company->logo);
        session(['company_logo_url' => $logoUrl]);
    } else {
        $logoUrl = asset('uploads/default_company_logo.png');
        session(['company_logo_url' => null]); // or set a default image path
    }

@endphp

<aside class="main-sidebar elevation-4 sidebar-light-navy">
    <!-- Brand Logo -->
    <a href="#" class="brand-link">
        <img src="{{asset('admin-assets/dist/img/logo-tp.png')}}" alt="Logo" class="brand-image" style="width: 230px; height: 35px; display: block; margin: -1.5px 0; padding: 1px;">
        <span class="brand-text font-weight-light"></span>
    </a>

    <div class="sidebar">
        <!-- <div class="user-panel mt-3 pb-3 mb-3 d-flex">
        <div class="image">
          <img src="{{asset('admin-assets/dist/img/avatar3.png')}}" class="img-circle elevation-2" alt="User Image" style="width: 33.6px; height: 33.6px;">
        </div>
        <div class="info">
          <a href="#" class="d-block">Hello, Administrator</a>
        </div>
      </div> -->
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column nav-child-indent" data-widget="treeview" role="menu" data-accordion="false">

                <li class="nav-item">
                    <a href="{{ route('profile.dashboard') }}" class="nav-link {{ menuActive(['profile.dashboard'], 'active') }}">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>Dashboard</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="{{ route('products.index') }}" class="nav-link {{ menuActive(['products.index', 'products.create', 'products.edit', 'products.show'], 'active') }}">
                        <i class="nav-icon fas fa-boxes"></i>
                        <p>Products</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="{{ route('vendors.index') }}" class="nav-link {{ menuActive(['vendors.index', 'vendors.create', 'vendors.edit', 'vendors.show'], 'active') }}">
                        <i class="nav-icon fas fa-user-tie"></i>
                        <p>Suppliers</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="{{ route('customers.index') }}" class="nav-link {{ menuActive(['customers.index', 'customers.create', 'customers.edit', 'customers.show'], 'active') }}">
                        <i class="nav-icon fas fa-user-tag"></i>
                        <p>Customers</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="{{ route('purchases.index') }}" class="nav-link {{ menuActive(['purchases.index', 'purchases.create', 'purchases.edit', 'purchases.show'], 'active') }}">
                        <i class="nav-icon fas fa-cart-arrow-down"></i>
                        <p>Purchases</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="{{ route('sales.index') }}" class="nav-link {{ menuActive(['sales.index', 'sales.create', 'sales.edit', 'sales.show'], 'active') }}">
                        <i class="nav-icon fas fa-cash-register"></i>
                        <p>Sales</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="{{ route('estimations.index') }}" class="nav-link {{ menuActive(['estimations.index', 'estimations.create', 'estimations.edit', 'estimations.show'], 'active') }}">
                        <i class="nav-icon fa-solid fa-receipt"></i>
                        <p>Estimation</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="{{ route('services.index') }}" class="nav-link {{ menuActive(['services.index', 'services.create', 'services.edit', 'services.show'], 'active') }}">
                        <i class="nav-icon fas fa-wrench"></i>
                        <p>Services</p>
                    </a>
                </li>

                 <!-- Returns -->
                <li class="nav-item {{ menuActive(['purchase-returns.index', 'purchase-returns.create', 'purchase-returns.edit', 'purchase-returns.show', 'sales-returns.index', 'sales-returns.create', 'sales-returns.edit', 'sales-returns.show'], 'menu-open') }}">
                    <a href="#" class="nav-link {{ menuActive(['purchase-returns.index', 'purchase-returns.create', 'purchase-returns.edit', 'purchase-returns.show', 'sales-returns.index', 'sales-returns.create', 'sales-returns.edit', 'sales-returns.show'], 'active') }}">
                        <i class="nav-icon fa fa-undo-alt"></i>
                        <p>Return <i class="fas fa-angle-left right"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('purchase-returns.index') }}" class="nav-link {{ menuActive(['purchase-returns.index', 'purchase-returns.create', 'purchase-returns.edit', 'purchase-returns.show'], 'active') }}">
                                <i class="nav-icon far fa-file"></i>
                                <p>Purchase Return</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('sales-returns.index') }}" class="nav-link {{ menuActive(['sales-returns.index', 'sales-returns.create', 'sales-returns.edit', 'sales-returns.show'], 'active') }}">
                                <i class="nav-icon far fa-file-alt"></i>
                                <p>Sale Return</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a href="{{ route('expenses.index') }}" class="nav-link {{ menuActive(['expenses.index', 'expenses.create', 'expenses.edit', 'expenses.show'], 'active') }}">
                        <i class="nav-icon fas fa-receipt"></i>
                        <p>Expenses</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="{{ route('consumptions.index') }}" class="nav-link {{ menuActive(['consumptions.index', 'consumptions.create', 'consumptions.edit', 'consumptions.show'], 'active') }}">
                        <i class="nav-icon fas fa-coffee"></i>
                        <p>Consumptions</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="{{ route('banks.index') }}" class="nav-link {{ menuActive(['banks.index', 'banks.create', 'banks.edit', 'banks.show'], 'active') }}">
                        <i class="nav-icon fas fa-landmark"></i>
                        <p>Banking</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="{{ route('loans.index') }}" class="nav-link {{ menuActive(['loans.index', 'loans.create', 'loans.show'], 'active') }}">
                        <i class="nav-icon fas fa-hand-holding-usd"></i>
                        <p>Loan</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="{{ route('reports.center') }}"
                    class="nav-link {{ request()->routeIs('reports.*') || request()->routeIs('dailyreports.*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-chart-bar"></i>
                        <p>Reports Center</p>
                    </a>
                </li>
                @if((\Modules\Settings\app\Models\Company::query()->value('inventory_mode') ?? 'standard') === 'batch')
                <li class="nav-item {{ request()->routeIs('batch-reports.*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->routeIs('batch-reports.*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-pills"></i><p>Batch Reports <i class="fas fa-angle-left right"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        @foreach([
                            'stock' => 'Batch Stock',
                            'near-expiry' => 'Near Expiry',
                            'expired' => 'Expired Stock',
                            'movements' => 'Batch Movement',
                            'ledger' => 'Batch Ledger',
                            'profitability' => 'Batch Profitability',
                        ] as $type => $label)
                        <li class="nav-item"><a href="{{ route('batch-reports.index', $type) }}" class="nav-link">
                            <i class="far fa-circle nav-icon"></i><p>{{ $label }}
                            @if($type === 'near-expiry')
                                @php
                                    $alertDays = (int) (\Modules\Settings\app\Models\Company::query()->value('expiry_alert_days') ?? 30);
                                    $expiryCount = \Modules\Product\app\Models\StockBatch::where('available_quantity', '>', 0)
                                        ->whereBetween('expiry_date', [today(), today()->addDays($alertDays)])->count();
                                @endphp
                                @if($expiryCount)<span class="badge badge-warning right">{{ $expiryCount }}</span>@endif
                            @endif
                            </p>
                        </a></li>
                        @endforeach
                    </ul>
                </li>
                @endif
                @if((\Modules\Settings\app\Models\Company::query()->value('inventory_mode') ?? 'standard') === 'mrp')
                <li class="nav-item {{ request()->routeIs('mrp-reports.*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->routeIs('mrp-reports.*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-tags"></i><p>MRP Stock Reports <i class="fas fa-angle-left right"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        @foreach([
                            'stock' => 'MRP-wise Stock',
                            'movements' => 'MRP Movements',
                            'profitability' => 'MRP Profitability',
                        ] as $type => $label)
                        <li class="nav-item"><a href="{{ route('mrp-reports.index', $type) }}" class="nav-link">
                            <i class="far fa-circle nav-icon"></i><p>{{ $label }}</p>
                        </a></li>
                        @endforeach
                    </ul>
                </li>
                @endif

                <!-- Master Menu -->
                <li class="nav-item {{ menuActive(['brands.index', 'brands.create', 'brands.edit', 'brands.show', 'groups.index', 'groups.create', 'groups.edit', 'subcategory.index', 'subcategory.create', 'subcategory.edit', 'category.index', 'category.create', 'category.edit', 'excategory.index', 'excategory.create', 'excategory.edit', 'counters.index', 'counters.create', 'counters.edit', 'counters.show'], 'menu-open') }}">
                    <a href="#" class="nav-link {{ menuActive(['brands.index', 'brands.create', 'brands.edit', 'brands.show', 'groups.index', 'groups.create', 'groups.edit', 'subcategory.index', 'subcategory.create', 'subcategory.edit', 'category.index', 'category.create', 'category.edit', 'excategory.index', 'excategory.create', 'excategory.edit', 'counters.index', 'counters.create', 'counters.edit', 'counters.show'], 'active') }}">
                        <i class="nav-icon far fa-newspaper"></i>
                        <p>Master <i class="fas fa-angle-left right"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <!-- Product Category -->
                        <li class="nav-item {{ menuActive(['category.index', 'category.create', 'category.edit', 'subcategory.index', 'subcategory.create', 'subcategory.edit','brands.index', 'brands.create', 'brands.edit', 'brands.show','groups.index', 'groups.create', 'groups.edit'], 'menu-open') }}">
                            <a href="#" class="nav-link {{ menuActive(['category.index', 'category.create', 'category.edit', 'subcategory.index', 'subcategory.create', 'subcategory.edit','brands.index', 'brands.create', 'brands.edit', 'brands.show','groups.index', 'groups.create', 'groups.edit'], 'active') }}">
                                <i class="fas fa-shopping-basket nav-icon"></i>
                                <p>Product Category <i class="fas fa-angle-left right"></i></p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <a href="{{ route('category.index') }}" class="nav-link {{ menuActive(['category.index', 'category.create', 'category.edit'], 'active') }}">
                                        <i class="fas fa-tasks nav-icon"></i>
                                        <p>Manage Category</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ route('subcategory.index') }}" class="nav-link {{ menuActive(['subcategory.index', 'subcategory.create', 'subcategory.edit', 'subcategory.show'], 'active') }}">
                                        <i class="fas fa-tags nav-icon"></i>
                                        <p>Manage Sub Category</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ route('brands.index') }}" class="nav-link {{ menuActive(['brands.index', 'brands.create', 'brands.edit', 'brands.show'], 'active') }}">
                                        <i class="nav-icon far fa-star"></i>
                                        <p>Brand</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="{{ route('groups.index') }}" class="nav-link {{ menuActive(['groups.index', 'groups.create', 'groups.edit'], 'active') }}">
                                        <i class="far fa-object-group nav-icon"></i>
                                        <p>Manage Groups</p>
                                    </a>
                                </li>
                            </ul>
                        </li>

                        <li class="nav-item">
                            <a href="{{ route('excategory.index') }}" class="nav-link {{ menuActive(['excategory.index', 'excategory.create', 'excategory.edit'], 'active') }}">
                                <i class="nav-icon fas fa-wallet"></i>
                                <p>Manage Ex-Category</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('counters.index') }}" class="nav-link {{ menuActive(['counters.index', 'counters.create', 'counters.edit', 'counters.show'], 'active') }}">
                                <i class="nav-icon fas fa-cash-register"></i>
                                <p>POS Counters</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Settings -->
                <li class="nav-item {{ menuActive(['company.index', 'company.edit', 'company.settings', 'users.index', 'users.add', 'users.edit', 'users.show'], 'menu-open') }}">
                    <a href="#" class="nav-link {{ menuActive(['company.index', 'company.edit', 'company.settings', 'users.index', 'users.add', 'users.edit', 'users.show'], 'active') }}">
                        <i class="nav-icon fas fa-cogs"></i>
                        <p>Settings <i class="fas fa-angle-left right"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('users.index') }}" class="nav-link {{ menuActive(['users.index', 'users.add', 'users.edit', 'users.show'], 'active') }}">
                                <i class="nav-icon fas fa-user-cog"></i>
                                <p>Users</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('company.index') }}" class="nav-link {{ menuActive(['company.index', 'company.edit', 'company.settings'], 'active') }}">
                                <i class="nav-icon far fa-building"></i>
                                <p>Company</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Utility -->
                <li class="nav-item {{ menuActive(['profile.dbbackup', 'profile.restore'], 'menu-open') }}">
                    <a href="#" class="nav-link {{ menuActive(['dbbackup', 'restore'], 'active') }}">
                        <i class="nav-icon fas fa-spinner"></i>
                        <p>Utility <i class="fas fa-angle-left right"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('profile.dbbackup') }}" class="nav-link {{ menuActive(['dbbackup'], 'active') }}">
                                <i class="nav-icon fas fa-database"></i>
                                <p>Back Up</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('profile.restore') }}" class="nav-link {{ menuActive(['profile.restore'], 'active') }}">
                                <i class="nav-icon far fa-window-restore"></i>
                                <p>Restore</p>
                            </a>
                        </li>
                    </ul>
                </li>

            </ul>
        </nav>
    </div>
</aside>
