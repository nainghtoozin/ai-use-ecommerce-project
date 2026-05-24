import { useState } from 'react';
import { useForm, router, Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import ImageUpload from '@/Components/ImageUpload';

const PRESET_COLORS = [
  { name: 'Blue', value: '#3B82F6' },
  { name: 'Emerald', value: '#10B981' },
  { name: 'Purple', value: '#8B5CF6' },
  { name: 'Rose', value: '#F43F5E' },
  { name: 'Orange', value: '#F97316' },
  { name: 'Slate', value: '#64748B' },
  { name: 'Black', value: '#0F172A' },
];

const TABS = [
  { id: 'general', label: 'General', icon: 'bi-gear' },
  { id: 'branding', label: 'Branding', icon: 'bi-palette' },
  { id: 'contact', label: 'Contact', icon: 'bi-telephone' },
  { id: 'about', label: 'About Us', icon: 'bi-info-circle' },
  { id: 'social', label: 'Social Media', icon: 'bi-share' },
  { id: 'seo', label: 'SEO', icon: 'bi-search' },
  { id: 'policies', label: 'Policies', icon: 'bi-file-text' },
  { id: 'homepage', label: 'Homepage', icon: 'bi-house' },
  { id: 'footer', label: 'Footer', icon: 'bi-layout-text-window-reverse' },
  { id: 'system', label: 'System', icon: 'bi-sliders' },
];

export default function SettingsEdit({ settings = {} }) {
  const [activeTab, setActiveTab] = useState('general');
  const [heroItems, setHeroItems] = useState(() => {
    return (settings.hero_images_urls || []).map((url, i) => ({
      id: `hero-existing-${i}-${Date.now()}`,
      type: 'existing',
      url,
      file: null,
    }));
  });
  const [heroDragActive, setHeroDragActive] = useState(false);
  const MAX_HERO_IMAGES = 5;

  const totalHeroCount = heroItems.length;

  const addHeroFiles = (files) => {
    const validFiles = Array.from(files).filter((f) => f.type.startsWith('image/'));
    const spaceLeft = MAX_HERO_IMAGES - totalHeroCount;
    if (spaceLeft <= 0) return;
    const toAdd = validFiles.slice(0, spaceLeft);
    setHeroItems((prev) => [
      ...prev,
      ...toAdd.map((file) => ({
        id: `hero-new-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
        type: 'new',
        url: null,
        file,
      })),
    ]);
  };

  const removeHeroItem = (id) => {
    setHeroItems((prev) => prev.filter((item) => item.id !== id));
  };

  const replaceHeroItem = (id, file) => {
    setHeroItems((prev) =>
      prev.map((item) =>
        item.id === id
          ? { id: `hero-new-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`, type: 'new', url: null, file }
          : item
      )
    );
  };

  const moveHeroItem = (id, direction) => {
    setHeroItems((prev) => {
      const idx = prev.findIndex((item) => item.id === id);
      if (idx === -1) return prev;
      const newIdx = idx + direction;
      if (newIdx < 0 || newIdx >= prev.length) return prev;
      const result = [...prev];
      [result[idx], result[newIdx]] = [result[newIdx], result[idx]];
      return result;
    });
  };

  const setCoverItem = (id) => {
    setHeroItems((prev) => {
      const idx = prev.findIndex((item) => item.id === id);
      if (idx <= 0) return prev;
      const result = [...prev];
      const [item] = result.splice(idx, 1);
      result.unshift(item);
      return result;
    });
  };

  const { data, setData, processing, errors } = useForm({
    site_name: settings.site_name || settings.name || '',
    site_tagline: settings.site_tagline || '',
    site_description: settings.site_description || '',
    site_keywords: settings.site_keywords || '',
    default_language: settings.default_language || 'en',
    timezone: settings.timezone || 'Asia/Yangon',
    currency_code: settings.currency_code || 'MMK',
    currency_symbol: settings.currency_symbol || 'MMK',
    theme_color: settings.theme_color || '#3B82F6',
    logo: null,
    favicon: null,
    og_image: null,
    footer_logo: null,
    about_image: null,
    hero_image: null,
    phone: settings.contact_info?.primary_phone || settings.phone || '',
    secondary_phone: settings.contact_info?.secondary_phone || '',
    support_email: settings.contact_info?.support_email || settings.support_email || '',
    sales_email: settings.contact_info?.sales_email || '',
    contact_email: settings.contact_info?.contact_email || settings.contact_email || '',
    whatsapp_number: settings.contact_info?.whatsapp_number || settings.whatsapp_number || '',
    telegram_username: settings.contact_info?.telegram_username || '',
    address_line_1: settings.address_info?.address_line_1 || settings.address || '',
    address_line_2: settings.address_info?.address_line_2 || '',
    city: settings.address_info?.city || '',
    state: settings.address_info?.state_region || '',
    postal_code: settings.address_info?.postal_code || '',
    country: settings.address_info?.country || settings.country || 'Myanmar',
    google_maps_link: settings.address_info?.google_maps_link || settings.google_maps_embed_url || '',
    about_title: settings.about_title || '',
    about_description: settings.about_description || '',
    mission_title: settings.mission_title || 'Our Mission',
    mission_description: settings.mission_description || '',
    vision_title: settings.vision_title || 'Our Vision',
    vision_description: settings.vision_description || '',
    company_name: settings.company_name || '',
    company_registration_number: settings.company_registration_number || '',
    facebook_url: settings.facebook_url || '',
    twitter_url: settings.twitter_url || '',
    instagram_url: settings.instagram_url || '',
    linkedin_url: settings.linkedin_url || '',
    youtube_url: settings.youtube_url || '',
    tiktok_url: settings.tiktok_url || '',
    pinterest_url: settings.pinterest_url || '',
    meta_title: settings.meta_title || '',
    meta_description: settings.meta_description || '',
    meta_keywords: settings.meta_keywords || '',
    robots_meta: settings.robots_meta || 'index, follow',
    google_analytics_id: settings.google_analytics_id || '',
    google_tag_manager_id: settings.google_tag_manager_id || '',
    facebook_pixel_id: settings.facebook_pixel_id || '',
    privacy_policy: settings.privacy_policy || '',
    terms_conditions: settings.terms_conditions || '',
    return_policy: settings.return_policy || '',
    shipping_policy: settings.shipping_policy || '',
    hero_title: settings.hero_title || '',
    hero_subtitle: settings.hero_subtitle || '',
    hero_button_text: settings.hero_button_text || '',
    hero_button_link: settings.hero_button_link || '',
    shipping_info: settings.shipping_info || '',
    secure_payment_info: settings.secure_payment_info || '',
    easy_returns_info: settings.easy_returns_info || '',
    footer_description: settings.footer_description || '',
    footer_copyright: settings.footer_copyright || '',
    footer_extra_text: settings.footer_extra_text || '',
    free_shipping_threshold: settings.free_shipping_threshold || 50000,
    default_shipping_fee: settings.default_shipping_fee || 2000,
    cod_enabled: settings.cod_enabled !== undefined ? settings.cod_enabled : true,
    guest_checkout_enabled: settings.guest_checkout_enabled !== undefined ? settings.guest_checkout_enabled : true,
    maintenance_mode: settings.maintenance_mode || false,
    maintenance_message: settings.maintenance_message || '',
    allow_registration: settings.allow_registration !== undefined ? settings.allow_registration : true,
    enable_reviews: settings.enable_reviews !== undefined ? settings.enable_reviews : true,
    enable_wishlist: settings.enable_wishlist !== undefined ? settings.enable_wishlist : true,
    enable_compare: settings.enable_compare !== undefined ? settings.enable_compare : true,
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    const formData = new FormData();
    Object.keys(data).forEach(key => {
      if (data[key] !== null && data[key] !== '') {
        if (data[key] instanceof File) {
          formData.append(key, data[key]);
        } else if (typeof data[key] === 'boolean') {
          formData.append(key, data[key] ? '1' : '0');
        } else {
          formData.append(key, data[key]);
        }
      }
    });

    if (heroItems.length > 0) {
      const payload = heroItems.map((item) => ({
        type: item.type,
        path: item.type === 'existing' ? item.url : null,
      }));
      formData.append('hero_images_payload', JSON.stringify(payload));
      heroItems.forEach((item) => {
        if (item.type === 'new') {
          formData.append('hero_images[]', item.file);
        }
      });
    } else {
      formData.append('hero_images_payload', JSON.stringify([]));
    }

    formData.append('_method', 'PUT');
    router.post('/admin/website-info/edit', formData, {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => {
        setTimeout(() => {
          window.location.reload();
        }, 500);
      },
    });
  };

  const handleHeroDragOver = (e) => {
    e.preventDefault();
    if (totalHeroCount < MAX_HERO_IMAGES) setHeroDragActive(true);
  };

  const handleHeroDragLeave = (e) => {
    e.preventDefault();
    setHeroDragActive(false);
  };

  const handleHeroDrop = (e) => {
    e.preventDefault();
    setHeroDragActive(false);
    if (e.dataTransfer.files?.length) addHeroFiles(e.dataTransfer.files);
  };

  const renderField = (name, label, type = 'text', options = {}) => (
    <div className={options.colSpan || 'col-span-1'}>
      <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>
      {type === 'textarea' ? (
        <textarea
          value={data[name]}
          onChange={(e) => setData(name, e.target.value)}
          rows={options.rows || 3}
          className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[var(--theme-color,#3B82F6)]"
        />
      ) : type === 'select' ? (
        <select
          value={data[name]}
          onChange={(e) => setData(name, e.target.value)}
          className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[var(--theme-color,#3B82F6)]"
        >
          {options.options?.map(opt => (
            <option key={opt.value} value={opt.value}>{opt.label}</option>
          ))}
        </select>
      ) : type === 'switch' ? (
        <label className="relative inline-flex items-center cursor-pointer">
          <input
            type="checkbox"
            checked={data[name]}
            onChange={(e) => setData(name, e.target.checked)}
            className="sr-only peer"
          />
          <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-[rgba(var(--theme-color-rgb,59,130,246)/0.3)] rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[var(--theme-color,#3B82F6)]"></div>
          <span className="ml-3 text-sm text-gray-500">{options.switchLabel || ''}</span>
        </label>
      ) : (
        <input
          type={type}
          value={data[name]}
          onChange={(e) => setData(name, e.target.value)}
          className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[var(--theme-color,#3B82F6)]"
        />
      )}
      {errors[name] && <p className="mt-1 text-sm text-red-600">{errors[name]}</p>}
    </div>
  );

  return (
    <AdminLayout>
      <Head title="Website Settings" />
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div className="mb-6">
          <h1 className="text-2xl font-bold text-gray-900">Website Settings</h1>
          <p className="text-sm text-gray-500 mt-1">Manage all your website settings from one place.</p>
        </div>

        <div className="bg-white rounded-lg shadow-sm border border-gray-200">
          {/* Tabs */}
          <div className="border-b border-gray-200 overflow-x-auto">
            <nav className="flex space-x-1 px-4 min-w-max">
              {TABS.map(tab => (
                <button
                  key={tab.id}
                  onClick={() => setActiveTab(tab.id)}
                   className={`flex items-center px-4 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap ${
                    activeTab === tab.id
                      ? 'text-white border-transparent'
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                  }`}
                  style={activeTab === tab.id ? { backgroundColor: 'var(--theme-color, #3B82F6)', borderBottomColor: 'var(--theme-color, #3B82F6)' } : {}}
                >
                  <i className={`bi ${tab.icon} mr-2`}></i>
                  {tab.label}
                </button>
              ))}
            </nav>
          </div>

          <form onSubmit={handleSubmit} className="p-6">
            {/* General Tab */}
            {activeTab === 'general' && (
              <div className="space-y-6">
                <div>
                  <h3 className="text-lg font-medium text-gray-900 mb-4">General Settings</h3>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {renderField('site_name', 'Site Name')}
                    {renderField('site_tagline', 'Site Tagline')}
                    {renderField('site_description', 'Site Description', 'textarea')}
                    {renderField('site_keywords', 'Site Keywords')}
                    {renderField('default_language', 'Default Language', 'select', {
                      options: [
                        { value: 'en', label: 'English' },
                        { value: 'my', label: 'Myanmar' },
                      ]
                    })}
                    {renderField('timezone', 'Timezone')}
                    {renderField('currency_code', 'Currency Code')}
                    {renderField('currency_symbol', 'Currency Symbol')}

                    {/* Theme Color Picker */}
                    <div className="col-span-1 md:col-span-2">
                      <label className="block text-sm font-medium text-gray-700 mb-2">Theme Color</label>
                      <div className="flex flex-wrap items-start gap-4">
                        <div className="flex flex-col items-center gap-2">
                          <div
                            className="w-16 h-16 rounded-xl shadow-inner border-2 border-gray-200 transition-colors duration-200"
                            style={{ backgroundColor: data.theme_color }}
                          />
                          <input
                            type="color"
                            value={data.theme_color}
                            onChange={(e) => setData('theme_color', e.target.value)}
                            className="w-16 h-10 cursor-pointer rounded-lg border border-gray-300"
                            title="Pick custom color"
                          />
                        </div>

                        <div className="flex-1">
                          <p className="text-xs text-gray-500 mb-2">Preset Colors</p>
                          <div className="flex flex-wrap gap-2">
                            {PRESET_COLORS.map((color) => (
                              <button
                                key={color.value}
                                type="button"
                                onClick={() => setData('theme_color', color.value)}
                                className={`group relative w-10 h-10 rounded-lg border-2 transition-all duration-150 hover:scale-110 ${
                                  data.theme_color === color.value
                                    ? 'border-gray-900 ring-2 ring-gray-300 ring-offset-2'
                                    : 'border-gray-200 hover:border-gray-400'
                                }`}
                                style={{ backgroundColor: color.value }}
                                title={color.name}
                              >
                                {data.theme_color === color.value && (
                                  <svg className="w-4 h-4 text-white absolute inset-0 m-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={3}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                                  </svg>
                                )}
                              </button>
                            ))}
                          </div>
                          <p className="text-xs text-gray-400 mt-2">
                            Selected: <span className="font-mono font-medium text-gray-600">{data.theme_color}</span>
                          </p>
                        </div>
                      </div>

                      <div className="mt-3">
                        <label className="text-xs text-gray-500 mb-1 block">Custom Hex (optional)</label>
                        <input
                          type="text"
                          value={data.theme_color}
                          onChange={(e) => setData('theme_color', e.target.value)}
                          placeholder="#3B82F6"
                          className="w-full max-w-xs border border-gray-300 rounded-lg px-3 py-1.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-[var(--theme-color,#3B82F6)]"
                        />
                      </div>

                      {/* Live Preview */}
                      <div className="mt-4 p-4 bg-gray-50 rounded-lg border border-gray-200">
                        <p className="text-xs font-medium text-gray-500 mb-3">Live Preview</p>
                        <div className="flex flex-wrap gap-3 items-center">
                          <button
                            className="px-4 py-2 text-white text-sm font-medium rounded-lg transition-colors"
                            style={{ backgroundColor: data.theme_color }}
                          >
                            Primary Button
                          </button>
                          <button
                            className="px-4 py-2 text-sm font-medium rounded-lg border-2 transition-colors"
                            style={{ borderColor: data.theme_color, color: data.theme_color }}
                          >
                            Outline Button
                          </button>
                          <span
                            className="px-3 py-1 text-white text-xs font-medium rounded-full"
                            style={{ backgroundColor: data.theme_color }}
                          >
                            Badge
                          </span>
                          <a
                            href="#"
                            className="text-sm font-medium"
                            style={{ color: data.theme_color }}
                          >
                            Link
                          </a>
                        </div>
                      </div>

                      {errors.theme_color && <p className="mt-2 text-sm text-red-600">{errors.theme_color}</p>}
                    </div>
                  </div>
                </div>
              </div>
            )}

            {/* Branding Tab */}
            {activeTab === 'branding' && (
              <div className="space-y-6">
                <div>
                  <h3 className="text-lg font-medium text-gray-900 mb-4">Branding</h3>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <ImageUpload
                      name="logo"
                      label="Site Logo"
                      value={data.logo ?? settings.logo}
                      onChange={(file) => setData('logo', file)}
                      error={errors.logo}
                      maxSize={2}
                    />
                    <ImageUpload
                      name="favicon"
                      label="Favicon"
                      value={data.favicon ?? settings.favicon}
                      onChange={(file) => setData('favicon', file)}
                      error={errors.favicon}
                      maxSize={0.5}
                      accept="image/*"
                    />
                    <ImageUpload
                      name="og_image"
                      label="Open Graph Image"
                      value={data.og_image ?? settings.og_image}
                      onChange={(file) => setData('og_image', file)}
                      error={errors.og_image}
                      maxSize={2}
                    />
                    <ImageUpload
                      name="footer_logo"
                      label="Footer Logo"
                      value={data.footer_logo ?? settings.footer_logo}
                      onChange={(file) => setData('footer_logo', file)}
                      error={errors.footer_logo}
                      maxSize={2}
                    />
                  </div>
                </div>
              </div>
            )}

            {/* Contact Tab */}
            {activeTab === 'contact' && (
              <div className="space-y-6">
                <div>
                  <h3 className="text-lg font-medium text-gray-900 mb-4">Contact Information</h3>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {renderField('phone', 'Primary Phone')}
                    {renderField('secondary_phone', 'Secondary Phone')}
                    {renderField('support_email', 'Support Email')}
                    {renderField('sales_email', 'Sales Email')}
                    {renderField('contact_email', 'Contact Email')}
                    {renderField('whatsapp_number', 'WhatsApp Number')}
                    {renderField('telegram_username', 'Telegram Username')}
                  </div>
                </div>
                <div className="border-t border-gray-200 pt-6">
                  <h3 className="text-lg font-medium text-gray-900 mb-4">Address</h3>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {renderField('address_line_1', 'Address Line 1')}
                    {renderField('address_line_2', 'Address Line 2')}
                    {renderField('city', 'City')}
                    {renderField('state', 'State/Region')}
                    {renderField('postal_code', 'Postal Code')}
                    {renderField('country', 'Country')}
                    {renderField('google_maps_link', 'Google Maps Link', 'text', { colSpan: 'col-span-2' })}
                  </div>
                </div>
              </div>
            )}

            {/* About Us Tab */}
            {activeTab === 'about' && (
              <div className="space-y-6">
                <div>
                  <h3 className="text-lg font-medium text-gray-900 mb-4">About Us</h3>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {renderField('about_title', 'About Title')}
                    {renderField('about_description', 'About Description', 'textarea', { rows: 5 })}
                    <ImageUpload
                      name="about_image"
                      label="About Image"
                      value={data.about_image ?? settings.about_image}
                      onChange={(file) => setData('about_image', file)}
                      error={errors.about_image}
                      maxSize={2}
                    />
                    {renderField('company_name', 'Company Name')}
                  </div>
                </div>
                <div className="border-t border-gray-200 pt-6">
                  <h3 className="text-lg font-medium text-gray-900 mb-4">Mission & Vision</h3>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {renderField('mission_title', 'Mission Title')}
                    {renderField('mission_description', 'Mission Description', 'textarea')}
                    {renderField('vision_title', 'Vision Title')}
                    {renderField('vision_description', 'Vision Description', 'textarea')}
                  </div>
                </div>
              </div>
            )}

            {/* Social Media Tab */}
            {activeTab === 'social' && (
              <div className="space-y-6">
                <div>
                  <h3 className="text-lg font-medium text-gray-900 mb-4">Social Media Links</h3>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {renderField('facebook_url', 'Facebook URL')}
                    {renderField('twitter_url', 'Twitter URL')}
                    {renderField('instagram_url', 'Instagram URL')}
                    {renderField('linkedin_url', 'LinkedIn URL')}
                    {renderField('youtube_url', 'YouTube URL')}
                    {renderField('tiktok_url', 'TikTok URL')}
                    {renderField('pinterest_url', 'Pinterest URL')}
                  </div>
                </div>
              </div>
            )}

            {/* SEO Tab */}
            {activeTab === 'seo' && (
              <div className="space-y-6">
                <div>
                  <h3 className="text-lg font-medium text-gray-900 mb-4">SEO Settings</h3>
                  <div className="grid grid-cols-1 gap-4">
                    {renderField('meta_title', 'Meta Title')}
                    {renderField('meta_description', 'Meta Description', 'textarea', { rows: 3 })}
                    {renderField('meta_keywords', 'Meta Keywords')}
                    {renderField('robots_meta', 'Robots Meta')}
                    {renderField('canonical_url', 'Canonical URL')}
                  </div>
                </div>
                <div className="border-t border-gray-200 pt-6">
                  <h3 className="text-lg font-medium text-gray-900 mb-4">Analytics</h3>
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    {renderField('google_analytics_id', 'Google Analytics ID')}
                    {renderField('google_tag_manager_id', 'Google Tag Manager ID')}
                    {renderField('facebook_pixel_id', 'Facebook Pixel ID')}
                  </div>
                </div>
              </div>
            )}

            {/* Policies Tab */}
            {activeTab === 'policies' && (
              <div className="space-y-6">
                <div>
                  <h3 className="text-lg font-medium text-gray-900 mb-4">Website Policies</h3>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {renderField('privacy_policy', 'Privacy Policy', 'textarea', { rows: 5 })}
                    {renderField('terms_conditions', 'Terms & Conditions', 'textarea', { rows: 5 })}
                    {renderField('return_policy', 'Return Policy', 'textarea', { rows: 5 })}
                    {renderField('shipping_policy', 'Shipping Policy', 'textarea', { rows: 5 })}
                  </div>
                </div>
              </div>
            )}

            {/* Homepage Tab */}
            {activeTab === 'homepage' && (
              <div className="space-y-6">
                <div>
                  <h3 className="text-lg font-medium text-gray-900 mb-4">Hero Section</h3>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {renderField('hero_title', 'Hero Title')}
                    {renderField('hero_subtitle', 'Hero Subtitle', 'textarea')}
                    {renderField('hero_button_text', 'Hero Button Text')}
                    {renderField('hero_button_link', 'Hero Button Link')}
                  </div>
                  <div className="mt-4">
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Hero Images
                      <span className="text-gray-400 font-normal ml-1">({totalHeroCount}/{MAX_HERO_IMAGES})</span>
                    </label>
                    <p className="text-xs text-gray-500 mb-3">Upload up to {MAX_HERO_IMAGES} images for the homepage hero carousel. Click an image to replace it. Drag to reorder or use the arrow buttons.</p>

                    {/* Preview Grid */}
                    {heroItems.length > 0 && (
                      <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3 mb-3">
                        {heroItems.map((item, idx) => {
                          const previewUrl = item.type === 'new' ? URL.createObjectURL(item.file) : item.url;
                          return (
                          <div key={item.id} className="relative group rounded-xl overflow-hidden border border-gray-200 bg-gray-50 shadow-sm hover:shadow-md transition-shadow">
                            {/* Cover badge */}
                            {idx === 0 && (
                              <span className="absolute top-1 left-1 z-10 bg-yellow-500 text-white text-[10px] font-medium px-1.5 py-0.5 rounded flex items-center gap-0.5 shadow">
                                <svg className="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                                Cover
                              </span>
                            )}
                            {/* New badge */}
                            {item.type === 'new' && (
                              <span className="absolute top-1 right-1 z-10 bg-blue-500 text-white text-[10px] font-medium px-1.5 py-0.5 rounded shadow">
                                New
                              </span>
                            )}
                            {/* Image (click to replace) */}
                            <label className="block cursor-pointer">
                              <input
                                type="file"
                                accept="image/*"
                                className="hidden"
                                onChange={(e) => {
                                  if (e.target.files?.[0]) {
                                    replaceHeroItem(item.id, e.target.files[0]);
                                    e.target.value = '';
                                  }
                                }}
                              />
                              <div className="relative aspect-video overflow-hidden">
                                <img
                                  src={previewUrl}
                                  alt={`Hero ${idx + 1}`}
                                  className="w-full h-full object-cover"
                                  onLoad={() => item.type === 'new' && URL.revokeObjectURL(previewUrl)}
                                />
                                {/* Hover overlay */}
                                <div className="absolute inset-0 bg-black/0 group-hover:bg-black/40 transition-colors flex items-center justify-center">
                                  <div className="opacity-0 group-hover:opacity-100 transition-all duration-200 bg-white/20 backdrop-blur-sm rounded-full p-2 hover:bg-white/30">
                                    <svg className="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                  </div>
                                </div>
                              </div>
                            </label>

                            {/* Bottom toolbar */}
                            <div className="flex items-center justify-between px-2 py-1.5 bg-gray-50 border-t border-gray-100">
                              <div className="flex items-center gap-0.5">
                                <button
                                  type="button"
                                  onClick={() => moveHeroItem(item.id, -1)}
                                  disabled={idx === 0}
                                  className="p-1 rounded hover:bg-gray-200 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                                  title="Move left"
                                >
                                  <svg className="w-3.5 h-3.5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>
                                </button>
                                <span className="text-[11px] text-gray-400 font-mono w-4 text-center select-none">{idx + 1}</span>
                                <button
                                  type="button"
                                  onClick={() => moveHeroItem(item.id, 1)}
                                  disabled={idx === heroItems.length - 1}
                                  className="p-1 rounded hover:bg-gray-200 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                                  title="Move right"
                                >
                                  <svg className="w-3.5 h-3.5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>
                                </button>
                                {idx > 0 && (
                                  <button
                                    type="button"
                                    onClick={() => setCoverItem(item.id)}
                                    className="p-1 rounded hover:bg-amber-100 transition-colors ml-1"
                                    title="Set as cover"
                                  >
                                    <svg className="w-3.5 h-3.5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" /></svg>
                                  </button>
                                )}
                              </div>
                              <button
                                type="button"
                                onClick={() => removeHeroItem(item.id)}
                                className="p-1 rounded hover:bg-red-100 transition-colors group/delete"
                                title="Remove"
                              >
                                <svg className="w-3.5 h-3.5 text-gray-400 group-hover/delete:text-red-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                              </button>
                            </div>
                          </div>
                        );
                        })}
                      </div>
                    )}

                    {/* Upload Drop Area */}
                    {totalHeroCount < MAX_HERO_IMAGES && (
                      <label
                        onDragOver={handleHeroDragOver}
                        onDragLeave={handleHeroDragLeave}
                        onDrop={handleHeroDrop}
                        className={`relative flex items-center justify-center border-2 border-dashed rounded-lg p-6 cursor-pointer transition-colors ${
                          heroDragActive
                            ? 'border-blue-500 bg-blue-50'
                            : 'border-gray-300 hover:border-gray-400 hover:bg-gray-50'
                        }`}
                      >
                        <input
                          type="file"
                          accept="image/*"
                          multiple
                          onChange={(e) => { if (e.target.files?.length) addHeroFiles(e.target.files); e.target.value = ''; }}
                          className="hidden"
                        />
                        <div className="text-center">
                          <svg className="mx-auto h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                              d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3" />
                          </svg>
                          <p className="mt-1 text-sm text-gray-600">
                            <span className="font-medium text-blue-600">Click to upload</span> or drag & drop
                          </p>
                          <p className="mt-0.5 text-xs text-gray-400">PNG, JPG, WEBP up to 2MB</p>
                        </div>
                      </label>
                    )}

                    {errors.hero_images && <p className="mt-2 text-sm text-red-600">{errors.hero_images}</p>}
                  </div>
                </div>
                <div className="border-t border-gray-200 pt-6">
                  <h3 className="text-lg font-medium text-gray-900 mb-4">Info Cards</h3>
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    {renderField('shipping_info', 'Shipping Info', 'textarea', { rows: 2 })}
                    {renderField('secure_payment_info', 'Secure Payment Info', 'textarea', { rows: 2 })}
                    {renderField('easy_returns_info', 'Easy Returns Info', 'textarea', { rows: 2 })}
                  </div>
                </div>
              </div>
            )}

            {/* Footer Tab */}
            {activeTab === 'footer' && (
              <div className="space-y-6">
                <div>
                  <h3 className="text-lg font-medium text-gray-900 mb-4">Footer Settings</h3>
                  <div className="grid grid-cols-1 gap-4">
                    {renderField('footer_description', 'Footer Description', 'textarea', { rows: 3 })}
                    {renderField('footer_copyright', 'Copyright Text')}
                    {renderField('footer_extra_text', 'Extra Text')}
                  </div>
                </div>
              </div>
            )}

            {/* System Tab */}
            {activeTab === 'system' && (
              <div className="space-y-6">
                <div>
                  <h3 className="text-lg font-medium text-gray-900 mb-4">Order Settings</h3>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {renderField('free_shipping_threshold', 'Free Shipping Threshold', 'number')}
                    {renderField('default_shipping_fee', 'Default Shipping Fee', 'number')}
                    {renderField('cod_enabled', 'COD Enabled', 'switch', { switchLabel: 'Enable Cash on Delivery' })}
                    {renderField('guest_checkout_enabled', 'Guest Checkout', 'switch', { switchLabel: 'Allow guest checkout' })}
                  </div>
                </div>
                <div className="border-t border-gray-200 pt-6">
                  <h3 className="text-lg font-medium text-gray-900 mb-4">System Options</h3>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {renderField('allow_registration', 'User Registration', 'switch', { switchLabel: 'Allow new user registration' })}
                    {renderField('enable_reviews', 'Product Reviews', 'switch', { switchLabel: 'Enable product reviews' })}
                    {renderField('enable_wishlist', 'Wishlist', 'switch', { switchLabel: 'Enable wishlist feature' })}
                    {renderField('enable_compare', 'Compare Products', 'switch', { switchLabel: 'Enable product comparison' })}
                  </div>
                </div>
                <div className="border-t border-gray-200 pt-6">
                  <h3 className="text-lg font-medium text-gray-900 mb-4">Maintenance Mode</h3>
                  <div className="grid grid-cols-1 gap-4">
                    {renderField('maintenance_mode', 'Maintenance Mode', 'switch', { switchLabel: 'Put website in maintenance mode' })}
                    {renderField('maintenance_message', 'Maintenance Message', 'textarea', { rows: 2 })}
                  </div>
                </div>
              </div>
            )}

            {/* Submit */}
            <div className="flex justify-end pt-6 border-t border-gray-200 mt-8">
              <button
                type="submit"
                disabled={processing}
                className="px-6 py-2 text-white rounded-lg disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex items-center gap-2"
                style={{ backgroundColor: 'var(--theme-color, #3B82F6)' }}
                onMouseEnter={(e) => { if (!e.currentTarget.disabled) e.currentTarget.style.opacity = '0.9'; }}
                onMouseLeave={(e) => e.currentTarget.style.opacity = '1'}
              >
                {processing ? (
                  <>
                    <svg className="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                      <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                      <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Saving...
                  </>
                ) : (
                  <>
                    <i className="bi bi-check-lg"></i>
                    Save Changes
                  </>
                )}
              </button>
            </div>
          </form>
        </div>
      </div>
    </AdminLayout>
  );
}