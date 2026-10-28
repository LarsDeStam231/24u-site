<!DOCTYPE html>
<html lang="nl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#080d13">
        <meta name="description" content="{{ $pageData['page']['description'] }}">
        <title>{{ $pageData['page']['documentTitle'] }}</title>
        <link rel="icon" href="{{ asset('assets/event/favicon.svg') }}" type="image/svg+xml">
        <link rel="canonical" href="{{ $pageData['page']['canonical'] }}">
        <script>window.eventPageData = {{ Illuminate\Support\Js::from($pageData) }};</script>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div id="app"></div>
        <noscript>
            <h1>{{ $pageData['page']['heading'] }}</h1>
            <p>{{ $pageData['page']['description'] }}</p>
            <p>Schakel JavaScript in om alle onderdelen van de website te bekijken.</p>
            <nav aria-label="Paginanavigatie">
                @foreach ($pageData['navigation'] as $item)
                    <a href="{{ $item['href'] }}">{{ $item['label'] }}</a>
                @endforeach
                @foreach ($pageData['accountNavigation'] as $item)
                    <a href="{{ $item['href'] }}">{{ $item['label'] }}</a>
                @endforeach
            </nav>
        </noscript>
    </body>
</html>
