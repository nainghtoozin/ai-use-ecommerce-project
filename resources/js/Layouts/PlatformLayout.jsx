import FlashMessages from '@/Components/FlashMessages';
import PlatformNavbar from '@/Components/PlatformNavbar';
import PlatformFooter from '@/Components/PlatformFooter';

export default function PlatformLayout({ children }) {
    return (
        <>
            <FlashMessages />
            <div className="min-h-screen flex flex-col bg-gray-50">
                <PlatformNavbar />
                <main className="flex-1">
                    <div className="py-2">
                        {children}
                    </div>
                </main>
                <PlatformFooter />
            </div>
        </>
    );
}
