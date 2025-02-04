@php use Illuminate\Support\Facades\File;use Illuminate\Support\Str; @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>
        Remotisan
    </title>

    @php($files = collect(File::allFiles(base_path() . "/vendor/paymeservice/remotisan/dist/assets"))->keyBy(fn($path) => Str::afterLast($path, ".")))
    <style>{!! File::get($files->get("css")) !!}</style>
</head>

<body class="bg-gray-50">
    <header class="bg-indigo-600 shadow">
        <div class="container mx-auto px-4 py-2 flex items-end">
            <h1 class="text-white text-3xl font-bold">Remotisan</h1>
            <p class="ml-4 text-gray-300">Manage your remote commands with ease.</p>
        </div>
    </header>
    <main class="container mx-auto px-4 py-2">
        <section id="react-root">
            <!-- React components from resources/react/components will be rendered here -->
        </section>
    </main>
    <footer class="bg-gray-200">
        <div class="container mx-auto text-center p-4 text-gray-700">
            &copy; 2023 Remotisan. All rights reserved.
        </div>
    </footer>
</body>
<script>
        window.remotisanBaseUrl = "{{ config('remotisan.url') }}";
</script>
<script >{!! File::get($files->get("js")) !!}</script>
</html>
