import { useState, useRef, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Globe, ChevronDown, Check } from 'lucide-react';
import { adminUrl } from '@/Utils/adminUrl';

const languages = [
    { code: 'en', label: 'English', flag: '🇺🇸' },
    { code: 'my', label: 'Myanmar', flag: '🇲🇲' },
];

export default function LanguageSwitcher() {
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

    const switchLanguage = (langCode) => {
        router.post(adminUrl('/admin/language/switch'), { locale: langCode }, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <div className="relative" ref={ref}>
            <button
                onClick={() => setOpen(!open)}
                className="flex items-center gap-1.5 px-2.5 py-1.5 text-sm text-gray-600 hover:text-gray-900 dark:text-gray-100 hover:bg-gray-100 dark:bg-gray-800 rounded-lg transition-colors"
                title="Switch Language"
            >
                <Globe className="w-4 h-4" />
                <span className="hidden sm:inline text-xs font-medium">{currentLang.label}</span>
                <ChevronDown className="w-3 h-3" />
            </button>

            {open && (
                <div className="absolute right-0 mt-1 w-40 bg-white dark:bg-gray-900 rounded-lg shadow-lg border border-gray-200 dark:border-gray-800 py-1 z-50">
                    {languages.map((lang) => (
                        <button
                            key={lang.code}
                            onClick={() => switchLanguage(lang.code)}
                            className={`w-full flex items-center gap-2 px-3 py-2 text-sm hover:bg-gray-50 dark:bg-gray-950 transition-colors ${
                                locale === lang.code ? 'text-blue-600 font-medium' : 'text-gray-700'
                            }`}
                        >
                            <span className="text-base">{lang.flag}</span>
                            <span className="flex-1">{lang.label}</span>
                            {locale === lang.code && <Check className="w-4 h-4 text-blue-600" />}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
