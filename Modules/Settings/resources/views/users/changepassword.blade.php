<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Retail 360 | Change Password</title>

  <!-- Google Font: Source Sans Pro -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="{{asset('/admin-assets/plugins/fontawesome-free/css/all.min.css')}}">
  <!-- icheck bootstrap -->
  <link rel="stylesheet" href="{{asset('/admin-assets/plugins/icheck-bootstrap/icheck-bootstrap.min.css')}}">
  <!-- Theme style -->
  <link rel="stylesheet" href="{{asset('/admin-assets/dist/css/adminlte.min.css')}}">
</head>
<body class="hold-transition login-page">
<div class="login-box">
  <div class="login-logo">
  <a href="#"><img src="{{tenant_asset('company_logos/'.$company_logo)}}" alt="Logo" class="" style="width: 200px;height:100px" ></a>
  </div>
  <!-- /.login-logo -->
  <div class="card">
    <div class="card-body login-card-body">
      <p class="login-box-msg">You are only one step a way from your new password, change your password now.</p>
        
        <!-- Display success/error messages -->
        @if(session('success'))
          <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if(session('error'))
          <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
      <form action="{{route('users.changepassword')}}" method="post">
          @csrf
        <div class="input-group mb-3">
          <input type="password" name="current_password" id="current_password" class="form-control @error('current_password')is-invalid @enderror" placeholder="Current Password">
          <div class="input-group-append">
            <div class="input-group-text">
              <span class="fas fa-lock"></span>
            </div>
          </div>
        @error('current_password')
                <p class="invalid-feedback">{{$message}}</p>
          @enderror
        </div>
        <div class="input-group mb-3">
          <input type="password" name="new_password" id="new_password" class="form-control @error('new_password')is-invalid @enderror" placeholder="New Password">
          <div class="input-group-append">
            <div class="input-group-text">
              <span class="fas fa-lock"></span>
            </div>
          </div>
        @error('new_password')
                <p class="invalid-feedback">{{$message}}</p>
          @enderror
        </div>
        <div class="input-group mb-3">
          <input type="password" name="new_password_confirmation" id="password_confirmation" class="form-control @error('new_password_confirmation') is-invalid @enderror" placeholder="Confirm Password">
          <div class="input-group-append">
            <div class="input-group-text">
              <span class="fas fa-lock"></span>
            </div>
          </div>
          @error('new_password_confirmation')
            <p class="invalid-feedback">{{ $message }}</p>
         @enderror
        </div>
        <div class="row">
              <div class="col-2">
                      <button type="button" class="btn btn-light" id="togglePassword">
                          <span class="fas fa-eye" id="eyeIcon"></span>
                      </button>
              </div>
              <div class="col-10">
                <button type="submit" class="btn btn-primary btn-block">Change password</button>
          </div>
          <!-- /.col -->
        </div>
      </form>

      <p class="mt-3 mb-1">
  
      </p>
    </div>
    <!-- /.login-card-body -->
  </div>
</div>
<!-- /.login-box -->

<!-- jQuery -->
<script src="{{asset('/admin-assets/plugins/jquery/jquery.min.js')}}"></script>
<!-- Bootstrap 4 -->
<script src="{{asset('/admin-assets/plugins/bootstrap/js/bootstrap.bundle.min.js')}}"></script>
<!-- AdminLTE App -->
<script src="{{asset('/admin-assets/dist/js/adminlte.min.js')}}"></script>
<script>
  document.getElementById('togglePassword').addEventListener('click', function () {
    const passwordInput1 = document.getElementById('current_password');
    const passwordInput2 = document.getElementById('new_password');
    const passwordInput3 = document.getElementById('password_confirmation');
      
    const eyeIcon = document.getElementById('eyeIcon');
    
    // Toggle the password field type between 'password' and 'text'
    if (passwordInput1.type === 'password') {
      passwordInput1.type = 'text';
      passwordInput2.type = 'text';
      passwordInput3.type = 'text';
      eyeIcon.classList.remove('fa-eye');
      eyeIcon.classList.add('fa-eye-slash');
    } else {
      passwordInput1.type = 'password';
      passwordInput2.type = 'password';
      passwordInput3.type = 'password';
      eyeIcon.classList.remove('fa-eye-slash');
      eyeIcon.classList.add('fa-eye');
    }
  });
</script>

</body>
</html>
