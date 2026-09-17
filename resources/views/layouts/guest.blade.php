<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'RUN-ITC')</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    @include('layouts.partials.theme')
</head>
<body class="bg-gray-50 text-gray-800 antialiased">
    <div class="fixed right-4 top-4 z-50">
        @include('layouts.partials.theme-toggle')
    </div>
    @yield('content')
</body>
</html>
