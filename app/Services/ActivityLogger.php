<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Str;

class ActivityLogger
{
    public static function log(
        string $description,
        string $event,
        mixed $subject = null,
        array $properties = [],
        ?string $logName = null,
        ?string $category = null,
        ?string $severity = null
    ): ActivityLog {
        $impersonatorId = session('impersonator_id');
        $isImpersonating = $impersonatorId && auth()->check() && $impersonatorId !== auth()->id();

        // Auto-resolve category from event if not provided
        if (!$category) {
            $category = static::resolveCategory($event, $logName);
        }

        // Auto-resolve severity from event if not provided
        if (!$severity) {
            $severity = static::resolveSeverity($event);
        }

        return ActivityLog::create([
            'log_name' => $logName ?? $event,
            'category' => $category,
            'severity' => $severity,
            'description' => $description,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->getKey(),
            'causer_type' => auth()->user() ? get_class(auth()->user()) : null,
            'causer_id' => $isImpersonating ? $impersonatorId : auth()->id(),
            'impersonator_id' => $isImpersonating ? $impersonatorId : null,
            'impersonated_user_id' => $isImpersonating ? auth()->id() : null,
            'properties' => $properties,
            'event' => $event,
            'batch_uuid' => (string) Str::uuid(),
        ]);
    }

    protected static function resolveCategory(string $event, ?string $logName): string
    {
        $authEvents = ['login', 'logout', 'password_reset', 'email_verified', 'registered'];
        $securityEvents = ['suspended', 'banned', 'activated', 'locked', 'unlocked', 'impersonation_started', 'impersonation_ended'];
        $orderEvents = ['order_created', 'order_status_changed', 'order_cancelled', 'order_completed'];
        $paymentEvents = ['payment_verified', 'payment_rejected', 'payment_proof_uploaded', 'payment_refunded'];
        $productEvents = ['product_created', 'product_updated', 'product_deleted', 'product_bulk_deleted'];
        $inventoryEvents = ['stock_adjusted', 'stock_transferred', 'low_stock_alert'];
        $userEvents = ['user_created', 'user_updated', 'user_deleted', 'role_assigned', 'role_removed'];
        $settingsEvents = ['settings_updated', 'website_updated', 'payment_method_updated'];
        $billingEvents = ['subscription_created', 'subscription_renewed', 'subscription_expired', 'plan_changed', 'invoice_generated'];

        if (in_array($event, $authEvents) || $logName === 'auth') {
            return ActivityLog::CATEGORY_AUTH;
        }
        if (in_array($event, $securityEvents)) {
            return ActivityLog::CATEGORY_SECURITY;
        }
        if (in_array($event, $orderEvents) || $logName === 'order') {
            return ActivityLog::CATEGORY_ORDERS;
        }
        if (in_array($event, $paymentEvents) || $logName === 'payment') {
            return ActivityLog::CATEGORY_BILLING;
        }
        if (in_array($event, $productEvents) || $logName === 'product') {
            return ActivityLog::CATEGORY_PRODUCTS;
        }
        if (in_array($event, $inventoryEvents) || $logName === 'inventory') {
            return ActivityLog::CATEGORY_INVENTORY;
        }
        if (in_array($event, $userEvents) || $logName === 'user') {
            return ActivityLog::CATEGORY_USERS;
        }
        if (in_array($event, $settingsEvents) || $logName === 'settings') {
            return ActivityLog::CATEGORY_SETTINGS;
        }
        if (in_array($event, $billingEvents) || $logName === 'billing') {
            return ActivityLog::CATEGORY_BILLING;
        }

        return ActivityLog::CATEGORY_SYSTEM;
    }

    protected static function resolveSeverity(string $event): string
    {
        $successEvents = ['login', 'registered', 'email_verified', 'activated', 'payment_verified', 'order_completed', 'subscription_renewed'];
        $warningEvents = ['suspended', 'payment_rejected', 'low_stock_alert', 'subscription_expired', 'order_cancelled'];
        $errorEvents = ['banned', 'locked', 'payment_failed'];
        $criticalEvents = ['account_deleted', 'data_breach', 'security_violation'];

        if (in_array($event, $successEvents)) {
            return ActivityLog::SEVERITY_SUCCESS;
        }
        if (in_array($event, $warningEvents)) {
            return ActivityLog::SEVERITY_WARNING;
        }
        if (in_array($event, $errorEvents)) {
            return ActivityLog::SEVERITY_ERROR;
        }
        if (in_array($event, $criticalEvents)) {
            return ActivityLog::SEVERITY_CRITICAL;
        }

        return ActivityLog::SEVERITY_INFO;
    }
}
