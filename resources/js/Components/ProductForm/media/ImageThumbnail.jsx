import { useState, useEffect } from 'react';
import { X, GripVertical, Star, StarOff, Loader2 } from 'lucide-react';
import getImagePreviewUrl from '@/utils/getImagePreviewUrl';

export default function ImageThumbnail({
    file,
    existingUrl,
    index,
    onRemove,
    isFeatured = false,
    onSetFeatured,
    canSetFeatured = false,
    uploading = false,
}) {
    const [previewUrl, setPreviewUrl] = useState(null);
    const [isHovered, setIsHovered] = useState(false);

    useEffect(() => {
        if (file) {
            const url = URL.createObjectURL(file);
            setPreviewUrl(url);
            return () => URL.revokeObjectURL(url);
        }
        return () => {};
    }, [file]);

    const src = previewUrl || existingUrl || null;

    return (
        <div
            className={`
                group relative aspect-square rounded-xl overflow-hidden border-2 transition-all duration-150
                ${isFeatured
                    ? 'border-blue-500 ring-2 ring-blue-500/20'
                    : 'border-gray-200 hover:border-gray-300'
                }
                ${uploading ? 'opacity-70' : ''}
            `}
            onMouseEnter={() => setIsHovered(true)}
            onMouseLeave={() => setIsHovered(false)}
        >
            {/* Image */}
            {src ? (
                <img
                    src={src}
                    alt={file?.name || 'Product image'}
                    className="w-full h-full object-cover"
                />
            ) : (
                <div className="w-full h-full bg-gray-100 flex items-center justify-center">
                    <Image className="w-8 h-8 text-gray-300" />
                </div>
            )}

            {/* Uploading overlay */}
            {uploading && (
                <div className="absolute inset-0 bg-black/30 flex items-center justify-center">
                    <Loader2 className="w-6 h-6 text-white animate-spin" />
                </div>
            )}

            {/* Hover overlay */}
            {isHovered && !uploading && (
                <div className="absolute inset-0 bg-black/40 transition-opacity">
                    <div className="absolute inset-0 flex items-center justify-center gap-2">
                        {/* Remove button */}
                        <button
                            type="button"
                            onClick={(e) => {
                                e.stopPropagation();
                                onRemove(index);
                            }}
                            className="w-8 h-8 flex items-center justify-center rounded-lg bg-white/90 hover:bg-white text-gray-700 hover:text-red-600 transition-colors shadow-sm"
                            title="Remove image"
                        >
                            <X className="w-4 h-4" />
                        </button>
                    </div>

                    {/* Set featured button (for gallery items) */}
                    {canSetFeatured && !isFeatured && (
                        <div className="absolute bottom-1.5 right-1.5">
                            <button
                                type="button"
                                onClick={(e) => {
                                    e.stopPropagation();
                                    onSetFeatured(index);
                                }}
                                className="w-7 h-7 flex items-center justify-center rounded-md bg-white/90 hover:bg-white text-gray-500 hover:text-amber-500 transition-colors shadow-sm"
                                title="Set as featured"
                            >
                                <StarOff className="w-3.5 h-3.5" />
                            </button>
                        </div>
                    )}
                </div>
            )}

            {/* Featured badge */}
            {isFeatured && (
                <div className="absolute top-1.5 left-1.5">
                    <span className="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md bg-blue-600 text-white text-[10px] font-medium shadow-sm">
                        <Star className="w-3 h-3" />
                        Featured
                    </span>
                </div>
            )}

            {/* Drag handle (always visible on hover) */}
            {isHovered && (
                <div className="absolute top-1.5 right-1.5 cursor-grab active:cursor-grabbing">
                    <div className="w-6 h-6 flex items-center justify-center rounded-md bg-white/90 text-gray-400 shadow-sm">
                        <GripVertical className="w-3.5 h-3.5" />
                    </div>
                </div>
            )}
        </div>
    );
}

function Image({ className }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
    );
}
