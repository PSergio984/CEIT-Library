<?php

namespace Tests\Feature;

use App\Services\AttendanceService;
use App\Services\QrPayloadValidator;
use App\Traits\CreatesQrCanonicalMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

class QrPayloadValidatorTest extends TestCase
{
    use CreatesQrCanonicalMessage, RefreshDatabase;

    private function makeQr(array $overrides = []): string
    {
        $data = array_merge([
            'v' => 7,
            'user_id' => 1,
            'nonce' => Str::random(16),
            'timestamp' => time(),
        ], $overrides);

        $canonicalMessage = $this->createCanonicalMessage($data);
        $data['hash'] = $overrides['hash'] ?? hash_hmac('sha256', $canonicalMessage, config('app.qr_hmac_secret'));

        return json_encode(['encrypted' => Crypt::encryptString(json_encode($data))]);
    }

    public function test_valid_payload_returns_decrypted_data_without_touching_attendance(): void
    {
        $result = app(QrPayloadValidator::class)->validate($this->makeQr());

        $this->assertIsArray($result);
        $this->assertSame(1, $result['user_id']);
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_rejects_missing_wrapper_tampered_hash_and_replayed_nonce(): void
    {
        $validator = app(QrPayloadValidator::class);

        $this->assertSame(QrPayloadValidator::INVALID, $validator->validate('not-a-qr-payload'));
        $this->assertSame(QrPayloadValidator::INVALID, $validator->validate($this->makeQr(['hash' => 'tampered'])));

        $qr = $this->makeQr();
        $this->assertIsArray($validator->validate($qr));
        $this->assertSame(QrPayloadValidator::INVALID, $validator->validate($qr));
    }

    public function test_timestamp_skew_enforced_by_default_but_can_be_relaxed(): void
    {
        $stale = $this->makeQr(['timestamp' => time() - 120]);
        $validator = app(QrPayloadValidator::class);

        $this->assertSame(QrPayloadValidator::INVALID, $validator->validate($stale));
        $this->assertIsArray($validator->validate($stale, enforceTimestampSkew: false));
    }

    public function test_rejects_missing_field_garbage_ciphertext_and_weak_secret(): void
    {
        $validator = app(QrPayloadValidator::class);

        // Missing required field
        $data = json_decode(Crypt::decryptString(json_decode($this->makeQr(), true)['encrypted']), true);
        unset($data['nonce']);
        $this->assertSame(QrPayloadValidator::INVALID, $validator->validate(json_encode(['encrypted' => Crypt::encryptString(json_encode($data))])));

        // Undecryptable ciphertext
        $this->assertSame(QrPayloadValidator::INVALID, $validator->validate(json_encode(['encrypted' => 'garbage'])));

        // Missing or too-short HMAC secret (checked before hash verification)
        $qr = $this->makeQr();
        config(['app.qr_hmac_secret' => 'too-short']);
        $this->assertSame(QrPayloadValidator::INVALID, $validator->validate($qr));
        config(['app.qr_hmac_secret' => null]);
        $this->assertSame(QrPayloadValidator::INVALID, $validator->validate($qr));
    }

    public function test_service_scan_for_missing_user_leaves_nonce_unspent(): void
    {
        $qr = $this->makeQr(['user_id' => 999999, 'nonce' => 'unspent-nonce']);

        // User lookup and rate limit run before the nonce is burned, so a scan
        // that fails at user lookup must not consume it.
        $this->assertSame('invalid', app(AttendanceService::class)->decryptAndValidateAttendanceData($qr));
        $this->assertFalse(Cache::has('qr_nonce:unspent-nonce'));

        // The nonce is still spendable by the real validation path.
        $this->assertIsArray(app(QrPayloadValidator::class)->validate($qr));
    }
}
