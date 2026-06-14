import { useState, useCallback, useRef } from 'react';
import MediaDropzone from '../media/MediaDropzone';
import ImageThumbnail from '../media/ImageThumbnail';
import getImagePreviewUrl from '@/Utils/getImagePreviewUrl';

export default function MediaSection({
    errors,
    photo1File,
    setPhoto1File,
    existingPhoto1Url = null,
    existingGalleryImages = [],
    galleryFiles,
    setGalleryFiles,
    removedGalleryImages,
    setRemovedGalleryImages,
}) {
    const [dragOverIndex, setDragOverIndex] = useState(null);
    const dragItemRef = useRef(null);

    const MAX_IMAGES = 10;

    const existingCount = existingGalleryImages.filter(
        (_, idx) => !removedGalleryImages.includes(existingGalleryImages[idx])
    ).length;

    const totalImages = existingCount + galleryFiles.length;

    const handleGalleryAdd = useCallback((files) => {
        setGalleryFiles((prev) => [...prev, ...files]);
    }, [setGalleryFiles]);

    const handleNewGalleryRemove = useCallback((index) => {
        setGalleryFiles((prev) => prev.filter((_, i) => i !== index));
    }, [setGalleryFiles]);

    const handleExistingRemove = useCallback((index) => {
        const path = existingGalleryImages[index];
        setRemovedGalleryImages((prev) => [...prev, path]);
    }, [existingGalleryImages, setRemovedGalleryImages]);

    const handleDragStart = (index) => {
        dragItemRef.current = index;
    };

    const handleDragOver = (e, index) => {
        e.preventDefault();
        if (dragItemRef.current === null || dragItemRef.current === index) return;
        setDragOverIndex(index);
    };

    const handleDragLeave = () => {
        setDragOverIndex(null);
    };

    const handleDrop = (dropIndex) => {
        const dragIndex = dragItemRef.current;
        if (dragIndex === null || dragIndex === dropIndex) {
            setDragOverIndex(null);
            return;
        }
        setGalleryFiles((prev) => {
            const next = [...prev];
            const [dragged] = next.splice(dragIndex, 1);
            next.splice(dropIndex, 0, dragged);
            return next;
        });
        dragItemRef.current = null;
        setDragOverIndex(null);
    };

    return (
        <>
            <div className="flex items-center justify-between mb-3">
                <div>
                    <h4 className="text-sm font-semibold text-gray-900">
                        Gallery Images
                        {totalImages > 0 && (
                            <span className="ml-1.5 text-xs font-normal text-gray-400">
                                ({totalImages}/{MAX_IMAGES})
                            </span>
                        )}
                    </h4>
                    <p className="text-xs text-gray-500 mt-0.5">Maximum 10 images</p>
                </div>
                {totalImages > 0 && (
                    <button
                        type="button"
                        onClick={() => {
                            setGalleryFiles([]);
                            setRemovedGalleryImages([...existingGalleryImages]);
                        }}
                        className="text-xs text-red-600 hover:text-red-700 font-medium"
                    >
                        Clear all
                    </button>
                )}
            </div>

            {/* Existing Gallery Images */}
            {existingGalleryImages.length > 0 && (
                <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 mb-4">
                    {existingGalleryImages.map((path, index) => {
                        const isRemoved = removedGalleryImages.includes(path);
                        return (
                            <div
                                key={index}
                                className={`relative aspect-square rounded-xl overflow-hidden border-2 transition-all duration-150 ${
                                    isRemoved
                                        ? 'border-red-300 opacity-50'
                                        : 'border-gray-200'
                                }`}
                            >
                                <img
                                    src={getImagePreviewUrl(path)}
                                    alt={`Gallery ${index + 1}`}
                                    className="w-full h-full object-cover"
                                />
                                {!isRemoved && (
                                    <div className="absolute inset-0 bg-black/40 opacity-0 hover:opacity-100 transition-opacity flex items-center justify-center">
                                        <button
                                            type="button"
                                            onClick={() => handleExistingRemove(index)}
                                            className="w-8 h-8 flex items-center justify-center rounded-lg bg-white/90 hover:bg-white text-gray-700 hover:text-red-600 transition-colors shadow-sm"
                                            title="Remove image"
                                        >
                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                )}
                                {isRemoved && (
                                    <div className="absolute inset-0 flex items-center justify-center">
                                        <span className="text-xs font-medium text-red-600 bg-white/90 px-2 py-1 rounded-md">Removed</span>
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            )}

            {/* New Gallery Uploads */}
            {galleryFiles.length > 0 && (
                <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 mb-4">
                    {galleryFiles.map((file, index) => (
                        <div
                            key={index}
                            draggable
                            onDragStart={() => handleDragStart(index)}
                            onDragOver={(e) => handleDragOver(e, index)}
                            onDragLeave={handleDragLeave}
                            onDrop={() => handleDrop(index)}
                            className={`transition-all duration-150 ${dragOverIndex === index ? 'scale-95 opacity-50' : ''}`}
                        >
                            <ImageThumbnail
                                file={file}
                                index={index}
                                onRemove={handleNewGalleryRemove}
                                isFeatured={false}
                                canSetFeatured={false}
                            />
                        </div>
                    ))}
                </div>
            )}

            {totalImages < MAX_IMAGES && (
                <MediaDropzone
                    onFilesAdd={handleGalleryAdd}
                    maxFiles={MAX_IMAGES}
                    existingCount={totalImages}
                    error={errors.gallery_images}
                />
            )}

            {totalImages >= MAX_IMAGES && (
                <div className="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-center">
                    <p className="text-sm text-gray-500">Maximum {MAX_IMAGES} images reached</p>
                </div>
            )}
        </>
    );
}
