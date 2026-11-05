<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>VietLegal Check</title>
  <style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 11pt; color: #1c1c1c; }
    .cover { page-break-after: always; }
    .cover h1 { font-size: 22pt; margin-top: 40%; }
    .meta { width: 100%; margin-top: 24pt; }
    .meta td { padding: 4pt 0; border-bottom: 0.5pt solid #ccc; }
    .note { background: #f4f1ea; padding: 10pt; border: 0.5pt solid #c8c2b6; margin: 16pt 0; font-size: 9.5pt; }
    .hash { font-family: monospace; font-size: 7.5pt; color: #555; word-break: break-all; margin-top: 18pt; }
    h2 { font-size: 14pt; border-bottom: 1pt solid #333; padding-bottom: 4pt; margin-top: 18pt; }
    h3 { font-size: 12pt; margin-bottom: 4pt; }
    .chip { font-size: 9pt; color: #555; }
    .finding { margin-bottom: 14pt; page-break-inside: avoid; }
    table.basis { width: 100%; border-collapse: collapse; font-size: 9.5pt; }
    table.basis th, table.basis td { border: 0.5pt solid #aaa; padding: 4pt 6pt; text-align: left; }
  </style>
</head>
<body>
  @yield('content')
</body>
</html>
