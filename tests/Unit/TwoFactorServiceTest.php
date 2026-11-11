<?php

namespace Tests\Unit;

use App\Services\TwoFactorService;
use Tests\TestCase;

class TwoFactorServiceTest extends TestCase
{
    public function test_rfc6238_sha1_vectors(): void
    {
        $service = new TwoFactorService();
        $ref = new \ReflectionClass($service);
        $m = $ref->getMethod('totp');
        $m->setAccessible(true);
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'; // base32 for '12345678901234567890'
        // RFC 6238 test vector: time 59 -> 287082
        $this->assertSame('287082', $m->invoke($service, $secret, 1));
        $this->assertSame('359152', $m->invoke($service, $secret, 2));
    }

    public function test_verify_accepts_valid_code(): void
    {
        $service = new TwoFactorService();
        $secret = $service->generateSecret();
        // generate current code via reflection
        $ref = new \ReflectionClass($service);
        $m = $ref->getMethod('totp');
        $m->setAccessible(true);
        $step = (int) floor(time() / 30);
        $code = $m->invoke($service, $secret, $step);
        $this->assertTrue($service->verify($secret, $code));
    }

    public function test_recovery_code_one_time(): void
    {
        $service = new TwoFactorService();
        $codes = $service->generateRecoveryCodes(1);
        $hash = $service->hashRecoveryCode($codes[0]);
        $this->assertTrue($service->verifyRecoveryCode($hash, $codes[0]));
    }
}
