<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Laravel</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet" />

        <!-- Styles -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-cover bg-center font-sans relative" style="background-image: url('{{ asset('images/plvbg.jpg') }}');">
        <!-- Blue Overlay for opacity effect -->
        <div class="absolute inset-0 bg-[#273F4F]/80 z-0"></div>
        <!-- Floating Elements -->
        <div class="absolute bg-white/10 rounded-full w-20 h-20 top-1/5 left-1/12 animate-float1 z-10"></div>
        <div class="absolute bg-white/10 rounded-full w-16 h-16 top-3/4 right-1/6 animate-float2 z-10"></div>
        <div class="absolute bg-white/10 rounded-full w-10 h-10 bottom-1/5 left-1/5 animate-float3 z-10"></div>

        <!-- Header -->
        <header class="flex justify-between items-center px-10 py-4 z-20 relative" style="background-color: #273F4F;">
            <a href="/" class="flex items-center text-white text-2xl font-bold hover:opacity-80 transition">
                <div class="w-12 h-12">
                    <img src="{{ asset('images/ceit-logo.png') }}" alt="Description of image">
                </div>
                <span class="ml-2">CEIT Library</span>
            </a>
            <div>
                @if (Route::has('login'))
                    <livewire:welcome.navigation />
                @endif
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex flex-col items-center justify-center min-h-[70vh] text-center relative z-20">
            <div class="bg-[#FE7743]/50 p-10 rounded-2xl shadow-2xl max-w-2xl w-full mx-4">
                <h1 class="text-gray-900 text-4xl md:text-5xl font-bold mb-8 drop-shadow">CEIT Library Management System</h1>
                <div class="mx-auto mb-8 w-48 h-48 flex items-center justify-center rounded-full ">
                    <img src="{{ asset('images/ceit-logo.png') }}" alt="Description of image" class="drop-shadow-xl w-40 h-40 object-contain">
                </div>
                <p class="text-gray-900 text-lg md:text-xl mb-8 font-light drop-shadow">
                    PLV eLib is a digital library system that makes searching<br>
                    and borrowing theses faster, easier, and more secure.
                </p>
            </div>
        </main>



        <!-- Floating Animation Keyframes -->
        <style>
            @keyframes float1 { 0%,100%{transform:translateY(0) rotate(0deg);} 50%{transform:translateY(-20px) rotate(180deg);} }
            @keyframes float2 { 0%,100%{transform:translateY(0) rotate(0deg);} 50%{transform:translateY(-15px) rotate(180deg);} }
            @keyframes float3 { 0%,100%{transform:translateY(0) rotate(0deg);} 50%{transform:translateY(-10px) rotate(180deg);} }
            .animate-float1 { animation: float1 6s ease-in-out infinite; }
            .animate-float2 { animation: float2 7s ease-in-out infinite; }
            .animate-float3 { animation: float3 8s ease-in-out infinite; }
        </style>
    </body>
</html>
