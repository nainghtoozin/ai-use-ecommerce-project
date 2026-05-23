<?php

namespace App\Console\Commands;

use App\Models\WebsiteInfo;
use Illuminate\Console\Command;

class NormalizeHeroImagePaths extends Command
{
    protected $signature = 'app:normalize-hero-images';
    protected $description = 'Normalize hero image paths to relative format in the database';

    public function handle(): int
    {
        $appStorageUrl = rtrim(config('app.url'), '/') . '/storage/';
        $infos = WebsiteInfo::all();
        $totalFixed = 0;

        foreach ($infos as $info) {
            $images = $info->hero_images ?? [];

            if (empty($images)) {
                continue;
            }

            $changed = false;
            $normalized = [];

            foreach ($images as $path) {
                if (empty($path)) {
                    continue;
                }

                $original = $path;

                if (str_starts_with($path, $appStorageUrl)) {
                    $path = substr($path, strlen($appStorageUrl));
                } elseif (str_starts_with($path, '/storage/')) {
                    $path = substr($path, 9);
                }

                $normalized[] = $path;

                if ($path !== $original) {
                    $changed = true;
                }
            }

            if ($changed) {
                $info->hero_images = $normalized;
                $info->save();
                $totalFixed += count($normalized);
                $this->line("Fixed hero images for website info ID: {$info->id}");
            }
        }

        if ($totalFixed > 0) {
            $this->info("Normalized {$totalFixed} hero image paths.");
        } else {
            $this->info('All hero image paths are already normalized.');
        }

        return Command::SUCCESS;
    }
}
