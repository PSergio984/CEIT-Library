<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Traits\CreatesQrCanonicalMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeDownloadController extends Controller
{
    use CreatesQrCanonicalMessage;

    /**
     * Download QR code for a specific inventory item
     */
    public function download(Request $request, int $inventoryId)
    {
        // Ensure user is authenticated
        if (! Auth::check()) {
            abort(401, 'Unauthorized');
        }

        // Get the inventory copy
        $copy = Inventory::with('academicPaper')->find($inventoryId);

        if (! $copy) {
            abort(404, 'Copy not found.');
        }

        if (! $copy->isAvailable()) {
            abort(409, 'This copy is not available.');
        }

        $paper = $copy->academicPaper;

        // Build encrypted payload with TTL
        $payload = [
            'inventory_id' => $copy->id,
            'paper_id' => $paper->id,
            'catalog_code' => $paper->catalog_code,
            'title' => $paper->title,
            'requested_by' => Auth::id(),
            'lat' => Auth::user()->email,
            'iat' => now()->timestamp,
            'exp' => now()->addMinutes(5)->timestamp,
        ];

        // Encrypt the QR payload
        $qrPayload = $this->createEncryptedQrMessage($payload);
        $filename = 'qr-code-inv-' . $copy->id . '.png';

        // Generate and stream the QR code
        return response()->streamDownload(
            fn() => print QrCode::size(500)->format('png')->generate($qrPayload),
            $filename,
            ['Content-Type' => 'image/png']
        );
    }
}
