import { useState, useMemo, useRef, useEffect } from 'react';
import { TIMEZONES } from '@/Data/timezones';

export default function TimezoneSelect({ value, onChange, error }) {
  const [query, setQuery] = useState('');
  const [open, setOpen] = useState(false);
  const [focusedIdx, setFocusedIdx] = useState(-1);
  const containerRef = useRef(null);
  const inputRef = useRef(null);

  const filtered = useMemo(() => {
    if (!query) return TIMEZONES;
    const q = query.toLowerCase();
    return TIMEZONES.filter(tz => tz.toLowerCase().includes(q));
  }, [query]);

  const displayValue = value || '';

  useEffect(() => {
    const handleClickOutside = (e) => {
      if (containerRef.current && !containerRef.current.contains(e.target)) {
        setOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  useEffect(() => {
    if (!open) {
      setFocusedIdx(-1);
    }
  }, [open]);

  const select = (tz) => {
    onChange(tz);
    setQuery('');
    setOpen(false);
    inputRef.current?.focus();
  };

  const handleKeyDown = (e) => {
    if (!open) {
      if (e.key === 'ArrowDown' || e.key === 'Enter') {
        setOpen(true);
        e.preventDefault();
      }
      return;
    }

    switch (e.key) {
      case 'ArrowDown':
        e.preventDefault();
        setFocusedIdx(prev => Math.min(prev + 1, filtered.length - 1));
        break;
      case 'ArrowUp':
        e.preventDefault();
        setFocusedIdx(prev => Math.max(prev - 1, 0));
        break;
      case 'Enter':
        e.preventDefault();
        if (focusedIdx >= 0 && focusedIdx < filtered.length) {
          select(filtered[focusedIdx]);
        }
        break;
      case 'Escape':
        setOpen(false);
        break;
    }
  };

  return (
    <div ref={containerRef} className="relative col-span-1">
      <label className="block text-sm font-medium text-gray-700 mb-1">Timezone</label>
      <input
        ref={inputRef}
        type="text"
        value={open ? query : displayValue}
        onChange={(e) => { setQuery(e.target.value); setOpen(true); }}
        onFocus={() => setOpen(true)}
        onKeyDown={handleKeyDown}
        placeholder="Search timezone..."
        className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[var(--theme-color,#3B82F6)]"
      />
      {open && (
        <ul className="absolute z-50 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto">
          {filtered.length === 0 ? (
            <li className="px-4 py-2 text-sm text-gray-400">No matching timezones</li>
          ) : (
            filtered.map((tz, i) => (
              <li
                key={tz}
                onClick={() => select(tz)}
                onMouseEnter={() => setFocusedIdx(i)}
                className={`px-4 py-2 text-sm cursor-pointer ${
                  i === focusedIdx ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-50'
                } ${tz === value && !open ? 'font-semibold' : ''}`}
              >
                {tz}
              </li>
            ))
          )}
        </ul>
      )}
      {error && <p className="mt-1 text-sm text-red-600">{error}</p>}
    </div>
  );
}
