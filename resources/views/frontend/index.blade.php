<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ get_settings('system_name') }} — {{ get_phrase('School Management System') }}</title>
    <meta content="{{ get_settings('system_name') }} — a complete school management &amp; e-learning platform powered by Algotech Labs." name="description" />
    <meta content="school management system, e-learning, student information system, online exams, fees management, nursing school, Algotech Labs" name="keywords" />
    <meta content="Algotech Labs" name="author" />
    <meta property="og:title" content="{{ get_settings('system_name') }} — School Management System" />
    <meta property="og:description" content="A complete school management &amp; e-learning platform powered by Algotech Labs." />
    <meta property="og:type" content="website" />
    <meta property="og:image" content="{{ asset('assets/uploads/logo/'.get_settings('dark_logo')) }}" />
    <meta name="twitter:card" content="summary" />

    @include('frontend.include_top')

</head>

<body data-bs-spy="scroll" data-bs-target=".header-area" data-bs-offset="50" tabindex="0">

    @yield('content')

    @include('external_plugin')
    
    @include('frontend.include_buttom')
    
</body>
</html>