<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>TFC Protocol</title>
  <link rel="icon" href="/images/coat-of-arms-sm.png" type="image/png">
  <link rel="stylesheet" href="{{ asset('protocol-app/styles.css') }}?v=20261104">
</head>
<body>
  <div id="app"></div>
  <div id="toasts"></div>
  <script src="{{ asset('protocol-app/render.js') }}?v=20261104"></script>
  <script src="{{ asset('protocol-app/app.js') }}?v=20261104"></script>
</body>
</html>
