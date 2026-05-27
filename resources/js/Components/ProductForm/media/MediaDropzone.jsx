import { useState, useRef, useCallback } from 'react';
import { Upload, Image, X, AlertCircle } from 'lucide-react';

const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
const MAX_FILE_SIZE = 2 * 1024 * 1024; // 2MB

export default function MediaDropzone({
    onFilesAdd,
    maxFiles = 10,
    maxSizeMB = 2,
    existingCount = 0,
    error,
}) {
    const [isDragging, setIsDragging] = useState(false);
    const [uploadErrors, setUploadErrors] = useState([]);
    const inputRef = useRef(null);
    const dragCounterRef = useRef(0);

    const remaining = maxFiles - existingCount;

    const validateAndProcess = useCallback((fileList) => {
        const errors = [];
        const validFiles = [];

        const files = Array.from(fileList);

        if (remaining <= 0) {
            errors.push(`Maximum ${maxFiles} images reached`);
            setUploadErrors(errors);
            return;
        }

        const toProcess = files.slice(0, remaining);

        toProcess.forEach((file) => {
            if (!ACCEPTED_TYPES.includes(file.type)) {
                errors.push(`${file.name}: unsupported format`);
                return;
            }
            if (file.size > maxSizeMB * 1024 * 1024) {
                errors.push(`${file.name}: exceeds ${maxSizeMB}MB limit`);
                return;
            }
            validFiles.push(file);
        });

        setUploadErrors(errors);
        if (validFiles.length > 0) {
            onFilesAdd(validFiles);
        }
    }, [remaining, maxFiles, maxSizeMB, onFilesAdd]);

    const handleDragEnter = (e) => {
        e.preventDefault();
        e.stopPropagation();
        dragCounterRef.current++;
        if (dragCounterRef.current === 1) setIsDragging(true);
    };

    const handleDragLeave = (e) => {
        e.preventDefault();
        e.stopPropagation();
        dragCounterRef.current--;
        if (dragCounterRef.current === 0) setIsDragging(false);
    };

    const handleDragOver = (e) => {
        e.preventDefault();
        e.stopPropagation();
    };

    const handleDrop = (e) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(false);
        dragCounterRef.current = 0;
        if (e.dataTransfer.files?.length) {
            validateAndProcess(e.dataTransfer.files);
        }
    };

    const handleClick = () => {
        inputRef.current?.click();
    };

    const handleInputChange = (e) => {
        if (e.target.files?.length) {
            validateAndProcess(e.target.files);
        }
        if (inputRef.current) inputRef.current.value = '';
    };

    return (
        <div>
            <div
                onDragEnter={handleDragEnter}
                onDragLeave={handleDragLeave}
                onDragOver={handleDragOver}
                onDrop={handleDrop}
                onClick={handleClick}
                className={`
                    relative flex flex-col items-center justify-center rounded-xl border-2 border-dashed
                    px-4 py-8 text-center cursor-pointer transition-all duration-200
                    ${isDragging
                        ? 'border-blue-500 bg-blue-50 scale-[1.01]'
                        : 'border-gray-200 bg-gray-50/50 hover:border-gray-300 hover:bg-gray-50'
                    }
                    ${error ? 'border-red-300 bg-red-50/50' : ''}
                `}
            >
                <input
                    ref={inputRef}
                    type="file"
                    accept="image/jpeg,image/png,image/webp,image/gif"
                    multiple={maxFiles > 1}
                    onChange={handleInputChange}
                    className="hidden"
                />

                <div className={`
                    w-10 h-10 rounded-xl flex items-center justify-center mb-3 transition-colors
                    ${isDragging ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-400'}
                `}>
                    <Upload className="w-5 h-5" />
                </div>

                <p className="text-sm text-gray-700 mb-1">
                    <span className="font-medium text-blue-600">Click to upload</span>
                    {' '}or drag and drop
                </p>
                <p className="text-xs text-gray-400">
                    JPG, PNG, WEBP or GIF (max {maxSizeMB}MB)
                </p>
                {remaining < maxFiles && (
                    <p className="text-xs text-gray-400 mt-1">
                        {remaining} slot{remaining !== 1 ? 's' : ''} remaining
                    </p>
                )}
            </div>

            {/* Upload errors */}
            {uploadErrors.length > 0 && (
                <div className="mt-3 space-y-1">
                    {uploadErrors.map((err, i) => (
                        <div key={i} className="flex items-center gap-1.5 text-xs text-red-600">
                            <AlertCircle className="w-3.5 h-3.5 flex-shrink-0" />
                            <span>{err}</span>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
