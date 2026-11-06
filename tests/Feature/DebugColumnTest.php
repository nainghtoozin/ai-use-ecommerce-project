<?php
namespace Tests\Feature;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
class DebugColumnTest extends TestCase {
    use DatabaseTransactions;
    public function test_col() {
        echo "has support_center_enabled: " . (Schema::hasColumn('platform_settings','support_center_enabled') ? 'YES' : 'NO') . "\n";
        echo "has telegram_url: " . (Schema::hasColumn('platform_settings','telegram_url') ? 'YES' : 'NO') . "\n";
        echo "has support_schedule: " . (Schema::hasColumn('platform_settings','support_schedule') ? 'YES' : 'NO') . "\n";
    }
}
