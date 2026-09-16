import { useState, useEffect, useRef, useCallback } from 'react';
import { X, ZoomIn, ZoomOut, Maximize, Minimize, ChevronLeft, ChevronRight } from 'lucide-react';

const ZOOM_STEPS = [1, 1.5, 2, 3];

function ControlButton({ label, onClick, children }) {
    return (
        <button
            type="button"
            aria-label={label}
            onClick={(e) => { e.stopPropagation(); onClick(); }}
            className="p-2 rounded-full bg-white/10 text-white hover:bg-white/25 transition-colors"
        >
            {children}
        </button>
    );
}

export default function PromotionImageViewer({ images = [], index = 0, onClose, onIndex }) {
    const [zoomIdx, setZoomIdx] = useState(0);
    const [isFullscreen, setIsFullscreen] = useState(false);
    const [visible, setVisible] = useState(false);
    const containerRef = useRef(null);
    const touchRef = useRef({ x: 0, pinch: 0 });
    const zoom = ZOOM_STEPS[zoomIdx] ?? 1;
    const hasMany = images.length > 1;

    useEffect(() => {
        const frame = requestAnimationFrame(() => setVisible(true));
        return () => cancelAnimationFrame(frame);
    }, []);

    useEffect(() => { setZoomIdx(0); }, [index]);

    useEffect(() => {
        const prev = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => { document.body.style.overflow = prev; };
    }, []);

    useEffect(() => {
        const onFullscreenChange = () => setIsFullscreen(Boolean(document.fullscreenElement));
        document.addEventListener('fullscreenchange', onFullscreenChange);
        return () => document.removeEventListener('fullscreenchange', onFullscreenChange);
    }, []);

    const handleClose = useCallback(() => {
        setVisible(false);
        if (document.fullscreenElement) document.exitFullscreen().catch(() => {});
        setTimeout(onClose, 150);
    }, [onClose]);

    const goTo = useCallback((next) => {
        if (!images.length) return;
        onIndex((next + images.length) % images.length);
    }, [images.length, onIndex]);

    const prev = useCallback(() => goTo(index - 1), [goTo, index]);
    const next = useCallback(() => goTo(index + 1), [goTo, index]);

    useEffect(() => {
        const onKey = (e) => {
            if (e.key === 'Escape') handleClose();
            else if (e.key === 'ArrowRight' && hasMany) next();
            else if (e.key === 'ArrowLeft' && hasMany) prev();
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [handleClose, hasMany, next, prev]);

    const zoomIn = useCallback(() => setZoomIdx((i) => Math.min(i + 1, ZOOM_STEPS.length - 1)), []);
    const zoomOut = useCallback(() => setZoomIdx((i) => Math.max(i - 1, 0)), []);

    const toggleFullscreen = useCallback(() => {
        if (document.fullscreenElement) {
            document.exitFullscreen().catch(() => {});
        } else {
            containerRef.current?.requestFullscreen?.().catch(() => {});
        }
    }, []);

    const handleWheel = (e) => {
        if (e.deltaY < 0) zoomIn();
        else if (e.deltaY > 0) zoomOut();
    };

    const handleTouchStart = (e) => {
        const touches = e.touches;
        if (touches.length === 1) {
            touchRef.current = { x: touches[0].clientX, pinch: 0 };
        } else if (touches.length === 2) {
            touchRef.current = {
                x: 0,
                pinch: Math.hypot(touches[0].clientX - touches[1].clientX, touches[0].clientY - touches[1].clientY),
            };
        }
    };

    const handleTouchMove = (e) => {
        const touches = e.touches;
        if (touches.length === 2 && touchRef.current.pinch > 0) {
            const distance = Math.hypot(touches[0].clientX - touches[1].clientX, touches[0].clientY - touches[1].clientY);
            if (distance - touchRef.current.pinch > 24) {
                zoomIn();
                touchRef.current.pinch = distance;
            } else if (touchRef.current.pinch - distance > 24) {
                zoomOut();
                touchRef.current.pinch = distance;
            }
        }
    };

    const handleTouchEnd = (e) => {
        const startX = touchRef.current.x;
        touchRef.current = { x: 0, pinch: 0 };
        if (!startX || zoom !== 1 || !hasMany) return;
        const dx = e.changedTouches[0].clientX - startX;
        if (Math.abs(dx) > 48) {
            if (dx < 0) next();
            else prev();
        }
    };

    const current = images[index];
    if (!current) return null;

    return (
        <div
            ref={containerRef}
            role="dialog"
            aria-modal="true"
            aria-label="Promotion image viewer"
            onClick={handleClose}
            className="fixed inset-0 z-[100] bg-black/90 flex flex-col transition-opacity duration-150"
            style={{ opacity: visible ? 1 : 0 }}
        >
            <div className="flex items-center justify-between p-3 sm:p-4" onClick={(e) => e.stopPropagation()}>
                <span className="text-sm text-white/80 tabular-nums min-w-[3rem]">
                    {hasMany ? `${index + 1} / ${images.length}` : ''}
                </span>
                <div className="flex items-center gap-1.5">
                    <ControlButton label="Zoom out" onClick={zoomOut}><ZoomOut className="w-5 h-5" /></ControlButton>
                    <ControlButton label="Zoom in" onClick={zoomIn}><ZoomIn className="w-5 h-5" /></ControlButton>
                    <ControlButton label={isFullscreen ? 'Exit fullscreen' : 'Fullscreen'} onClick={toggleFullscreen}>
                        {isFullscreen ? <Minimize className="w-5 h-5" /> : <Maximize className="w-5 h-5" />}
                    </ControlButton>
                    <ControlButton label="Close viewer" onClick={handleClose}><X className="w-5 h-5" /></ControlButton>
                </div>
            </div>

            <div
                className="relative flex-1 flex items-center justify-center overflow-hidden px-1 sm:px-14 pb-2"
                onClick={(e) => e.stopPropagation()}
                onWheel={handleWheel}
                onTouchStart={handleTouchStart}
                onTouchMove={handleTouchMove}
                onTouchEnd={handleTouchEnd}
            >
                {hasMany && (
                    <>
                        <button type="button" aria-label="Previous image" onClick={(e) => { e.stopPropagation(); prev(); }} className="absolute left-2 sm:left-4 z-10 p-2 rounded-full bg-white/10 text-white hover:bg-white/25 transition-colors">
                            <ChevronLeft className="w-6 h-6" />
                        </button>
                        <button type="button" aria-label="Next image" onClick={(e) => { e.stopPropagation(); next(); }} className="absolute right-2 sm:right-4 z-10 p-2 rounded-full bg-white/10 text-white hover:bg-white/25 transition-colors">
                            <ChevronRight className="w-6 h-6" />
                        </button>
                    </>
                )}
                <img
                    key={current.src}
                    src={current.src}
                    alt={current.alt || 'Promotion image'}
                    draggable={false}
                    className="max-w-full max-h-full object-contain select-none transition-transform duration-150"
                    style={{ transform: `scale(${zoom})` }}
                />
            </div>

            {hasMany && (
                <div className="flex justify-start sm:justify-center gap-2 px-4 pb-4 overflow-x-auto" onClick={(e) => e.stopPropagation()}>
                    {images.map((img, i) => (
                        <button
                            key={`${img.src}-${i}`}
                            type="button"
                            aria-label={`View image ${i + 1}`}
                            onClick={() => onIndex(i)}
                            className={`w-14 h-14 shrink-0 rounded-lg overflow-hidden border-2 transition ${i === index ? 'border-white' : 'border-transparent opacity-50 hover:opacity-100'}`}
                        >
                            <img src={img.src} alt="" className="w-full h-full object-cover pointer-events-none" />
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
