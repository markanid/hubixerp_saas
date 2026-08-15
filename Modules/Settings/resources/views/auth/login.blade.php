<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>HubixERP | Log in</title>

  <!-- Google Font: Source Sans Pro -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="{{asset('admin-assets/plugins/fontawesome-free/css/all.min.css')}}">
  <!-- icheck bootstrap -->
  <link rel="stylesheet" href="{{asset('admin-assets/plugins/icheck-bootstrap/icheck-bootstrap.min.css')}}">
  <!-- Theme style -->
  <link rel="stylesheet" href="{{asset('admin-assets/dist/css/adminlte.min.css')}}">
  <link rel="shortcut icon" type="image/x-icon" href="{{asset('admin-assets/dist/img/favicon.png')}}">
</head>
<body class="hold-transition login-page">
<div class="login-box">
 
  <!-- /.login-logo -->
  <div class="card">
    <div class="card-body login-card-body">
        <div class="login-logo">
            <a href="#"><img src="{{asset('admin-assets/dist/img/logo-tp.png')}}" alt="Logo" class="" style="width: 261px;height:35px" ></a>
        </div>
        @if(session('error'))
            <div class="text-danger text-center">{{session('error')}}</div>
        @endif
        @if(session('success'))
            <div class="text-success text-center">{{session('success')}}</div>
        @endif
      <p class="login-box-msg">Sign in to start your session</p>
      
      <form action="{{route('auth.authenticate')}}" method="post">
        @csrf
        <div class="input-group mb-1">
          <input type="email" name="email" class="form-control" placeholder="Email">
          <div class="input-group-append">
            <div class="input-group-text">
              <span class="fas fa-envelope"></span>
            </div>
          </div>
        </div>
        @error('email')
            <div class="text-danger">{{$message}}</div> 
        @enderror
        <div class="input-group mb-1">
          <input type="password" name="password" id="password" class="form-control" placeholder="Password">
          <div class="input-group-append">
            <div class="input-group-text">
                <span class="fas fa-eye" id="eyeIcon"></span>
            </div>
          </div>
        </div>
        @error('password')
            <div class="text-danger">{{$message}}</div> 
        @enderror
        <div class="form-group mb-3">
            <select name="financial_year" class="form-control" required>
                <option value="">Select Financial Year</option>
                @foreach($financialYears as $year)
                    <option value="{{ $year }}" {{ old('financial_year', $currentFinancialYear) == $year ? 'selected' : '' }}>{{ $year }}</option>
                @endforeach
            </select>
        </div>
        @error('financial_year')
            <div class="text-danger">{{ $message }}</div>
        @enderror
        <div class="row">
          
          <!-- /.col -->
          <div class="col-12">
            <button type="submit" class="btn btn-primary btn-flat btn-block">Sign In</button>
          </div>
          <!-- /.col -->
        </div>
      </form>
        
    </div>
        
    <!-- /.login-card-body -->
  </div>
   <div style="color:#3ab6ff;" class=" text-center">
              Powered by 
            <a style="color:#000;" href="https://apexsoftlabs.com">Apex Soft Labs</a>
            
          </div>
</div>
<!-- /.login-box -->

<!-- jQuery -->
<script src="{{asset('admin-assets/plugins/jquery/jquery.min.js')}}"></script>
<!-- Bootstrap 4 -->
<script src="{{asset('admin-assets/plugins/bootstrap/js/bootstrap.bundle.min.js')}}"></script>
<!-- AdminLTE App -->
<script src="{{asset('admin-assets/dist/js/adminlte.min.js')}}"></script>
<script>
  document.getElementById('eyeIcon').addEventListener('click', function () {
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');
    
    // Toggle the password field type between 'password' and 'text'
    if (passwordInput.type === 'password') {
      passwordInput.type = 'text';
      eyeIcon.classList.remove('fa-eye');
      eyeIcon.classList.add('fa-eye-slash');
    } else {
      passwordInput.type = 'password';
      eyeIcon.classList.remove('fa-eye-slash');
      eyeIcon.classList.add('fa-eye');
    }
  });
</script>

</body>
</html>
