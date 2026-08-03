import { useState, useRef, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Globe, ChevronDown, Check } from 'lucide-react';

const languages = [
    { code: 'en', label: 'English', flag: '🇺🇸' },
    { code: 'my', label: 'Myanmar', flag: '🇲🇲' },
];

export default function LanguageSwitcher({ variant = 'navbar' }) {
    const { locale } = usePage().props;
    const [open, setOpen] = useState(false);
    const ref = useRef(null);

    const currentLang = languages.find(l => l.code === locale) || languages[0];

    useEffect(() => {
        function handleClickOutside(e) {
            if (ref.current && !ref.current.contains(e.target)) {
                setOpen(false);
            }
        }
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const switchLanguage = (code) => {
        router.post('/language/switch', { locale: code }, {
            preserveScroll: true,
            onFinish: () => setOpen(false),
        });
    };

    if (variant === 'mobile') {
        return (
            <div className="flex gap-2">
                {languages.map((lang) => (
                    <button
                        key={lang.code}
                        onClick={() => switchLanguage(lang.code)}
                        className={`flex-1 flex items-center justify-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-colors ${
                            locale === lang.code
                                ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
                                : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
                        }`}
                    >
                        <span>{lang.flag}</span>
                        <span>{lang.label}</span>
                    </button>
                ))}
            </div>
        );
    }

    return (
        <div ref={ref} className="relative">
            <button
                onClick={() => setOpen(!open)}
                className="flex items-center gap-1.5 px-2.5 py-1.5 text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors"
                aria-label="Switch language"
            >
                <Globe className="w-4 h-4" />
                <span className="hidden sm:inline">{currentLang.label}</span>
                <ChevronDown className={`w-3 h-3 transition-transform ${open ? 'rotate-180' : ''}`} />
            </button>

            {open && (
                <>
                    <div className="fixed inset-0 z-40" onClick={() => setOpen(false)} />
                    <div className="absolute right-0 mt-1 w-44 bg-white dark:bg-gray-900 rounded-xl shadow-lg border border-gray-200 dark:border-gray-800 z-50 overflow-hidden py-1">
                        {languages.map((lang) => (
                            <button
                                key={lang.code}
                                onClick={() => switchLanguage(lang.code)}
                                className={`w-full flex items-center gap-3 px-4 py-2.5 text-sm transition-colors ${
                                    locale === lang.code
                                        ? 'bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-100 font-medium'
                                        : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800'
                                }`}
                            >
                                <span className="text-base">{lang.flag}</span>
                                <span className="flex-1 text-left">{lang.label}</span>
                                {locale === lang.code && <Check className="w-4 h-4 text-green-600" />}
                            </button>
                        ))}
                    </div>
                </>
            )}
        </div>
    );
}
