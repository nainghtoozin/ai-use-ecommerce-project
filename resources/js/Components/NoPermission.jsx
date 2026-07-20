import { Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { ShieldAlert } from 'lucide-react';

export default function NoPermission({ message = "You don't have permission to access this page." }) {
    return (
        <AdminLayout>
            <Head title="Access Denied" />
            <div className="flex items-center justify-center min-h-[60vh]">
                <div className="text-center">
                    <div className="w-16 h-16 mx-auto mb-4 rounded-full bg-red-50 flex items-center justify-center">
                        <ShieldAlert className="w-8 h-8 text-red-500" />
                    </div>
                    <h2 className="text-xl font-semibold text-gray-900 mb-2">Access Denied</h2>
                    <p className="text-sm text-gray-500 max-w-md">{message}</p>
                    <p className="text-xs text-gray-400 mt-4">Contact your store owner if you need access.</p>
                </div>
            </div>
        </AdminLayout>
    );
}
