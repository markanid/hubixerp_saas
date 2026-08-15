 <!-- Main Sidebar Container -->
 <aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="../../index3.html" class="brand-link">
      <img src="{{asset('/admin/dist/img/AdminLTELogo.png')}}" alt="AdminLTE Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
      <span class="brand-text font-weight-light">Admin Panel</span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
      <!-- Sidebar user (optional) -->
      <div class="user-panel mt-3 pb-3 mb-3 d-flex">
        <div class="image">
          <img src="{{asset('/dist/img/user2-160x160.jpg')}}" class="img-circle elevation-2" alt="User Image">
        </div>  
      </div>


      <!-- Sidebar Menu -->
      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
          <!-- Add icons to the links using the .nav-icon class
               with font-awesome or any other icon font library -->
          <li class="nav-item">
            <a href="" class="nav-link">
              <i class="nav-icon fas fa-tachometer-alt"></i>
              <p>
                Dashboard
                
              </p>
            </a>
          </li>
          <li class="nav-item">
            <a href="{{ route('category.index') }}" class="nav-link">
              <i class="nav-icon fas fa-project-diagram"></i>
              <p>
                Category
                
              </p>
            </a>
          </li>
          <li class="nav-item">
            <a href="{{ route('categorydetail.index') }}" class="nav-link">
              <i class="nav-icon fas fa-tasks"></i>
              <p>
                Category Details
                
              </p>
            </a>
          </li>
          <li class="nav-item">
            <a href="{{ route('services.index') }}" class="nav-link">
              <i class="nav-icon fas fa-sitemap"></i>
              <p>
                Services
                
              </p>
            </a>
          </li>
          <li class="nav-item">
            <a href="{{ route('about.create') }}" class="nav-link">
              <i class="nav-icon fas fa-building"></i>
              <p>
                About Us
                
              </p>
            </a>
          </li>
          <li class="nav-item">
            <a href="{{ route('brand.index') }}" class="nav-link">
              <i class="nav-icon fas fa-thumbtack"></i>
              <p>
                Brands
                
              </p>
            </a>
          </li>
          <li class="nav-item">
            <a href="{{ route('contact.create') }}" class="nav-link">
              <i class="nav-icon fas fa-address-card"></i>
              <p>
                Contact Us
               
              </p>
            </a>
          </li>
          
        </ul>
      </nav>
      <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
  </aside>
