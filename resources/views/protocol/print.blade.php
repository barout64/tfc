<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>{{ $title }}</title>
  <style id="letter-css">@page{size:A4;margin:2cm}body{margin:0;background:#fff}@media print{.letter{padding:0;max-width:none;min-height:calc(297mm - 4cm)}}</style>
</head>
<body>
  <div id="letter"></div>
  <script type="application/json" id="letter-data">{!! json_encode($letter, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
  <script src="{{ asset('protocol-app/render.js') }}?v=20261104"></script>
  <script>
    document.getElementById('letter-css').prepend(Render.LETTER_CSS);
    document.getElementById('letter').innerHTML = Render.renderLetter(JSON.parse(document.getElementById('letter-data').textContent));
    window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });
  </script>
</body>
</html>
