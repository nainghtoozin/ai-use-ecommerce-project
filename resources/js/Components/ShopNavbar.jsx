import { useState, useRef, useEffect } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import { assetUrl } from '@/Utils/helpers';
import NotificationBell from '@/Components/NotificationBell';

export default function ShopNavbar() {
    const { props, url } = usePage();
    const { auth, website_info, cart: serverCart, categories = [] } = props;
    
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [userMenuOpen, setUserMenuOpen] = useState(false);
    const [categoryMenuOpen, setCategoryMenuOpen] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [mobileSearchOpen, setMobileSearchOpen] = useState(false);
    const [cartCount, setCartCount] = useState(serverCart?.count || 0);
    
    const userMenuRef = useRef(null);
    const categoryMenuRef = useRef(null);
    const searchInputRef = useRef(null);

    const logoUrl = assetUrl(website_info?.logo);
    const siteName = website_info?.name || 'Electronics Store';

    useEffect(() => {
        const handleCartUpdate = (e) => {
            setCartCount(e.detail.count);
        };
        
        window.addEventListener('cart-updated', handleCartUpdate);
        
        function handleClickOutside(event) {
            if (userMenuRef.current && !userMenuRef.current.contains(event.target)) {
                setUserMenuOpen(false);
            }
            if (categoryMenuRef.current && !categoryMenuRef.current.contains(event.target)) {
                setCategoryMenuOpen(false);
            }
        }
        document.addEventListener('mousedown', handleClickOutside);
        
        return () => {
            window.removeEventListener('cart-updated', handleCartUpdate);
            document.removeEventListener('mousedown', handleClickOutside);
        };
    }, []);

    const handleSearch = (e) => {
        e.preventDefault();
        if (searchQuery.trim()) {
            router.get('/', { query: searchQuery.trim() }, { preserveState: true });
            setMobileSearchOpen(false);
        }
    };

    function isActive(href) {
        if (href === '/' && url === '/') return true;
        if (href !== '/') {
            const hrefPath = href.replace(/\/+$/, '');
            const urlPath = url.replace(/\/+$/, '');
            if (urlPath === hrefPath) return true;
            if (urlPath.startsWith(hrefPath + '/')) return true;
        }
        return false;
    }

    const logout = () => router.post('/logout');

    const navLinks = [
        { label: 'Home', href: '/', icon: 'bi-house-door' },
        { label: 'Products', href: '/', icon: 'bi-grid' },
        { label: 'My Orders', href: '/orders', icon: 'bi-receipt' },
    ];

    return (
        <nav className="bg-white border-b border-gray-200 sticky top-0 z-50 shadow-sm">
            <div className="max-w-7xl mx-auto px-3 sm:px-4 lg:px-8">
                <div className="flex items-center justify-between h-14 lg:h-16 gap-2 lg:gap-4">
                    <Link href="/" className="flex items-center gap-2 flex-shrink-0">
                        {logoUrl ? (
                            <img src={logoUrl} alt={siteName} className="h-8 w-auto lg:h-9" />
                        ) : (
                            <div className="h-8 w-8 lg:h-9 lg:w-9 bg-blue-600 rounded-lg flex items-center justify-center">
                                <i className="bi bi-shop text-white text-base lg:text-lg"></i>
                            </div>
                        )}
                        <span className="text-lg lg:text-xl font-bold text-gray-900 hidden lg:block">{siteName}</span>
                    </Link>

                    <form onSubmit={handleSearch} className="hidden md:flex flex-1 max-w-lg">
                        <div className="relative w-full">
                            <input
                                ref={searchInputRef}
                                type="text"
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                placeholder="Search products..."
                                className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-gray-50 hover:bg-white transition-colors"
                            />
                            <button
                                type="submit"
                                className="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-blue-600 transition-colors"
                            >
                                <i className="bi bi-search text-lg"></i>
                            </button>
                        </div>
                    </form>

                    <div className="hidden md:flex items-center gap-1">
                        <div ref={categoryMenuRef} className="relative">
                            <button
                                onClick={() => setCategoryMenuOpen(!categoryMenuOpen)}
                                className="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
                            >
                                <i className="bi bi-grid-3x3-gap text-lg"></i>
                                <span>Categories</span>
                                <i className={`bi bi-chevron-down text-xs transition-transform ${categoryMenuOpen ? 'rotate-180' : ''}`}></i>
                            </button>
                            {categoryMenuOpen && categories.length > 0 && (
                                <div className="absolute left-0 mt-2 w-56 bg-white rounded-xl shadow-lg border border-gray-200 z-50 py-2 overflow-hidden">
                                    <div className="px-3 py-1.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                        Browse Categories
                                    </div>
                                    <div className="max-h-64 overflow-y-auto">
                                        {categories.map((cat) => (
                                            <Link
                                                key={cat.id}
                                                href={`/?category=${cat.id}`}
                                                onClick={() => setCategoryMenuOpen(false)}
                                                className="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors"
                                            >
                                                <i className="bi bi-tag text-gray-400"></i>
                                                {cat.name}
                                            </Link>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>

                        {navLinks.map((item) => (
                            <Link
                                key={item.href}
                                href={item.href}
                                className={`flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg transition-colors ${
                                    isActive(item.href)
                                        ? 'bg-blue-50 text-blue-700'
                                        : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
                                }`}
                            >
                                <i className={`bi ${item.icon}`}></i>
                                <span>{item.label}</span>
                            </Link>
                        ))}
                    </div>

                    <div className="flex items-center gap-1">
                        <Link
                            href="/cart"
                            className="relative p-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                            title="Shopping Cart"
                        >
                            <i className="bi bi-cart3 text-xl"></i>
                            {cartCount > 0 && (
                                <span className="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-xs font-bold rounded-full min-w-[16px] h-4 flex items-center justify-center px-0.5">
                                    {cartCount > 99 ? '99+' : cartCount}
                                </span>
                            )}
                        </Link>

                        {auth?.user ? (
                            <>
                                <NotificationBell />
                                <Link
                                    href="/chat"
                                    className="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors hidden sm:block"
                                    title="Messages"
                                >
                                    <i className="bi bi-chat-dots text-xl"></i>
                                </Link>
                                <div ref={userMenuRef} className="relative">
                                    <button
                                        onClick={() => setUserMenuOpen(!userMenuOpen)}
                                        className="flex items-center gap-2 px-1.5 py-1 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
                                    >
                                        <div className="w-7 lg:w-8 h-7 lg:h-8 bg-blue-600 text-white rounded-full flex items-center justify-center text-xs font-bold">
                                            {auth.user.name?.charAt(0).toUpperCase()}
                                        </div>
                                        <span className="hidden lg:inline max-w-[80px] truncate">{auth.user.name}</span>
                                        <i className="bi bi-chevron-down text-xs text-gray-400 hidden lg:block"></i>
                                    </button>
                                    {userMenuOpen && (
                                        <>
                                            <div className="fixed inset-0 z-40" onClick={() => setUserMenuOpen(false)}></div>
                                            <div className="absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-lg border border-gray-200 z-50 overflow-hidden">
                                                <div className="px-4 py-3 border-b border-gray-100">
                                                    <p className="text-sm font-semibold text-gray-900 truncate">{auth.user.name}</p>
                                                    <p className="text-xs text-gray-500 truncate">{auth.user.email}</p>
                                                </div>
                                                <div className="py-1">
                                                    <Link href="/profile" className="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                                        <i className="bi bi-person text-gray-400"></i>
                                                        My Profile
                                                    </Link>
                                                    <Link href="/orders" className="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                                        <i className="bi bi-receipt text-gray-400"></i>
                                                        My Orders
                                                    </Link>
                                                    <div className="border-t border-gray-100 mt-1 pt-1">
                                                        <button onClick={logout} className="flex items-center gap-3 w-full text-left px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors">
                                                            <i className="bi bi-box-arrow-right text-gray-400"></i>
                                                            Logout
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </>
                                    )}
                                </div>
                            </>
                        ) : (
                            <div className="hidden sm:flex items-center gap-2">
                                <Link
                                    href="/login"
                                    className="px-3 lg:px-4 py-2 text-sm font-medium text-gray-700 hover:text-blue-600 transition-colors"
                                >
                                    Login
                                </Link>
                                <Link
                                    href="/register"
                                    className="px-3 lg:px-4 py-2 text-sm font-medium bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors shadow-sm"
                                >
                                    Register
                                </Link>
                            </div>
                        )}

                        <button
                            onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                            className="md:hidden p-2 text-gray-500 hover:bg-gray-100 rounded-lg transition-colors"
                        >
                            <i className={`bi ${mobileMenuOpen ? 'bi-x-lg' : 'bi-list'} text-xl`}></i>
                        </button>
                    </div>
                </div>
            </div>

            {mobileMenuOpen && (
                <div className="md:hidden border-t border-gray-200 bg-white shadow-lg max-h-[calc(100vh-3.5rem)] overflow-y-auto">
                    <div className="px-4 py-3 space-y-3">
                        <form onSubmit={handleSearch} className="relative">
                            <input
                                type="text"
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                placeholder="Search products..."
                                className="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50"
                            />
                            <button
                                type="submit"
                                className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"
                            >
                                <i className="bi bi-search text-lg"></i>
                            </button>
                        </form>

                        {categories.length > 0 && (
                            <div>
                                <button
                                    onClick={() => setCategoryMenuOpen(!categoryMenuOpen)}
                                    className="flex items-center justify-between w-full px-3 py-2.5 text-sm font-medium text-gray-700 bg-gray-50 rounded-lg"
                                >
                                    <span><i className="bi bi-grid-3x3-gap mr-2"></i>Categories</span>
                                    <i className={`bi bi-chevron-down text-xs transition-transform ${categoryMenuOpen ? 'rotate-180' : ''}`}></i>
                                </button>
                                {categoryMenuOpen && (
                                    <div className="mt-2 pl-2 space-y-1 border-l-2 border-gray-200">
                                        {categories.map((cat) => (
                                            <Link
                                                key={cat.id}
                                                href={`/?category=${cat.id}`}
                                                onClick={() => {
                                                    setCategoryMenuOpen(false);
                                                    setMobileMenuOpen(false);
                                                }}
                                                className="block px-3 py-2 text-sm text-gray-600 hover:text-blue-600"
                                            >
                                                {cat.name}
                                            </Link>
                                        ))}
                                    </div>
                                )}
                            </div>
                        )}

                        <div className="grid grid-cols-2 gap-2 pt-2 border-t border-gray-200">
                            {navLinks.map((item) => (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    onClick={() => setMobileMenuOpen(false)}
                                    className={`flex items-center justify-center gap-2 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors ${
                                        isActive(item.href)
                                            ? 'bg-blue-50 text-blue-700'
                                            : 'text-gray-600 hover:bg-gray-100'
                                    }`}
                                >
                                    <i className={`bi ${item.icon}`}></i>
                                    {item.label}
                                </Link>
                            ))}
                            <Link
                                href="/cart"
                                onClick={() => setMobileMenuOpen(false)}
                                className="flex items-center justify-center gap-2 px-3 py-2.5 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100"
                            >
                                <i className="bi bi-cart3"></i>
                                Cart
                                {cartCount > 0 && (
                                    <span className="bg-red-500 text-white text-xs font-bold rounded-full min-w-[16px] h-4 flex items-center justify-center px-0.5">
                                        {cartCount}
                                    </span>
                                )}
                            </Link>
                        </div>

                        {!auth?.user && (
                            <div className="grid grid-cols-2 gap-2 pt-2 border-t border-gray-200">
                                <Link
                                    href="/login"
                                    className="flex items-center justify-center px-3 py-2.5 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50"
                                >
                                    Login
                                </Link>
                                <Link
                                    href="/register"
                                    className="flex items-center justify-center px-3 py-2.5 text-sm font-medium bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                                >
                                    Register
                                </Link>
                            </div>
                        )}
                    </div>
                </div>
            )}
        </nav>
    );
}