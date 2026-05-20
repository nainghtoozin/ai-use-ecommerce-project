import { useState, useEffect, useRef } from 'react';
import getImagePreviewUrl from '@/utils/getImagePreviewUrl';

export default function ImageUpload({
    name,
    label,
    value = null,
    onChange,
    error = null,
    accept = 'image/*',
    maxSize = 2,
    previewSize = 'md',
}) {
    const [isDragging, setIsDragging] = useState(false);
    const inputRef = useRef(null);

    const previewUrl = getImagePreviewUrl(value);

    const previewSizes = {
        sm: 'w-24 h-24',
        md: 'w-40 h-40',
        lg: 'w-64 h-64',
        full: 'w-full h-64',
    };

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

            <div className="flex items-start gap-4">
                <div className={`${previewSizes[previewSize]} flex-shrink-0 relative group`}>
                    {previewUrl ? (
                        <div className="relative w-full h-full rounded-lg overflow-hidden border border-gray-200 bg-gray-50">
                            <img
                                src={previewUrl}
                                alt={label || 'Preview'}
                                className="w-full h-full object-cover"
                            />
                            <button
                                type="button"
                                onClick={handleRemove}
                                className="absolute top-1 right-1 bg-red-500 hover:bg-red-600 text-white rounded-full w-6 h-6 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity shadow"
                            >
                                <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    ) : (
                        <div className={`w-full h-full ${previewSizes[previewSize]} rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 flex items-center justify-center`}>
                            <svg className="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                    )}
                </div>

                <div className="flex-1 min-w-0">
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
                            relative border-2 border-dashed rounded-lg p-4 cursor-pointer transition-colors
                            ${isDragging
                                ? 'border-blue-500 bg-blue-50'
                                : 'border-gray-300 hover:border-gray-400 hover:bg-gray-50'
                            }
                            ${error ? 'border-red-300 bg-red-50' : ''}
                        `}
                    >
                        <div className="text-center">
                            <svg className="mx-auto h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3" />
                            </svg>
                            <p className="mt-1 text-sm text-gray-600">
                                <span className="font-medium text-blue-600 hover:text-blue-500">
                                    Click to upload
                                </span>
                                {' '}or drag and drop
                            </p>
                            <p className="mt-1 text-xs text-gray-400">
                                PNG, JPG, WEBP up to {maxSize}MB
                            </p>
                        </div>
                    </div>

                    {error && (
                        <p className="mt-1 text-sm text-red-600">{error}</p>
                    )}
                </div>
            </div>
        </div>
    );
}