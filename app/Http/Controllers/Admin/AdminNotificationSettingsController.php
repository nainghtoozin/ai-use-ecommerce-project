<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\TelegramIntegration;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminNotificationSettingsController extends Controller
{
    public function edit()
    {
        if (!auth()->user()->can('settings.notifications')) {
            abort(403, 'Unauthorized');
        }

        $settings = Setting::pluck('value', 'key')->toArray();
        $telegramIntegration = TelegramIntegration::forCurrentTenant()->first();

        return Inertia::render('Admin/Settings/NotificationSettings', [
            'settings' => [
                'notifications_enabled' => $settings['notifications_enabled'] ?? 'true',
                'notification_orders_enabled' => $settings['notification_orders_enabled'] ?? 'true',
                'notification_customers_enabled' => $settings['notification_customers_enabled'] ?? 'true',
                'notification_inventory_enabled' => $settings['notification_inventory_enabled'] ?? 'true',
                'notification_system_enabled' => $settings['notification_system_enabled'] ?? 'true',
                'item_new_order' => $settings['item_new_order'] ?? 'true',
                'item_order_cancelled' => $settings['item_order_cancelled'] ?? 'true',
                'item_order_status_changed' => $settings['item_order_status_changed'] ?? 'true',
                'item_payment_proof_uploaded' => $settings['item_payment_proof_uploaded'] ?? 'true',
                'item_new_customer' => $settings['item_new_customer'] ?? 'true',
                'item_customer_event' => $settings['item_customer_event'] ?? 'true',
                'item_low_stock' => $settings['item_low_stock'] ?? 'true',
                'item_out_of_stock' => $settings['item_out_of_stock'] ?? 'true',
                'item_system_alert' => $settings['item_system_alert'] ?? 'true',
            ],
            'channels' => [
                'in_app' => true,
                'telegram' => $telegramIntegration ? ($telegramIntegration->isPersonalVerified() || $telegramIntegration->isGroupVerified()) : false,
            ],
        ]);
    }

    public function update(Request $request)
    {
        if (!auth()->user()->can('settings.notifications')) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'notifications_enabled' => 'required',
            'notification_orders_enabled' => 'required',
            'notification_customers_enabled' => 'required',
            'notification_inventory_enabled' => 'required',
            'notification_system_enabled' => 'required',
            'item_new_order' => 'required',
            'item_order_cancelled' => 'required',
            'item_order_status_changed' => 'required',
            'item_payment_proof_uploaded' => 'required',
            'item_new_customer' => 'required',
            'item_customer_event' => 'required',
            'item_low_stock' => 'required',
            'item_out_of_stock' => 'required',
            'item_system_alert' => 'required',
        ]);

        $toBoolean = fn ($v) => filter_var($v, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';

        Setting::set('notifications_enabled', $toBoolean($validated['notifications_enabled']));
        Setting::set('notification_orders_enabled', $toBoolean($validated['notification_orders_enabled']));
        Setting::set('notification_customers_enabled', $toBoolean($validated['notification_customers_enabled']));
        Setting::set('notification_inventory_enabled', $toBoolean($validated['notification_inventory_enabled']));
        Setting::set('notification_system_enabled', $toBoolean($validated['notification_system_enabled']));
        Setting::set('item_new_order', $toBoolean($validated['item_new_order']));
        Setting::set('item_order_cancelled', $toBoolean($validated['item_order_cancelled']));
        Setting::set('item_order_status_changed', $toBoolean($validated['item_order_status_changed']));
        Setting::set('item_payment_proof_uploaded', $toBoolean($validated['item_payment_proof_uploaded']));
        Setting::set('item_new_customer', $toBoolean($validated['item_new_customer']));
        Setting::set('item_customer_event', $toBoolean($validated['item_customer_event']));
        Setting::set('item_low_stock', $toBoolean($validated['item_low_stock']));
        Setting::set('item_out_of_stock', $toBoolean($validated['item_out_of_stock']));
        Setting::set('item_system_alert', $toBoolean($validated['item_system_alert']));

        ActivityLogger::log(
            'Notification settings updated',
            'settings_updated',
            properties: [
                'notifications_enabled' => $toBoolean($validated['notifications_enabled']),
                'notification_orders_enabled' => $toBoolean($validated['notification_orders_enabled']),
                'notification_customers_enabled' => $toBoolean($validated['notification_customers_enabled']),
                'notification_inventory_enabled' => $toBoolean($validated['notification_inventory_enabled']),
                'notification_system_enabled' => $toBoolean($validated['notification_system_enabled']),
            ]
        );

        return admin_redirect('admin.settings.notifications')
            ->with('success', 'Notification settings updated successfully.');
    }
}
