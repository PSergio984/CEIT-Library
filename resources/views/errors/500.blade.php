<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - Server Error</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-base-100">
    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="max-w-2xl w-full">
            <!-- Error Icon and Code -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-error/10 mb-6">
                    <svg class="w-12 h-12 text-error" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h1 class="text-6xl font-bold text-error mb-2">500</h1>
                <h2 class="text-2xl font-semibold text-base-content mb-4">Internal Server Error</h2>
            </div>

            <!-- Error Message Card -->
            <div class="card bg-base-200 shadow-xl mb-6">
                <div class="card-body text-center">
                    <p class="text-lg text-base-content/70 mb-4">
                        Something went wrong on our end.
                    </p>
                    <p class="text-base-content/60">
                        We're working to fix the problem. Please try again in a few moments.
                    </p>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <button onclick="window.location.reload()" class="btn btn-outline btn-primary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Try Again
                </button>

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

            <!-- Help Text -->
            <div class="text-center mt-8 text-base-content/60">
                <p class="text-sm">
                    If the problem persists, please
                    <a href="mailto:support@ceitleibrary.com" class="link link-primary">contact support</a>
                </p>
            </div>
        </div>
    </div>
</body>

</html>
