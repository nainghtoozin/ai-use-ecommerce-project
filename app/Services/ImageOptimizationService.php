<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Spatie\ImageOptimizer\OptimizerChainFactory;

class ImageOptimizationService
{
    private const FOLDER_WHITELIST = [
        'products',
        'products/gallery',
        'brands',
        'website-settings',
        'platform-settings',
        'profile-images',
        'payment-proofs',
        'payment-evidence',
        'payment-methods',
        'billing-payment-methods',
        'promotions',
    ];

    public function __construct(
        private readonly int $timeout = 60,
    ) {}

    public function optimize(string $fullPath, ?string $folder = null): void
    {
        if (!file_exists($fullPath)) {
            Log::warning('ImageOptimizationService: file not found, skipping', ['path' => $fullPath]);
            return;
        }

        if ($folder !== null && !in_array($folder, self::FOLDER_WHITELIST, true)) {
            Log::info('ImageOptimizationService: folder not whitelisted, skipping', ['folder' => $folder]);
            return;
        }

        $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        if ($extension === 'gif') {
            return;
        }

        try {
            $optimizerChain = OptimizerChainFactory::create();
            $optimizerChain->timeout = $this->timeout;
            $optimizerChain->optimize($fullPath);

            $newSize = filesize($fullPath);
            Log::info('ImageOptimizationService: optimization completed', [
                'path' => $fullPath,
                'size_bytes' => $newSize,
            ]);
        } catch (\Throwable $e) {
            Log::warning('ImageOptimizationService: optimization failed', [
                'path' => $fullPath,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
