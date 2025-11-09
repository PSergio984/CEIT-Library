<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Access Forbidden</title>
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
                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <h1 class="text-6xl font-bold text-error mb-2">403</h1>
                <h2 class="text-2xl font-semibold text-base-content mb-4">Access Forbidden</h2>
            </div>

            <!-- Error Message Card -->
            <div class="card bg-base-200 shadow-xl mb-6">
                <div class="card-body">
                    <div class="flex items-start gap-3 mb-4">
                        <svg class="w-6 h-6 text-error flex-shrink-0 mt-1" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div>
                            <h3 class="font-semibold text-lg mb-2">You don't have permission to access this page</h3>
                            <p class="text-base-content/70 mb-4">
                                {{ $exception->getMessage() ?: 'This action is unauthorized. You may not have the necessary privileges to view this content.' }}
                            </p>
                        </div>
                    </div>

                    <!-- Permission Info -->
                    <div class="divider"></div>

                    <div class="space-y-3">
                        <h4 class="font-semibold flex items-center gap-2">
                            <svg class="w-5 h-5 text-info" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Why am I seeing this?
                        </h4>
                        <ul class="list-disc list-inside space-y-2 text-base-content/70 ml-7">
                            <li>You may not have the required role or permissions</li>
                            <li>This page is restricted to administrators or librarians only</li>
                            <li>Your librarian access may have expired</li>
                            <li>You might be trying to access a feature not available to your account type</li>
                        </ul>
                    </div>

                    <!-- User Role Info -->
                    @auth
                        <div class="divider"></div>
                        <div class="alert alert-info">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <div>
                                <div class="font-semibold">Your Account Type</div>
                                <div class="text-sm">
                                    @if (auth()->user()->isSuperAdmin())
                                        <span class="badge badge-error">Super Administrator</span>
                                    @elseif(auth()->user()->hasLibrarianRole())
                                        <span class="badge badge-primary">Librarian</span>
                                    @elseif(auth()->user()->isLibrarian())
                                        <span class="badge badge-secondary">Active Librarian Duty</span>
                                    @else
                                        <span class="badge badge-ghost">Student</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endauth
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="{{ url()->previous(route('student.dashboard')) }}" class="btn btn-outline btn-primary">
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
                    <a href="{{ route('login') }}" class="btn btn-primary">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                        </svg>
                        Login
                    </a>
                @endauth
            </div>

            <!-- Help Text -->
            <div class="text-center mt-8 text-base-content/60">
                <p class="text-sm">
                    Need access to this page?
                    <a href="mailto:admin@ceitleibrary.com" class="link link-primary">Contact an Administrator</a>
                </p>
            </div>
        </div>
    </div>
</body>

</html>
