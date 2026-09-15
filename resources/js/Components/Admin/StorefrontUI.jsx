import { Link } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, ChevronLeft, Globe, Inbox, Info, Plus, XCircle } from 'lucide-react';

export const SAVE_STATUS = {
    IDLE: 'idle',
    UNSAVED: 'unsaved',
    SAVING: 'saving',
    SAVED: 'saved',
    FAILED: 'failed',
};

export const SAVE_STATUS_LABEL = {
    idle: 'All changes saved',
    unsaved: 'Unsaved changes',
    saving: 'Saving…',
    saved: '✓ Draft saved',
    failed: "Couldn't save changes",
};

export const SAVE_STATUS_TONE = {
    idle: 'text-emerald-600 dark:text-emerald-400',
    unsaved: 'text-amber-600 dark:text-amber-400',
    saving: 'text-blue-600 dark:text-blue-400',
    saved: 'text-emerald-600 dark:text-emerald-400',
    failed: 'text-red-600 dark:text-red-400',
};

export const FIELD_INPUT = 'mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 transition-colors focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500';
export const FIELD_SELECT = 'mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 pl-3 pr-9 py-2 text-sm text-gray-900 dark:text-gray-100 transition-colors focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500';
export const FIELD_CHECKBOX = 'rounded border-gray-300 dark:border-gray-700 text-blue-600 focus:ring-blue-500';

const NOTICE_TONE = {
    success: {
        role: 'status',
        icon: CheckCircle2,
        classes: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-300',
    },
    error: {
        role: 'alert',
        icon: XCircle,
        classes: 'border-red-200 bg-red-50 text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300',
    },
    warning: {
        role: 'alert',
        icon: AlertTriangle,
        classes: 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300',
    },
    info: {
        role: 'status',
        icon: Info,
        classes: 'border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-800 dark:bg-blue-900/20 dark:text-blue-300',
    },
};

const BUTTON_BASE = 'inline-flex items-center justify-center gap-1.5 rounded-lg font-semibold transition-colors disabled:opacity-50 shadow-sm';
const BUTTON_SIZES = {
    md: 'px-4 py-2 text-sm',
    sm: 'px-3 py-1.5 text-xs',
};

