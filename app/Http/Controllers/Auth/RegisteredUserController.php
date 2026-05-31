<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use App\Models\Role;

class RegisteredUserController extends Controller
{
    public function create(): \Inertia\Response|\Illuminate\Http\RedirectResponse
    {
        $settings = \App\Models\WebsiteInfo::getSettings();
        if (!$settings->allow_registration) {
            return redirect()->route('login')->with('error', 'Registration is currently disabled.');
        }
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request): RedirectResponse
    {
        $settings = \App\Models\WebsiteInfo::getSettings();
        if (!$settings->allow_registration) {
            return redirect()->route('login')->with('error', 'Registration is currently disabled.');
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $customerRole = Role::firstOrCreate([
            'name' => 'customer',
            'guard_name' => 'web',
            'tenant_id' => \App\Models\Tenant::getCurrent()?->id,
        ]);

        if ($customerRole->wasRecentlyCreated) {
            $globalRole = Role::where('name', 'customer')
                ->whereNull('tenant_id')
                ->first();
            if ($globalRole) {
                $customerRole->syncPermissions($globalRole->permissions);
            }
        }

        $user->assignRole($customerRole);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('client.dashboard', absolute: false));
    }
}
