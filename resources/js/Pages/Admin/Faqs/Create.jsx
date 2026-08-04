import { useState } from 'react';
import { Link, router, Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { adminUrl } from '@/Utils/adminUrl';
import RichTextEditor from '@/Components/editor/RichTextEditor';

export default function FaqCreate({ categories }) {
    const [form, setForm] = useState({
        category: 'general',
        question_en: '',
        question_my: '',
        answer_en: '',
        answer_my: '',
        sort_order: 0,
        is_active: true,
    });
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);
    const [preview, setPreview] = useState(false);

    function setField(key, value) {
        setForm(prev => ({ ...prev, [key]: value }));
        if (errors[key]) setErrors(prev => { const n = { ...prev }; delete n[key]; return n; });
    }

    function handleSubmit(e) {
        e.preventDefault();
        setProcessing(true);
        router.post(adminUrl('/admin/faqs'), form, {
            onError: (errs) => setErrors(errs),
            onFinish: () => setProcessing(false),
        });
    }

    return (
        <AdminLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Create FAQ</h2>}>
            <Head title="Create FAQ" />
            <div className="py-6">
                <div className="max-w-4xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white dark:bg-gray-900 overflow-hidden shadow-sm sm:rounded-lg">
                        <form onSubmit={handleSubmit} className="p-6 space-y-6">
                            <div className="flex items-center justify-between mb-2">
                                <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">FAQ Details</h3>
                                <div className="flex items-center gap-3">
                                    <button type="button" onClick={() => setPreview(!preview)} className="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400">
                                        {preview ? 'Edit' : 'Preview'}
                                    </button>
                                    <Link href={adminUrl('/admin/faqs')} className="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">Back to list</Link>
                                </div>
                            </div>

                            {preview ? (
                                <div className="space-y-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                    <div>
                                        <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">{categories[form.category] || form.category}</span>
                                    </div>
                                    <div>
                                        <h4 className="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Question (EN)</h4>
                                        <p className="text-gray-900 dark:text-gray-100">{form.question_en || <span className="italic text-gray-400">Not set</span>}</p>
                                    </div>
                                    {form.question_my && (
                                        <div>
                                            <h4 className="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Question (MY)</h4>
                                            <p className="text-gray-900 dark:text-gray-100">{form.question_my}</p>
                                        </div>
                                    )}
                                    <div>
                                        <h4 className="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Answer (EN)</h4>
                                        <div className="prose prose-sm dark:prose-invert max-w-none" dangerouslySetInnerHTML={{ __html: form.answer_en || '<p class="italic text-gray-400">Not set</p>' }} />
                                    </div>
                                    {form.answer_my && (
                                        <div>
                                            <h4 className="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Answer (MY)</h4>
                                            <div className="prose prose-sm dark:prose-invert max-w-none" dangerouslySetInnerHTML={{ __html: form.answer_my }} />
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Category</label>
                                        <select value={form.category} onChange={(e) => setField('category', e.target.value)} className="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                            {Object.entries(categories).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                                        </select>
                                        {errors.category && <p className="mt-1 text-sm text-red-600">{errors.category}</p>}
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Question (English) *</label>
                                            <input type="text" value={form.question_en} onChange={(e) => setField('question_en', e.target.value)} className="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" placeholder="How do I get started?" />
                                            {errors.question_en && <p className="mt-1 text-sm text-red-600">{errors.question_en}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Question (Myanmar)</label>
                                            <input type="text" value={form.question_my} onChange={(e) => setField('question_my', e.target.value)} className="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" placeholder="ဘယ်လိုစတင်ရမလဲ?" />
                                            {errors.question_my && <p className="mt-1 text-sm text-red-600">{errors.question_my}</p>}
                                        </div>
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Answer (English) *</label>
                                        <RichTextEditor value={form.answer_en} onChange={(v) => setField('answer_en', v)} placeholder="Write the answer in English..." minHeight="180px" />
                                        {errors.answer_en && <p className="mt-1 text-sm text-red-600">{errors.answer_en}</p>}
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Answer (Myanmar)</label>
                                        <RichTextEditor value={form.answer_my} onChange={(v) => setField('answer_my', v)} placeholder="Myanmar ဘာသာဖြင့် ဖြေကြားပါ..." minHeight="180px" />
                                        {errors.answer_my && <p className="mt-1 text-sm text-red-600">{errors.answer_my}</p>}
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Sort Order</label>
                                            <input type="number" value={form.sort_order} onChange={(e) => setField('sort_order', parseInt(e.target.value) || 0)} min="0" className="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" />
                                        </div>
                                        <div className="flex items-center gap-3 pt-6">
                                            <button type="button" role="switch" aria-checked={form.is_active} onClick={() => setField('is_active', !form.is_active)} className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${form.is_active ? 'bg-blue-600' : 'bg-gray-300 dark:bg-gray-600'}`}>
                                                <span className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${form.is_active ? 'translate-x-6' : 'translate-x-1'}`} />
                                            </button>
                                            <span className="text-sm text-gray-700 dark:text-gray-300">{form.is_active ? 'Active' : 'Inactive'}</span>
                                        </div>
                                    </div>
                                </>
                            )}

                            <div className="flex items-center justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-800 sticky bottom-0 bg-white dark:bg-gray-900 pb-2">
                                <Link href={adminUrl('/admin/faqs')} className="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">Cancel</Link>
                                <button type="submit" disabled={processing} className="px-6 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors">
                                    {processing ? 'Creating...' : 'Create FAQ'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