export function PageHeader({ eyebrow, title, subtitle, actions, compact = false, tight = false }) {
    return (
        <div className={`flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 ${tight ? 'mb-4' : 'mb-6'}`}>
            <div>
                {eyebrow && <p className="text-sm font-medium text-blue-600 dark:text-blue-400">{eyebrow}</p>}
                <h1 className={`font-bold text-gray-900 dark:text-gray-100 mt-1 ${compact ? 'text-xl' : 'text-2xl'}`}>{title}</h1>
                {subtitle && <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">{subtitle}</p>}
            </div>
            {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
        </div>
    );
}

export function SectionHeader({ title, description, actions }) {
    return (
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 className="text-lg font-bold text-gray-900 dark:text-gray-100">{title}</h2>
                {description && <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">{description}</p>}
            </div>
            {actions && <div className="flex flex-wrap items-center gap-2 shrink-0">{actions}</div>}
        </div>
    );
}

export function FormGroup({ title, description, children }) {
    return (
        <div>
            <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">{title}</h3>
            {description && <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5 mb-3">{description}</p>}
            {!description && <div className="mb-3" />}
            {children}
        </div>
    );
}

export function BackLink({ href, children }) {
    return (
        <Link href={href} className="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg px-2 py-1 -ml-2 mb-4 transition-colors">
            <ChevronLeft className="w-4 h-4" />
            {children}
        </Link>
    );
}

export function DraftBadge({ children = 'Draft', className = '' }) {
    return (
        <span className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300 text-[11px] font-semibold uppercase tracking-wide ${className}`}>
            <span className="w-1.5 h-1.5 rounded-full bg-current" />
            {children}
        </span>
    );
}

export function LiveBadge({ children = 'Live', className = '' }) {
    return (
        <span className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300 text-[11px] font-semibold uppercase tracking-wide ${className}`}>
            <span className="w-1.5 h-1.5 rounded-full bg-current" />
            {children}
        </span>
    );
}

export function StatusPill({ active, activeLabel = 'Active', inactiveLabel = 'Inactive', size = 'md', className = '' }) {
    const sizeClasses = size === 'xs'
        ? 'px-2 py-0.5 text-[10px] uppercase tracking-wide'
        : 'px-2.5 py-1 text-xs';
    const toneClasses = active
        ? 'bg-green-100 text-green-700 dark:bg-emerald-900/30 dark:text-emerald-300'
        : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300';
    return (
        <span className={`inline-flex items-center gap-1.5 rounded-full font-medium ${sizeClasses} ${toneClasses} ${className}`}>
            <span className="w-1.5 h-1.5 rounded-full bg-current" />
            {active ? activeLabel : inactiveLabel}
        </span>
    );
}

export function SaveStatusText({ status, onRetry }) {
    if (status === SAVE_STATUS.FAILED && onRetry) {
        return (
            <span className={`text-[11px] font-medium whitespace-nowrap ${SAVE_STATUS_TONE[status]}`}>
                <button type="button" onClick={onRetry} className="underline hover:no-underline">
                    {SAVE_STATUS_LABEL[status]} — Retry
                </button>
            </span>
        );
    }
    return (
        <span className={`text-[11px] font-medium whitespace-nowrap ${SAVE_STATUS_TONE[status] || SAVE_STATUS_TONE.idle}`}>
            {SAVE_STATUS_LABEL[status] || SAVE_STATUS_LABEL.idle}
        </span>
    );
}

export function Notice({ tone = 'success', children, className = '', dense = false }) {
    const config = NOTICE_TONE[tone] || NOTICE_TONE.success;
    const Icon = config.icon;
    return (
        <div role={config.role} className={`rounded-lg border text-sm ${dense ? 'p-3' : 'p-4'} ${config.classes} ${className}`}>
            <div className="flex items-start gap-2.5">
                <Icon className="w-5 h-5 shrink-0 mt-px" />
                <div className="flex-1 min-w-0">{children}</div>
            </div>
        </div>
    );
}

export function FormCard({ children, className = '' }) {
    return (
        <div className={`bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm p-4 sm:p-6 ${className}`}>
            {children}
        </div>
    );
}

export function EditorCard({ children, className = '', dense = false, ...rest }) {
    return (
        <section className={`bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm ${dense ? 'p-4' : 'p-4 sm:p-6'} ${className}`} {...rest}>
            {children}
        </section>
    );
}

export function TableCard({ children, className = '' }) {
    return (
        <div className={`bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden ${className}`}>
            {children}
        </div>
    );
}

export function TH({ align = 'left', children }) {
    const alignClasses = align === 'center' ? 'text-center' : align === 'right' ? 'text-right' : 'text-left';
    return (
        <th className={`px-6 py-3 ${alignClasses} text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide`}>
            {children}
        </th>
    );
}

export function TD({ align = 'left', variant, children, className = '' }) {
    const alignClasses = align === 'center' ? 'text-center' : align === 'right' ? 'text-right' : 'text-left';
    const variantClasses = variant === 'strong'
        ? 'font-medium text-gray-900 dark:text-gray-100'
        : variant === 'muted'
            ? 'text-gray-500 dark:text-gray-500'
            : `text-gray-600 dark:text-gray-400${variant === 'num' ? ' tabular-nums' : ''}`;
    return (
        <td className={`px-6 py-4 text-sm ${alignClasses} ${variantClasses} ${className}`}>
            {children}
        </td>
    );
}

export function TableEmptyState({ colSpan, title, hint, action }) {
    return (
        <tr>
            <td colSpan={colSpan} className="px-6 py-12 text-center">
                <div className="flex flex-col items-center gap-2">
                    <span className="w-10 h-10 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                        <Inbox className="w-5 h-5 text-gray-400 dark:text-gray-500" />
                    </span>
                    <p className="text-sm font-medium text-gray-700 dark:text-gray-300">{title}</p>
                    {hint && <p className="text-xs text-gray-500 dark:text-gray-400">{hint}</p>}
                    {action}
                </div>
            </td>
        </tr>
    );
}

const INPUT_BASE = 'w-full rounded-lg border px-3 py-2 text-sm text-gray-900 dark:text-gray-100 bg-white dark:bg-gray-800 placeholder-gray-400 dark:placeholder-gray-500 transition-colors focus:outline-none focus:ring-1';
const INPUT_TONE = {
    normal: 'border-gray-300 dark:border-gray-700 focus:border-blue-500 focus:ring-blue-500',
    error: 'border-red-300 dark:border-red-700 focus:border-red-500 focus:ring-red-500',
};

export function FieldError({ error }) {
    if (!error) return null;
    return <p className="mt-1 text-sm text-red-600 dark:text-red-400">{error}</p>;
}

export function TextInput({ id, label, error, helpText, required, ...props }) {
    return (
        <div>
            {label && (
                <label htmlFor={id} className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    {label}
                    {required && <span className="text-red-500 ml-0.5">*</span>}
                </label>
            )}
            <input
                id={id}
                className={`${INPUT_BASE} ${error ? INPUT_TONE.error : INPUT_TONE.normal}`}
                {...props}
            />
            <FieldError error={error} />
            {helpText && !error && <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{helpText}</p>}
        </div>
    );
}

export function TextareaInput({ id, label, error, helpText, required, rows = 3, ...props }) {
    return (
        <div>
            {label && (
                <label htmlFor={id} className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    {label}
                    {required && <span className="text-red-500 ml-0.5">*</span>}
                </label>
            )}
            <textarea
                id={id}
                rows={rows}
                className={`${INPUT_BASE} ${error ? INPUT_TONE.error : INPUT_TONE.normal}`}
                {...props}
            />
            <FieldError error={error} />
            {helpText && !error && <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{helpText}</p>}
        </div>
    );
}

export function SelectInput({ id, label, error, helpText, required, children, ...props }) {
    return (
        <div>
            {label && (
                <label htmlFor={id} className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    {label}
                    {required && <span className="text-red-500 ml-0.5">*</span>}
                </label>
            )}
            <select
                id={id}
                className={`${INPUT_BASE} ${error ? INPUT_TONE.error : INPUT_TONE.normal}`}
                {...props}
            >
                {children}
            </select>
            <FieldError error={error} />
            {helpText && !error && <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{helpText}</p>}
        </div>
    );
}

export function CheckboxRow({ id, label, ...props }) {
    return (
        <div className="flex items-center gap-2">
            <input
                id={id}
                type="checkbox"
                className={FIELD_CHECKBOX}
                {...props}
            />
            <label htmlFor={id} className="text-sm font-medium text-gray-700 dark:text-gray-300">{label}</label>
        </div>
    );
}

export function Switch({ label, checked, onChange, labelVisible = true }) {
    return (
        <label className="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300 cursor-pointer">
            <button
                type="button"
                role="switch"
                aria-checked={checked}
                aria-label={label}
                onClick={() => onChange(!checked)}
                className={`relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 ${checked ? 'bg-blue-600' : 'bg-gray-300 dark:bg-gray-600'}`}
            >
                <span className={`pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${checked ? 'translate-x-4' : 'translate-x-0'}`} />
            </button>
            {labelVisible ? label : <span className="sr-only">{label}</span>}
        </label>
    );
}

export function IconButton({ label, onClick, disabled, children }) {
    return (
        <button
            type="button"
            title={label}
            aria-label={label}
            onClick={onClick}
            disabled={disabled}
            className="w-9 h-9 inline-flex items-center justify-center rounded-lg border border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-200 disabled:opacity-30 transition-colors shrink-0"
        >
            {children}
        </button>
    );
}

export function PrimaryButton({ size = 'md', className = '', ...props }) {
    return (
        <button
            type="submit"
            className={`${BUTTON_BASE} ${BUTTON_SIZES[size] || BUTTON_SIZES.md} bg-blue-600 text-white hover:bg-blue-700 ${className}`}
            {...props}
        />
    );
}

export function SuccessButton({ size = 'md', className = '', ...props }) {
    return (
        <button
            type="button"
            className={`${BUTTON_BASE} ${BUTTON_SIZES[size] || BUTTON_SIZES.md} bg-green-600 text-white hover:bg-green-700 ${className}`}
            {...props}
        />
    );
}

export function OutlineButton({ size = 'md', className = '', ...props }) {
    return (
        <button
            type="button"
            className={`${BUTTON_BASE} ${BUTTON_SIZES[size] || BUTTON_SIZES.md} bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 ${className}`}
            {...props}
        />
    );
}

export function PrimaryLink({ href, size = 'md', icon = true, className = '', children }) {
    return (
        <Link
            href={href}
            className={`${BUTTON_BASE} ${BUTTON_SIZES[size] || BUTTON_SIZES.md} bg-blue-600 text-white hover:bg-blue-700 ${className}`}
        >
            {icon && <Plus className="w-4 h-4" />}
            {children}
        </Link>
    );
}

export function CancelLink({ href, children = 'Cancel' }) {
    return (
        <Link href={href} className="inline-flex items-center justify-center px-4 py-2 text-sm text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-gray-100 transition-colors">
            {children}
        </Link>
    );
}

export function PublishConfirmModal({ title, description, confirmLabel = 'Publish Changes', processing = false, onCancel, onConfirm }) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div className="w-full max-w-md rounded-xl bg-white dark:bg-gray-900 p-6 shadow-xl">
                <div className="flex items-start gap-3">
                    <span className="w-10 h-10 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center shrink-0">
                        <Globe className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                    </span>
                    <div className="min-w-0">
                        <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">{title}</h2>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{description}</p>
                    </div>
                </div>
                <div className="flex justify-end gap-2 mt-6">
                    <OutlineButton onClick={onCancel}>Cancel</OutlineButton>
                    <SuccessButton onClick={onConfirm} disabled={processing}>
                        {processing ? 'Publishing…' : confirmLabel}
                    </SuccessButton>
                </div>
            </div>
        </div>
    );
}

export function UnauthorizedState({ message }) {
    return (
        <div className="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-6 lg:py-8">
            <Notice tone="error" className="p-6 text-center">
                <p className="font-medium">{message}</p>
            </Notice>
        </div>
    );
}

export function EmptyState({ children }) {
    return (
        <div className="text-center py-16 text-gray-500 dark:text-gray-400 text-sm rounded-xl border border-dashed border-gray-300 dark:border-gray-700">
            {children}
        </div>
    );
}
