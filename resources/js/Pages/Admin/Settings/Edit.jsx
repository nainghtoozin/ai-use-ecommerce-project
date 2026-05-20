import { useState } from 'react';
import { useForm, router, Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import ImageUpload from '@/Components/ImageUpload';

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
    phone: settings.phone || '',
    secondary_phone: settings.secondary_phone || '',
    support_email: settings.support_email || '',
    sales_email: settings.sales_email || '',
    contact_email: settings.contact_email || '',
    whatsapp_number: settings.whatsapp_number || '',
    telegram_username: settings.telegram_username || '',
    address_line_1: settings.address_line_1 || '',
    address_line_2: settings.address_line_2 || '',
    city: settings.city || '',
    state: settings.state || '',
    postal_code: settings.postal_code || '',
    country: settings.country || 'Myanmar',
    google_maps_link: settings.google_maps_link || '',
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

  const renderField = (name, label, type = 'text', options = {}) => (
    <div className={options.colSpan || 'col-span-1'}>
      <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>
      {type === 'textarea' ? (
        <textarea
          value={data[name]}
          onChange={(e) => setData(name, e.target.value)}
          rows={options.rows || 3}
          className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
      ) : type === 'select' ? (
        <select
          value={data[name]}
          onChange={(e) => setData(name, e.target.value)}
          className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
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
          <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
          <span className="ml-3 text-sm text-gray-500">{options.switchLabel || ''}</span>
        </label>
      ) : (
        <input
          type={type}
          value={data[name]}
          onChange={(e) => setData(name, e.target.value)}
          className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
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
                      ? 'border-blue-600 text-blue-600'
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                  }`}
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
                    {renderField('theme_color', 'Theme Color', 'text')}
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
                    <ImageUpload
                      name="hero_image"
                      label="Hero Image"
                      value={data.hero_image ?? settings.hero_image}
                      onChange={(file) => setData('hero_image', file)}
                      error={errors.hero_image}
                      maxSize={2}
                    />
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
                className="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex items-center gap-2"
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