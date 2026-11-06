<?php
namespace Tests\Feature;
use App\Http\Controllers\SuperAdmin\SuperAdminSupportCenterController;
use App\Models\PlatformSetting;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;
class DebugSupportPersistTest extends TestCase {
    use DatabaseTransactions;
    public function test_persist() {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name'=>'superadmin','guard_name'=>'web']);
        $user = \App\Models\User::firstOrCreate(['email'=>'super@admin.com'], ['name'=>'Super Admin','password'=>bcrypt('password'),'status'=>'active','email_verified_at'=>now()]);
        $user->assignRole('superadmin');
        $this->actingAs($user);
        $settings = PlatformSetting::firstOrCreate(['site_name'=>'My Application']);
        PlatformSetting::clearCache();

        $req = Request::create('/superadmin/support-center', 'POST', [
            'telegram_url' => 'https://t.me/example_support',
            'whatsapp_url' => 'https://wa.me/959123456789',
            'messenger_url' => 'https://m.me/examplepage',
            'platform_timezone' => 'Asia/Yangon',
        ]);
        $req->setUserResolver(fn() => $user);

        $controller = app(SuperAdminSupportCenterController::class);
        $resp = $controller->update($req);
        echo "RESPONSE=" . get_class($resp) . "\n";

        PlatformSetting::clearCache();
        $fresh = PlatformSetting::find($settings->id);
        echo "telegram_url=" . ($fresh->telegram_url ?? 'NULL') . "\n";
        echo "whatsapp_url=" . ($fresh->whatsapp_url ?? 'NULL') . "\n";
        echo "messenger_url=" . ($fresh->messenger_url ?? 'NULL') . "\n";
    }
}
