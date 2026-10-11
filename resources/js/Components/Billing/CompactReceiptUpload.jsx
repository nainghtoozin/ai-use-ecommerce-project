import { useEffect, useRef, useState } from 'react';
import { Upload, X, FileImage, Eye, RefreshCw, AlertCircle } from 'lucide-react';

const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
const ACCEPT_ATTR = 'image/jpeg,image/png,image/jpg,image/gif';
const MAX_BYTES = 5 * 1024 * 1024;

function formatSize(bytes) {
    if (bytes >= 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
    return `${(bytes / 1024).toFixed(1)} KB`;
}

export default function CompactReceiptUpload({ file, onSelect, onRemove, onError, error }) {
    const inputRef = useRef(null);
    const [dragging, setDragging] = useState(false);
    const [previewOpen, setPreviewOpen] = useState(false);
    const [previewUrl, setPreviewUrl] = useState(null);

    useEffect(() => {
        if (!file) {
            setPreviewUrl(null);
            return;
        }
        const url = URL.createObjectURL(file);
        setPreviewUrl(url);
        return () => URL.revokeObjectURL(url);
    }, [file]);

    const accept = (f) => {
        if (!f) return;
        if (!ACCEPTED_TYPES.includes(f.type)) {
            onError?.('Unsupported file type. Please upload a PNG, JPG or GIF image.');
            return;
        }
        if (f.size > MAX_BYTES) {
            onError?.('File is too large. Maximum size is 5MB.');
            return;
        }
        onSelect?.(f);
    };

    const handleDrop = (e) => {
        e.preventDefault();
        setDragging(false);
        accept(e.dataTransfer.files?.[0]);
    };

    const handleChange = (e) => {
        accept(e.target.files?.[0]);
        e.target.value = '';
    };

    return (
        <div>
            <input
                ref={inputRef}
                type="file"
                accept={ACCEPT_ATTR}
                onChange={handleChange}
                className="hidden"
                aria-hidden="true"
                tabIndex={-1}
            />

            {file ? (
                <div className="flex items-center gap-3 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-2.5">
                    <button
                        type="button"
                        onClick={() => setPreviewOpen(true)}
                        className="relative flex-shrink-0 w-14 h-14 rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-800 group"
                        aria-label="Preview receipt"
                    >
                        {previewUrl && <img src={previewUrl} alt="Receipt preview" className="w-full h-full object-cover" />}
                        <span className="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                            <Eye className="w-4 h-4 text-white" />
                        </span>
                    </button>

                    <div className="flex-1 min-w-0">
                        <p className="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{file.name}</p>
                        <p className="text-xs text-gray-500 dark:text-gray-400">{formatSize(file.size)}</p>
                    </div>

                    <div className="flex items-center gap-1.5 flex-shrink-0">
                        <button
                            type="button"
                            onClick={() => inputRef.current?.click()}
                            className="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                        >
                            <RefreshCw className="w-3.5 h-3.5" /> Replace
                        </button>
                        <button
                            type="button"
                            onClick={() => onRemove?.()}
                            className="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition-colors"
                        >
                            <X className="w-3.5 h-3.5" /> Remove
                        </button>
                    </div>
                </div>
            ) : (
                <button
                    type="button"
                    onClick={() => inputRef.current?.click()}
                    onDragOver={(e) => { e.preventDefault(); setDragging(true); }}
                    onDragLeave={() => setDragging(false)}
                    onDrop={handleDrop}
                    className={`w-full rounded-xl border-2 border-dashed px-4 py-4 text-left transition-colors ${
                        dragging ? 'border-blue-400 bg-blue-50' : error ? 'border-red-300 bg-red-50' : 'border-gray-300 hover:border-gray-400 bg-gray-50/50'
                    }`}
                >
                    <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded-lg bg-gray-100 dark:bg-gray-800 flex items-center justify-center flex-shrink-0">
                            <FileImage className="w-5 h-5 text-gray-400 dark:text-gray-500" />
                        </div>
                        <div>
                            <p className="text-sm font-medium text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
                                <Upload className="w-3.5 h-3.5" /> Upload receipt
                            </p>
                            <p className="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Click or drag &amp; drop — PNG, JPG or GIF, max 5MB</p>
                        </div>
                    </div>
                </button>
            )}

            {previewOpen && previewUrl && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
                    onClick={() => setPreviewOpen(false)}
                    role="dialog"
                    aria-modal="true"
                    aria-label="Receipt preview"
                >
                    <div className="relative max-w-3xl max-h-[90vh]" onClick={(e) => e.stopPropagation()}>
                        <img src={previewUrl} alt="Receipt preview" className="max-h-[85vh] max-w-full rounded-lg object-contain" />
                        <button
                            type="button"
                            onClick={() => setPreviewOpen(false)}
                            className="absolute -top-3 -right-3 w-8 h-8 bg-white text-gray-700 rounded-full flex items-center justify-center shadow-lg hover:bg-gray-100"
                            aria-label="Close preview"
                        >
                            <X className="w-4 h-4" />
                        </button>
                        <p className="text-center text-xs text-white/80 mt-2">{file?.name} — {formatSize(file?.size || 0)}</p>
                    </div>
                </div>
            )}
        </div>
    );
}
