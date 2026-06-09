<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Room Booking</title>
    <script>
        window.__APP_LOCALE__ = @json(app()->getLocale());
        window.__I18N_LOCALES__ = @json(\App\Support\Translations::locales());
        window.__I18N_VERSION__ = @json(\App\Support\Translations::version());
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div id="app"></div>
</body>
</html>
