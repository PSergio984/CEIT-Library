<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-base-100">
    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="max-w-2xl w-full">
            <!-- Error Icon and Code -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-warning/10 mb-6">
                    <svg class="w-12 h-12 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h1 class="text-6xl font-bold text-warning mb-2">404</h1>
                <h2 class="text-2xl font-semibold text-base-content mb-4">Page Not Found</h2>
            </div>

            <!-- Error Message Card -->
            <div class="card bg-base-200 shadow-xl mb-6">
                <div class="card-body text-center">
                    <p class="text-lg text-base-content/70 mb-4">
                        Oops! We couldn't find the page you're looking for.
                    </p>
                    <p class="text-base-content/60">
                        The page may have been moved, deleted, or never existed in the first place.
                    </p>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="{{ url()->previous() }}" class="btn btn-outline btn-primary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Go Back
                </a>

                @auth
                    <a href="{{ route('student.dashboard') }}" class="btn btn-primary">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        Go to Dashboard
                    </a>
                @else
                    <a href="{{ url('/') }}" class="btn btn-primary">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        Go Home
                    </a>
                @endauth
            </div>
        </div>
    </div>
</body>

</html>
