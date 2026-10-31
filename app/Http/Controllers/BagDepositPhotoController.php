<?php

namespace App\Http\Controllers;

use App\Models\BagDeposit;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BagDepositPhotoController extends Controller
{
    /**
     * Serve a deposit's item photo from the private local disk. The route
     * lives inside the librarian.or.admin group, so only counter staff can
     * reach it — the file itself has no public URL.
     */
    public function __invoke(BagDeposit $bagDeposit): StreamedResponse
    {
        $disk = Storage::disk('local');

        abort_unless($bagDeposit->photo_path && $disk->exists($bagDeposit->photo_path), 404);

        return $disk->response($bagDeposit->photo_path);
    }
}
