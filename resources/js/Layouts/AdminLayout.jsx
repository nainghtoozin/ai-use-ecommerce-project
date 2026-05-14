import FlashMessages from '@/Components/FlashMessages';
import AdminSidebar from '@/Components/AdminSidebar';
import AdminHeader from '@/Components/AdminHeader';
import AdminFooter from '@/Components/AdminFooter';

export default function AdminLayout({ children, header = null }) {
    return (
        <>
            <FlashMessages />
            <div className="min-h-screen flex bg-gray-100">
                <AdminSidebar />
                <div className="flex-1 flex flex-col min-w-0">
                    <AdminHeader />
                    {header && (
                        <div className="bg-white border-b border-gray-200">
                            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 lg:py-4">
                                {header}
                            </div>
                        </div>
                    )}
                    <main className="flex-1">
                        {children}
                    </main>
                    <AdminFooter />
                </div>
            </div>
        </>
    );
}