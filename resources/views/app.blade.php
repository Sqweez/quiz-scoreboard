<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="application-name" content="Таблица квиза">
  <title inertia>Таблица квиза</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Spectral:wght@400;500;700&display=swap" rel="stylesheet">
  @vite('resources/js/app.ts')
  @inertiaHead
</head>
<body>
  @inertia
</body>
</html>
