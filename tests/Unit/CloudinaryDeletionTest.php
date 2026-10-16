<?php

namespace Tests\Unit;

use App\Services\ImageService;
use Tests\TestCase;

class FakeCloudinaryAdminApi
{
    public array $existing = [];

    public function asset($publicId, $options = [])
    {
        $type = is_array($options) ? ($options['resource_type'] ?? 'image') : 'image';

        if (($this->existing[$publicId] ?? null) === $type) {
            return ['secure_url' => 'https://example.test/' . $publicId];
        }

        throw new \RuntimeException('Resource not found');
    }
}

class FakeCloudinaryUploadApi
{
    public array $destroyQueue = [];

    public int $destroyCalls = 0;

    public function __construct(private readonly FakeCloudinaryAdminApi $admin) {}

    public function destroy($publicId, $options = [])
    {
        $this->destroyCalls++;
        $next = array_shift($this->destroyQueue);

        if ($next instanceof \Throwable) {
            throw $next;
        }

        $result = $next ?? ['result' => 'ok'];

        if (($result['result'] ?? null) === 'ok') {
            unset($this->admin->existing[$publicId]);
        }

        return $result;
    }
}

class FakeCloudinaryClient
{
    public function __construct(
        public readonly FakeCloudinaryAdminApi $admin,
        public readonly FakeCloudinaryUploadApi $upload,
    ) {}

    public function adminApi()
    {
        return $this->admin;
    }

    public function uploadApi()
    {
        return $this->upload;
    }
}

class CloudinaryDeletionTest extends TestCase
{
    private FakeCloudinaryAdminApi $admin;

    private FakeCloudinaryUploadApi $upload;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = new FakeCloudinaryAdminApi();
        $this->upload = new FakeCloudinaryUploadApi($this->admin);

        $client = new FakeCloudinaryClient($this->admin, $this->upload);
        ImageService::$cloudinaryFactory = fn () => $client;
    }

    protected function tearDown(): void
    {
        ImageService::$cloudinaryFactory = null;

        parent::tearDown();
    }

    public function test_resolves_image_type_on_first_hit(): void
    {
        $this->admin->existing['tenants/1/logo'] = 'image';

        $this->assertSame('image', ImageService::cloudinaryResourceType('tenants/1/logo'));
    }

    public function test_falls_through_to_video_then_raw(): void
    {
        $this->admin->existing['tenants/1/clip'] = 'video';
        $this->admin->existing['tenants/1/doc'] = 'raw';

        $this->assertSame('video', ImageService::cloudinaryResourceType('tenants/1/clip'));
        $this->assertSame('raw', ImageService::cloudinaryResourceType('tenants/1/doc'));
    }

    public function test_returns_null_when_asset_absent_everywhere(): void
    {
        $this->assertNull(ImageService::cloudinaryResourceType('tenants/1/missing'));
    }

    public function test_successful_deletion_is_verified(): void
    {
        $this->admin->existing['tenants/1/logo'] = 'image';

        $this->assertTrue(ImageService::deleteCloudinaryPublicId('tenants/1/logo'));
        $this->assertArrayNotHasKey('tenants/1/logo', $this->admin->existing);
        $this->assertSame(1, $this->upload->destroyCalls);
    }

    public function test_already_absent_asset_counts_as_deleted(): void
    {
        $this->assertTrue(ImageService::deleteCloudinaryPublicId('tenants/1/gone'));
        $this->assertSame(0, $this->upload->destroyCalls);
    }

    public function test_surviving_asset_is_not_reported_as_deleted(): void
    {
        $this->admin->existing['tenants/1/stubborn'] = 'image';

        $realAdmin = $this->admin;
        $this->upload = new class($realAdmin) extends FakeCloudinaryUploadApi {
            public function destroy($publicId, $options = [])
            {
                $this->destroyCalls++;

                return ['result' => 'ok'];
            }
        };

        $client = new FakeCloudinaryClient($realAdmin, $this->upload);
        ImageService::$cloudinaryFactory = fn () => $client;

        $this->assertFalse(ImageService::deleteCloudinaryPublicId('tenants/1/stubborn'));
    }

    public function test_transient_failure_retries_then_succeeds(): void
    {
        $this->admin->existing['tenants/1/flaky'] = 'image';
        $this->upload->destroyQueue[] = new \RuntimeException('cURL error 35: SSL connect reset');
        $this->upload->destroyQueue[] = ['result' => 'ok'];

        $this->assertTrue(ImageService::deleteCloudinaryPublicId('tenants/1/flaky'));
        $this->assertSame(2, $this->upload->destroyCalls);
    }

    public function test_persistent_failure_gives_up_after_three_attempts(): void
    {
        $this->admin->existing['tenants/1/doomed'] = 'image';
        $this->upload->destroyQueue[] = new \RuntimeException('cURL error 35: SSL connect reset');
        $this->upload->destroyQueue[] = new \RuntimeException('cURL error 35: SSL connect reset');
        $this->upload->destroyQueue[] = new \RuntimeException('cURL error 35: SSL connect reset');
        $this->upload->destroyQueue[] = ['result' => 'ok'];

        $this->assertFalse(ImageService::deleteCloudinaryPublicId('tenants/1/doomed'));
        $this->assertSame(3, $this->upload->destroyCalls);
    }

    public function test_non_transient_failure_does_not_retry(): void
    {
        $this->admin->existing['tenants/1/locked'] = 'image';
        $this->upload->destroyQueue[] = new \RuntimeException('Invalid credentials (401)');
        $this->upload->destroyQueue[] = ['result' => 'ok'];

        $this->assertFalse(ImageService::deleteCloudinaryPublicId('tenants/1/locked'));
        $this->assertSame(1, $this->upload->destroyCalls);
    }

    public function test_unavailable_sdk_reports_failure(): void
    {
        ImageService::$cloudinaryFactory = fn () => throw new \RuntimeException('nope');

        $this->assertFalse(ImageService::deleteCloudinaryPublicId('tenants/1/any'));
    }
}
