import { useState, useCallback, useRef } from 'react';
import MediaDropzone from '../media/MediaDropzone';
import ImageThumbnail from '../media/ImageThumbnail';

export default function MediaSection({
    data,
    setData,
    errors,
    photo1File,
    setPhoto1File,
    photo2File,
    setPhoto2File,
    existingPhoto1Url = null,
    existingPhoto2Url = null,
}) {
    const [galleryFiles, setGalleryFiles] = useState([]);
    const [uploadingIds, setUploadingIds] = useState(new Set());
    const [dragOverIndex, setDragOverIndex] = useState(null);
    const dragItemRef = useRef(null);

    const MAX_IMAGES = 10;
    const totalImages = [photo1File, photo2File, ...galleryFiles].filter(Boolean).length;
    const hasExisting1 = existingPhoto1Url && !photo1File;
    const hasExisting2 = existingPhoto2Url && !photo2File;

    /* ── Featured image handlers ── */
    const handleFeaturedChange = useCallback((file) => {
        setPhoto1File(file);
    }, [setPhoto1File]);

    const handleFeaturedRemove = useCallback(() => {
        setPhoto1File('remove');
    }, [setPhoto1File]);

    /* ── Secondary image handlers ── */
    const handleSecondaryChange = useCallback((file) => {
        setPhoto2File(file);
    }, [setPhoto2File]);

    const handleSecondaryRemove = useCallback(() => {
        setPhoto2File('remove');
    }, [setPhoto2File]);

    /* ── Gallery handlers ── */
    const handleGalleryAdd = useCallback((files) => {
        const newIds = files.map((_, i) => Date.now() + i);
        setGalleryFiles((prev) => [...prev, ...files]);
    }, []);

    const handleGalleryRemove = useCallback((index) => {
        setGalleryFiles((prev) => prev.filter((_, i) => i !== index));
    }, []);

    /* ── Promote gallery image to featured ── */
    const handleSetFeatured = useCallback((galleryIndex) => {
        const galleryFile = galleryFiles[galleryIndex];
        if (galleryFile) {
            setPhoto1File(galleryFile);
            setGalleryFiles((prev) => prev.filter((_, i) => i !== galleryIndex));
        }
    }, [galleryFiles, setPhoto1File]);

    /* ── Drag reorder for gallery ── */
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

    const allMedia = [
        { type: 'featured', file: photo1File },
        { type: 'secondary', file: photo2File },
        ...galleryFiles.map((f, i) => ({ type: 'gallery', file: f, index: i })),
    ].filter((m) => m.file);

    return (
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            {/* Header */}
            <div className="px-5 py-4 border-b border-gray-100">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="flex-shrink-0 w-8 h-8 rounded-lg bg-purple-50 flex items-center justify-center">
                            <svg className="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div>
                            <h3 className="text-base font-semibold text-gray-900">Media</h3>
                            <p className="text-xs text-gray-500 mt-0.5">
                                {allMedia.length}/{MAX_IMAGES} images · Drag to reorder
                            </p>
                        </div>
                    </div>
                    {allMedia.length > 0 && (
                        <span className="text-xs text-gray-400">
                            First image is the featured thumbnail
                        </span>
                    )}
                </div>
            </div>

            <div className="px-5 py-5 space-y-6">
                {/* ── Featured Image ── */}
                <div>
                    <div className="flex items-center justify-between mb-3">
                        <div>
                            <h4 className="text-sm font-semibold text-gray-900">Featured Image</h4>
                            <p className="text-xs text-gray-500 mt-0.5">Main product image shown in listings</p>
                        </div>
                    </div>

                    {photo1File ? (
                        <div className="flex gap-3">
                            <div className="w-32 sm:w-40 flex-shrink-0">
                                <ImageThumbnail
                                    file={photo1File}
                                    index={0}
                                    onRemove={handleFeaturedRemove}
                                    isFeatured
                                />
                            </div>
                            <div className="flex-1 min-w-0 flex flex-col justify-center">
                                <p className="text-sm font-medium text-gray-700 truncate">
                                    {photo1File.name}
                                </p>
                                <p className="text-xs text-gray-400 mt-0.5">
                                    {(photo1File.size / 1024 / 1024).toFixed(2)} MB
                                </p>
                                <div className="mt-3 flex gap-2">
                                    <button
                                        type="button"
                                        onClick={() => {
                                            const input = document.createElement('input');
                                            input.type = 'file';
                                            input.accept = 'image/*';
                                            input.onchange = (e) => {
                                                const file = e.target.files?.[0];
                                                if (file) handleFeaturedChange(file);
                                            };
                                            input.click();
                                        }}
                                        className="text-xs text-blue-600 hover:text-blue-700 font-medium"
                                    >
                                        Replace
                                    </button>
                                    <button
                                        type="button"
                                        onClick={handleFeaturedRemove}
                                        className="text-xs text-red-600 hover:text-red-700 font-medium"
                                    >
                                        Remove
                                    </button>
                                </div>
                            </div>
                        </div>
                    ) : hasExisting1 ? (
                        <div className="flex gap-3">
                            <div className="w-32 sm:w-40 flex-shrink-0">
                                <img
                                    src={existingPhoto1Url}
                                    alt="Featured"
                                    className="w-32 sm:w-40 h-32 sm:h-40 rounded-lg object-cover border border-gray-200"
                                />
                            </div>
                            <div className="flex-1 min-w-0 flex flex-col justify-center">
                                <p className="text-sm font-medium text-gray-700">Current featured image</p>
                                <p className="text-xs text-gray-400 mt-0.5">Uploaded previously</p>
                                <div className="mt-3 flex gap-2">
                                    <button
                                        type="button"
                                        onClick={() => {
                                            const input = document.createElement('input');
                                            input.type = 'file';
                                            input.accept = 'image/*';
                                            input.onchange = (e) => {
                                                const file = e.target.files?.[0];
                                                if (file) handleFeaturedChange(file);
                                            };
                                            input.click();
                                        }}
                                        className="text-xs text-blue-600 hover:text-blue-700 font-medium"
                                    >
                                        Replace
                                    </button>
                                    <button
                                        type="button"
                                        onClick={handleFeaturedRemove}
                                        className="text-xs text-red-600 hover:text-red-700 font-medium"
                                    >
                                        Remove
                                    </button>
                                </div>
                            </div>
                        </div>
                    ) : (
                        <MediaDropzone
                            onFilesAdd={(files) => handleFeaturedChange(files[0])}
                            maxFiles={1}
                            existingCount={allMedia.length}
                            error={errors.photo1}
                        />
                    )}
                </div>

                {/* ── Divider ── */}
                <div className="border-t border-gray-100" />

                {/* ── Gallery ── */}
                <div>
                    <div className="flex items-center justify-between mb-3">
                        <div>
                            <h4 className="text-sm font-semibold text-gray-900">
                                Gallery
                                {galleryFiles.length > 0 && (
                                    <span className="ml-1.5 text-xs font-normal text-gray-400">
                                        ({galleryFiles.length} image{galleryFiles.length !== 1 ? 's' : ''})
                                    </span>
                                )}
                            </h4>
                            <p className="text-xs text-gray-500 mt-0.5">Additional product images</p>
                        </div>
                        {galleryFiles.length > 0 && (
                            <button
                                type="button"
                                onClick={() => setGalleryFiles([])}
                                className="text-xs text-red-600 hover:text-red-700 font-medium"
                            >
                                Clear all
                            </button>
                        )}
                    </div>

                    {hasExisting2 && (
                        <div className="flex gap-3 mb-4">
                            <div className="w-24 h-24 flex-shrink-0">
                                <img
                                    src={existingPhoto2Url}
                                    alt="Gallery"
                                    className="w-24 h-24 rounded-lg object-cover border border-gray-200"
                                />
                            </div>
                            <div className="flex-1 min-w-0 flex flex-col justify-center">
                                <p className="text-sm font-medium text-gray-700">Current gallery image</p>
                                <p className="text-xs text-gray-400 mt-0.5">Uploaded previously</p>
                                <div className="mt-2 flex gap-2">
                                    <button
                                        type="button"
                                        onClick={() => {
                                            const input = document.createElement('input');
                                            input.type = 'file';
                                            input.accept = 'image/*';
                                            input.onchange = (e) => {
                                                const file = e.target.files?.[0];
                                                if (file) handleSecondaryChange(file);
                                            };
                                            input.click();
                                        }}
                                        className="text-xs text-blue-600 hover:text-blue-700 font-medium"
                                    >
                                        Replace
                                    </button>
                                    <button
                                        type="button"
                                        onClick={handleSecondaryRemove}
                                        className="text-xs text-red-600 hover:text-red-700 font-medium"
                                    >
                                        Remove
                                    </button>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Gallery Grid */}
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
                                    className={`
                                        transition-all duration-150
                                        ${dragOverIndex === index ? 'scale-95 opacity-50' : ''}
                                    `}
                                >
                                    <ImageThumbnail
                                        file={file}
                                        index={index}
                                        onRemove={handleGalleryRemove}
                                        onSetFeatured={handleSetFeatured}
                                        canSetFeatured={!photo1File}
                                    />
                                </div>
                            ))}
                        </div>
                    )}

                    {/* Upload area */}
                    {totalImages < MAX_IMAGES && (
                        <MediaDropzone
                            onFilesAdd={handleGalleryAdd}
                            maxFiles={MAX_IMAGES}
                            existingCount={totalImages}
                            error={errors.photo2}
                        />
                    )}

                    {totalImages >= MAX_IMAGES && (
                        <div className="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-center">
                            <p className="text-sm text-gray-500">Maximum {MAX_IMAGES} images reached</p>
                        </div>
                    )}
                </div>

                {/* ── Image Tips ── */}
                <div className="rounded-lg bg-blue-50 border border-blue-100 px-4 py-3">
                    <div className="flex gap-2">
                        <svg className="w-4 h-4 text-blue-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div className="text-xs text-blue-700 space-y-1">
                            <p className="font-medium">Image recommendations</p>
                            <ul className="list-disc list-inside space-y-0.5 text-blue-600">
                                <li>Use high-resolution images (at least 1024×1024px)</li>
                                <li>First image becomes the product thumbnail</li>
                                <li>Supported formats: JPG, PNG, WEBP (max 2MB)</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
