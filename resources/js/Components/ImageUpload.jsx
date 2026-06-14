import { useState, useEffect, useRef } from 'react';
import { Upload, X, Image } from 'lucide-react';
import getImagePreviewUrl from '@/utils/getImagePreviewUrl';

export default function ImageUpload({
    name,
    label,
    value = null,
    onChange,
    error = null,
    accept = 'image/*',
    maxSize = 2,
}) {
    const [isDragging, setIsDragging] = useState(false);
    const inputRef = useRef(null);

    const previewUrl = getImagePreviewUrl(value);

    useEffect(() => {
        return () => {
            if (previewUrl && previewUrl.startsWith('blob:')) {
                URL.revokeObjectURL(previewUrl);
            }
        };
    }, [previewUrl]);

    const handleFile = (file) => {
        if (!file) return;

        if (!file.type.startsWith('image/')) {
            alert('Please select an image file.');
            return;
        }

        const maxSizeBytes = maxSize * 1024 * 1024;
        if (file.size > maxSizeBytes) {
            alert(`File size must be under ${maxSize}MB.`);
            return;
        }

        if (onChange) {
            onChange(file);
        }
    };

    const handleInputChange = (e) => {
        const file = e.target.files?.[0];
        handleFile(file);
    };

    const handleDragOver = (e) => {
        e.preventDefault();
        setIsDragging(true);
    };

    const handleDragLeave = (e) => {
        e.preventDefault();
        setIsDragging(false);
    };

    const handleDrop = (e) => {
        e.preventDefault();
        setIsDragging(false);
        const file = e.dataTransfer.files?.[0];
        handleFile(file);
    };

    const handleRemove = () => {
        if (inputRef.current) inputRef.current.value = '';
        if (onChange) onChange(null);
    };

    const handleClick = () => {
        inputRef.current?.click();
    };

    return (
        <div>
            {label && (
                <label className="block text-sm font-medium text-gray-700 mb-2">
                    {label}
                    {maxSize && <span className="text-gray-400 font-normal ml-1">(max {maxSize}MB)</span>}
                </label>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* Preview */}
                <div className="relative aspect-square rounded-xl overflow-hidden border border-gray-200 bg-gray-50 group">
                    {previewUrl ? (
                        <>
                            <img
                                src={previewUrl}
                                alt="Preview"
                                className="w-full h-full object-cover"
                            />
                            <button
                                type="button"
                                onClick={handleRemove}
                                className="absolute top-2 right-2 bg-white/90 hover:bg-white text-gray-600 hover:text-red-600 rounded-full w-8 h-8 flex items-center justify-center shadow-sm opacity-0 group-hover:opacity-100 transition-all"
                            >
                                <X className="w-4 h-4" />
                            </button>
                        </>
                    ) : (
                        <div className="w-full h-full flex flex-col items-center justify-center text-gray-400">
                            <Image className="w-12 h-12 mb-2" />
                            <span className="text-sm">No image</span>
                        </div>
                    )}
                </div>

                {/* Upload Area */}
                <div className="flex flex-col">
                    <input
                        ref={inputRef}
                        type="file"
                        name={name}
                        accept={accept}
                        onChange={handleInputChange}
                        className="hidden"
                    />

                    <div
                        onDragOver={handleDragOver}
                        onDragLeave={handleDragLeave}
                        onDrop={handleDrop}
                        onClick={handleClick}
                        className={`
                            flex-1 min-h-[200px] border-2 border-dashed rounded-xl p-6
                            flex flex-col items-center justify-center cursor-pointer transition-all
                            ${isDragging
                                ? 'border-blue-500 bg-blue-50 scale-[1.02]'
                                : 'border-gray-300 hover:border-blue-400 hover:bg-blue-50/50'
                            }
                            ${error ? 'border-red-300 bg-red-50' : ''}
                        `}
                    >
                        <div className={`w-14 h-14 rounded-xl flex items-center justify-center mb-4 transition-colors ${isDragging ? 'bg-blue-100' : 'bg-gray-100'}`}>
                            <Upload className={`w-6 h-6 ${isDragging ? 'text-blue-600' : 'text-gray-400'}`} />
                        </div>
                        <p className="text-sm text-gray-700 font-medium">
                            <span className="text-blue-600 hover:text-blue-500">
                                Click to upload
                            </span>
                            {' '}or drag and drop
                        </p>
                        <p className="mt-1 text-xs text-gray-400">
                            PNG, JPG, WEBP up to {maxSize}MB
                        </p>
                    </div>

                    {error && (
                        <p className="mt-2 text-sm text-red-600">{error}</p>
                    )}
                </div>
            </div>
        </div>
    );
}
