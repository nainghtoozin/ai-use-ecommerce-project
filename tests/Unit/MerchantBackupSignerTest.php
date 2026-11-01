<?php

namespace Tests\Unit;

use App\Services\Export\MerchantBackupSigner;
use RuntimeException;
use Tests\TestCase;

class MerchantBackupSignerTest extends TestCase
{
    private function signer(?string $key = 'unit-test-signing-key-0123456789abcdef'): MerchantBackupSigner
    {
        return new MerchantBackupSigner($key, 'unit-key', 32);
    }

    public function test_signs_and_verifies_a_manifest(): void
    {
        $signer = $this->signer();
        $manifest = [
            'schema_version' => 2,
            'archive_id' => 'archive-1',
            'files' => ['products.csv' => ['sha256' => 'deadbeef']],
            'excluded' => ['media'],
        ];

        $manifest['signature'] = $signer->sign($manifest);

        $this->assertSame('hmac-sha256', $manifest['signature']['algorithm']);
        $this->assertSame('unit-key', $manifest['signature']['key_id']);
        $this->assertSame(64, strlen($manifest['signature']['value']));
        $this->assertTrue($signer->verify($manifest));
    }

    public function test_rejects_tampered_manifest(): void
    {
        $signer = $this->signer();
        $manifest = ['schema_version' => 2, 'total_records' => 10];
        $manifest['signature'] = $signer->sign($manifest);

        $manifest['total_records'] = 11;

        $this->assertFalse($signer->verify($manifest));
    }

    public function test_rejects_missing_and_short_keys(): void
    {
        $manifest = ['schema_version' => 2];
        $configured = $this->signer();
        $manifest['signature'] = $configured->sign($manifest);

        $this->assertFalse($this->signer(null)->verify($manifest));
        $this->assertFalse($this->signer(str_repeat('a', 31))->verify($manifest));

        $this->expectException(RuntimeException::class);
        $this->signer(null)->sign($manifest);
    }

    public function test_canonicalisation_is_key_order_independent(): void
    {
        $signer = $this->signer();
        $manifest = ['b' => 1, 'a' => ['d' => 2, 'c' => 3]];

        $signature = $signer->sign($manifest);

        $reordered = ['a' => ['c' => 3, 'd' => 2], 'b' => 1, 'signature' => $signature];

        $this->assertTrue($signer->verify($reordered));
    }

    public function test_signature_metadata_is_not_part_of_the_signed_payload(): void
    {
        $signer = $this->signer();
        $manifest = ['schema_version' => 2];
        $manifest['signature'] = $signer->sign($manifest);

        $manifest['signature']['signed_at'] = '2000-01-01T00:00:00+00:00';

        $this->assertTrue($signer->verify($manifest));
    }
}
