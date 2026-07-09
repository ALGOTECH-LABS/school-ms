<!DOCTYPE html>
<html lang="en">

<head>

  <title>{{ get_settings('system_name') }} — {{ get_phrase('School Management System') }}</title>
  <!-- all the meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta content="{{ get_settings('system_name') }} — a complete school management &amp; e-learning platform powered by Algotech Labs." name="description" />
  <meta content="school management system, e-learning, student information system, online exams, fees management, nursing school, Algotech Labs" name="keywords" />
  <meta content="Algotech Labs" name="author" />
  <!-- Open Graph / social -->
  <meta property="og:title" content="{{ get_settings('system_name') }} — School Management System" />
  <meta property="og:description" content="A complete school management &amp; e-learning platform powered by Algotech Labs." />
  <meta property="og:type" content="website" />
  <meta property="og:image" content="{{ asset('assets/uploads/logo/'.get_settings('dark_logo')) }}" />
  <meta name="twitter:card" content="summary" />
  <meta name="twitter:title" content="{{ get_settings('system_name') }} — School Management System" />
  <meta name="twitter:description" content="A complete school management &amp; e-learning platform powered by Algotech Labs." />
  <!-- all the css files -->
  <link rel="shortcut icon" href="{{ asset('assets/uploads/logo/'.get_settings('favicon')) }}" />
  <!-- Bootstrap CSS -->
  <link rel="stylesheet" type="text/css" href="{{ asset('assets/vendors/bootstrap-5.1.3/css/bootstrap.min.css') }}">

  <!--Custom css-->
  <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/main.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/style.css') }}">

  <link rel="stylesheet" type="text/css" href="{{ asset('assets/vendors/bootstrap-icons-1.8.1/bootstrap-icons.css') }}">

  <!--Toaster css-->
  <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/toastr.min.css') }}"/>

</head>

<body>

    <div class="container-fluid h-100">
        @yield('content')
    </div>

    @include('external_plugin')

    <!--Main Jquery-->
    <script src="{{ asset('assets/vendors/jquery/jquery-3.6.0.min.js') }}"></script>
    <!--Bootstrap bundle with popper-->
    <script src="{{ asset('assets/vendors/bootstrap-5.1.3/js/bootstrap.bundle.min.js') }}"></script>

    <!--Custom Script-->
    <script src="{{ asset('assets/js/script.js') }}"></script>
    <script src="{{ asset('assets/js/custom.js') }}"></script>

    <!--Toaster Script-->
    <script src="{{ asset('assets/js/toastr.min.js') }}"></script>

    <script>
        
        "use strict";

        @if(Session::has('message'))
        toastr.options =
        {
            "closeButton" : true,
            "progressBar" : true
        }
                toastr.success("{{ session('message') }}");
        @endif

        @if(Session::has('error'))
        toastr.options =
        {
            "closeButton" : true,
            "progressBar" : true
        }
                toastr.error("{{ session('error') }}");
        @endif

        @if(Session::has('info'))
        toastr.options =
        {
            "closeButton" : true,
            "progressBar" : true
        }
                toastr.info("{{ session('info') }}");
        @endif

        @if(Session::has('warning'))
        toastr.options =
        {
            "closeButton" : true,
            "progressBar" : true
        }
                toastr.warning("{{ session('warning') }}");
        @endif
    </script>
</body>
</html>
