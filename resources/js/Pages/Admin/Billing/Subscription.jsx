import { useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';

export default function AdminBillingSubscription() {
    useEffect(() => {
        router.get(adminUrl('/admin/billing'));
    }, []);

    return (
        <AdminLayout>
            <Head title="Subscription" />
            <div className="p-6 text-sm text-gray-500">Redirecting to Billing…</div>
        </AdminLayout>
    );
}
