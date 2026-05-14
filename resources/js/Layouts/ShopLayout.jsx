import FlashMessages from '@/Components/FlashMessages';
import ShopNavbar from '@/Components/ShopNavbar';
import ShopFooter from '@/Components/ShopFooter';

export default function ShopLayout({ children }) {
    return (
        <>
            <FlashMessages />
            <div className="min-h-screen flex flex-col bg-gray-50">
                <ShopNavbar />
                <main className="flex-1">
                    <div className="py-6 lg:py-8">
                        {children}
                    </div>
                </main>
                <ShopFooter />
            </div>
        </>
    );
}