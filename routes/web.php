<?php
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Route;
Route::view('/', 'welcome');

Route::get('dashboard', function () {
    $url = 'http://ceit-library.test/profile';
    $fileName = 'qrcode.png';
    $filePath = public_path($fileName);
    QrCode::format('png')->size(300)->generate($url, $filePath);
    $qrImageUrl = asset($fileName);
    return view('dashboard', [
        'qrImageUrl' => $qrImageUrl,
        'qrCode' => QrCode::size(300)->generate($url)
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');



Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
