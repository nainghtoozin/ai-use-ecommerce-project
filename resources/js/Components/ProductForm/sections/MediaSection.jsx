import { useState, useCallback, useRef } from 'react';
import MediaDropzone from '../media/MediaDropzone';
import ImageThumbnail from '../media/ImageThumbnail';

export default function MediaSection({
    errors,
    photo1File,
    setPhoto1File,
    photo2File,
    setPhoto2File,
    existingPhoto1Url = null,
    existingPhoto2Url = null,
}) {
    const [galleryFiles, setGalleryFiles] = useState([]);
    const [dragOverIndex, setDragOverIndex] = useState(null);
    const dragItemRef = useRef(null);

    const MAX_IMAGES = 10;
    const totalImages = [photo1File, photo2File, ...galleryFiles].filter(Boolean).length;
    const hasExisting2 = existingPhoto2Url && !photo2File;

    const handleSecondaryChange = useCallback((file) => {
        setPhoto2File(file);
    }, [setPhoto2File]);

    const handleSecondaryRemove = useCallback(() => {
        setPhoto2File('remove');
    }, [setPhoto2File]);

    const handleGalleryAdd = useCallback((files) => {
        setGalleryFiles((prev) => [...prev, ...files]);
    }, []);

    const handleGalleryRemove = useCallback((index) => {
        const removed = galleryFiles[index];
        setGalleryFiles((prev) => prev.filter((_, i) => i !== index));
        if (removed && photo1File === removed) {
            setPhoto1File(null);
        }
    }, [galleryFiles, photo1File, setPhoto1File]);

    const handleSetFeatured = useCallback((index) => {
        const file = galleryFiles[index];
        if (file) {
            setPhoto1File(file);
        }
    }, [galleryFiles, setPhoto1File]);

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
                        {galleryFiles.length > 0 && (
                            <span className="ml-1.5 text-xs font-normal text-gray-400">
                                ({galleryFiles.length}/{MAX_IMAGES})
                            </span>
                        )}
                    </h4>
                    <p className="text-xs text-gray-500 mt-0.5">Maximum 10 images</p>
                </div>
                {galleryFiles.length > 0 && (
                    <button
                        type="button"
                        onClick={() => {
                            setGalleryFiles([]);
                            if (photo1File && galleryFiles.includes(photo1File)) {
                                setPhoto1File(null);
                            }
                        }}
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
                            onRemove={handleGalleryRemove}
                            isFeatured={photo1File === file}
                            canSetFeatured={true}
                            onSetFeatured={handleSetFeatured}
                        />
                    </div>
                ))}
            </div>

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
        </>
    );
}
