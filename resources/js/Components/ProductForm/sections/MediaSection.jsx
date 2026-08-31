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
    onGalleryOrderChange,
}) {
    const [dragOverIndex, setDragOverIndex] = useState(null);
    const [dragSource, setDragSource] = useState(null);
    const dragIndexRef = useRef(null);

    const MAX_IMAGES = 10;

    const visibleExistingImages = existingGalleryImages.filter(
        (path) => !removedGalleryImages.includes(path)
    );
    const existingCount = visibleExistingImages.length;
    const totalImages = existingCount + galleryFiles.length;

    const handleGalleryAdd = useCallback((files) => {
        setGalleryFiles((prev) => [...prev, ...files]);
    }, [setGalleryFiles]);

    const handleNewGalleryRemove = useCallback((index) => {
        setGalleryFiles((prev) => prev.filter((_, i) => i !== index));
    }, [setGalleryFiles]);

    const handleExistingRemove = useCallback((path) => {
        const newOrder = visibleExistingImages.filter((p) => p !== path);
        setRemovedGalleryImages((prev) => [...prev, path]);
        onGalleryOrderChange?.(newOrder);
    }, [visibleExistingImages, setRemovedGalleryImages, onGalleryOrderChange]);

    const handleExistingDragStart = (e, index) => {
        dragIndexRef.current = index;
        setDragSource('existing');
        e.dataTransfer.effectAllowed = 'move';
    };

    const handleExistingDragOver = (e, index) => {
        e.preventDefault();
        if (dragIndexRef.current === null || dragSource !== 'existing') return;
        if (dragIndexRef.current === index) return;
        setDragOverIndex(index);
    };

    const handleNewDragOver = (e, index) => {
        e.preventDefault();
        if (dragIndexRef.current === null || dragSource !== 'new') return;
        if (dragIndexRef.current === index) return;
        setDragOverIndex(index);
    };

    const handleDragLeave = () => {
        setDragOverIndex(null);
    };

    const handleExistingDrop = (dropIndex) => {
        const dragIndex = dragIndexRef.current;
        if (dragIndex === null || dragSource !== 'existing') {
            handleDragEnd();
            return;
        }
        if (dragIndex === dropIndex) {
            handleDragEnd();
            return;
        }

        const newOrder = [...visibleExistingImages];
        const [dragged] = newOrder.splice(dragIndex, 1);
        newOrder.splice(dropIndex, 0, dragged);
        onGalleryOrderChange?.(newOrder);

        handleDragEnd();
    };

    const handleNewDrop = (dropIndex) => {
        const dragIndex = dragIndexRef.current;
        if (dragIndex === null || dragSource !== 'new') {
            handleDragEnd();
            return;
        }
        if (dragIndex === dropIndex) {
            handleDragEnd();
            return;
        }

        setGalleryFiles((prev) => {
            const next = [...prev];
            const [dragged] = next.splice(dragIndex, 1);
            next.splice(dropIndex, 0, dragged);
            return next;
        });

        handleDragEnd();
    };

    const handleDragEnd = () => {
        dragIndexRef.current = null;
        setDragSource(null);
        setDragOverIndex(null);
    };

    return (
        <>
            <div className="flex items-center justify-between mb-3">
                <div>
                    <h4 className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                        Gallery Images
                        {totalImages > 0 && (
                            <span className="ml-1.5 text-xs font-normal text-gray-400 dark:text-gray-500">
                                ({totalImages}/{MAX_IMAGES})
                            </span>
                        )}
                    </h4>
                    <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Maximum 10 images</p>
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
            {visibleExistingImages.length > 0 && (
                <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 mb-4">
                    {visibleExistingImages.map((path, index) => {
                        return (
                            <div
                                key={path}
                                draggable
                                onDragStart={(e) => handleExistingDragStart(e, index)}
                                onDragOver={(e) => handleExistingDragOver(e, index)}
                                onDragLeave={handleDragLeave}
                                onDrop={() => handleExistingDrop(index)}
                                onDragEnd={handleDragEnd}
                                className={`relative aspect-square rounded-xl overflow-hidden border-2 transition-all duration-150 cursor-grab active:cursor-grabbing ${
                                    dragOverIndex === index && dragSource === 'existing'
                                        ? 'border-blue-500 scale-95 opacity-50'
                                        : 'border-gray-200 hover:border-gray-300'
                                }`}
                            >
                                <img
                                    src={getImagePreviewUrl(path)}
                                    alt={`Gallery ${index + 1}`}
                                    className="w-full h-full object-cover"
                                />
                                <div className="absolute inset-0 bg-black/40 opacity-0 hover:opacity-100 transition-opacity flex items-center justify-center">
                                    <button
                                        type="button"
                                        onClick={() => handleExistingRemove(path)}
                                        className="w-8 h-8 flex items-center justify-center rounded-lg bg-white dark:bg-gray-900/90 hover:bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 hover:text-red-600 transition-colors shadow-sm"
                                        title="Remove image"
                                    >
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                                <div className="absolute top-1.5 left-1.5">
                                    <span className="inline-flex items-center px-1.5 py-0.5 rounded-md bg-black/60 text-white text-[10px] font-medium">
                                        {index + 1}
                                    </span>
                                </div>
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
                            key={`new-${index}`}
                            draggable
                            onDragStart={(e) => {
                                dragIndexRef.current = index;
                                setDragSource('new');
                                e.dataTransfer.effectAllowed = 'move';
                            }}
                            onDragOver={(e) => handleNewDragOver(e, index)}
                            onDragLeave={handleDragLeave}
                            onDrop={() => handleNewDrop(index)}
                            onDragEnd={handleDragEnd}
                            className={`transition-all duration-150 ${dragOverIndex === index && dragSource === 'new' ? 'scale-95 opacity-50' : ''}`}
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
                <div className="rounded-lg border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 px-4 py-3 text-center">
                    <p className="text-sm text-gray-500 dark:text-gray-400">Maximum {MAX_IMAGES} images reached</p>
                </div>
            )}
        </>
    );
}
