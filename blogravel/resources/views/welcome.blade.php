<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Blogravel') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex items-center justify-center min-h-screen bg-gray-50">
        <div class="text-center">
            <h1 class="text-4xl font-bold text-gray-900">{{ config('app.name', 'Blogravel') }}</h1>
            <p class="mt-2 text-gray-600">A self-hosted blogging platform.</p>
            <a href="/admin" class="inline-block mt-6 px-6 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-800">Admin</a>
        </div>
    </body>
</html>
