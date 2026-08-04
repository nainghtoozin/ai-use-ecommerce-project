import { useEditor, EditorContent } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';
import Underline from '@tiptap/extension-underline';
import TextAlign from '@tiptap/extension-text-align';
import Highlight from '@tiptap/extension-highlight';
import { TextStyle } from '@tiptap/extension-text-style';
import Color from '@tiptap/extension-color';
import { Table } from '@tiptap/extension-table';
import { TableRow } from '@tiptap/extension-table-row';
import { TableCell } from '@tiptap/extension-table-cell';
import { TableHeader } from '@tiptap/extension-table-header';
import { Blockquote } from '@tiptap/extension-blockquote';
import { CodeBlock } from '@tiptap/extension-code-block';
import { HorizontalRule } from '@tiptap/extension-horizontal-rule';
import { Placeholder } from '@tiptap/extension-placeholder';
import { useCallback, useEffect, useRef } from 'react';
import {
    Bold, Italic, Underline as UnderlineIcon, Strikethrough,
    Heading1, Heading2, Heading3,
    List, ListOrdered,
    Quote, Code, Minus,
    Link as LinkIcon, Image as ImageIcon,
    AlignLeft, AlignCenter, AlignRight, AlignJustify,
    Highlighter, Palette,
    Undo, Redo,
    Table as TableIcon,
    RemoveFormatting,
} from 'lucide-react';

function ToolbarButton({ onClick, isActive, disabled, children, title }) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            title={title}
            className={`p-1.5 rounded transition-colors ${
                isActive
                    ? 'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300'
                    : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-gray-200'
            } ${disabled ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer'}`}
        >
            {children}
        </button>
    );
}

function ToolbarDivider() {
    return <div className="w-px h-6 bg-gray-200 dark:bg-gray-700 mx-1" />;
}

function Toolbar({ editor }) {
    const fileInputRef = useRef(null);

    if (!editor) return null;

    const setLink = () => {
        const previousUrl = editor.getAttributes('link').href;
        const url = window.prompt('Enter URL:', previousUrl);
        if (url === null) return;
        if (url === '') {
            editor.chain().focus().extendMarkRange('link').unsetLink().run();
            return;
        }
        editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
    };

    const addImage = () => {
        const url = window.prompt('Enter image URL:');
        if (url) {
            editor.chain().focus().setImage({ src: url }).run();
        }
    };

    const handleFileUpload = async (e) => {
        const file = e.target.files?.[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('image', file);

        try {
            const response = await fetch('/admin/products/upload-image', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
            });
            const data = await response.json();
            if (data.url) {
                editor.chain().focus().setImage({ src: data.url }).run();
            }
        } catch (err) {
            console.error('Image upload failed:', err);
        }
        e.target.value = '';
    };

    const addTable = () => {
        editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run();
    };

    return (
        <div className="flex flex-wrap items-center gap-0.5 px-3 py-2 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 rounded-t-lg">
            <ToolbarButton onClick={() => editor.chain().focus().undo().run()} disabled={!editor.can().undo()} title="Undo">
                <Undo className="w-4 h-4" />
            </ToolbarButton>
            <ToolbarButton onClick={() => editor.chain().focus().redo().run()} disabled={!editor.can().redo()} title="Redo">
                <Redo className="w-4 h-4" />
            </ToolbarButton>

            <ToolbarDivider />

            <ToolbarButton onClick={() => editor.chain().focus().toggleHeading({ level: 1 }).run()} isActive={editor.isActive('heading', { level: 1 })} title="Heading 1">
                <Heading1 className="w-4 h-4" />
            </ToolbarButton>
            <ToolbarButton onClick={() => editor.chain().focus().toggleHeading({ level: 2 }).run()} isActive={editor.isActive('heading', { level: 2 })} title="Heading 2">
                <Heading2 className="w-4 h-4" />
            </ToolbarButton>
            <ToolbarButton onClick={() => editor.chain().focus().toggleHeading({ level: 3 }).run()} isActive={editor.isActive('heading', { level: 3 })} title="Heading 3">
                <Heading3 className="w-4 h-4" />
            </ToolbarButton>

            <ToolbarDivider />

            <ToolbarButton onClick={() => editor.chain().focus().toggleBold().run()} isActive={editor.isActive('bold')} title="Bold">
                <Bold className="w-4 h-4" />
            </ToolbarButton>
            <ToolbarButton onClick={() => editor.chain().focus().toggleItalic().run()} isActive={editor.isActive('italic')} title="Italic">
                <Italic className="w-4 h-4" />
            </ToolbarButton>
            <ToolbarButton onClick={() => editor.chain().focus().toggleUnderline().run()} isActive={editor.isActive('underline')} title="Underline">
                <UnderlineIcon className="w-4 h-4" />
            </ToolbarButton>
            <ToolbarButton onClick={() => editor.chain().focus().toggleStrike().run()} isActive={editor.isActive('strike')} title="Strikethrough">
                <Strikethrough className="w-4 h-4" />
            </ToolbarButton>
            <ToolbarButton onClick={() => editor.chain().focus().toggleHighlight().run()} isActive={editor.isActive('highlight')} title="Highlight">
                <Highlighter className="w-4 h-4" />
            </ToolbarButton>

            <ToolbarDivider />

            <ToolbarButton onClick={() => editor.chain().focus().toggleBulletList().run()} isActive={editor.isActive('bulletList')} title="Bullet List">
                <List className="w-4 h-4" />
            </ToolbarButton>
            <ToolbarButton onClick={() => editor.chain().focus().toggleOrderedList().run()} isActive={editor.isActive('orderedList')} title="Ordered List">
                <ListOrdered className="w-4 h-4" />
            </ToolbarButton>
            <ToolbarButton onClick={() => editor.chain().focus().toggleBlockquote().run()} isActive={editor.isActive('blockquote')} title="Blockquote">
                <Quote className="w-4 h-4" />
            </ToolbarButton>
            <ToolbarButton onClick={() => editor.chain().focus().toggleCodeBlock().run()} isActive={editor.isActive('codeBlock')} title="Code Block">
                <Code className="w-4 h-4" />
            </ToolbarButton>

            <ToolbarDivider />

            <ToolbarButton onClick={() => editor.chain().focus().setTextAlign('left').run()} isActive={editor.isActive({ textAlign: 'left' })} title="Align Left">
                <AlignLeft className="w-4 h-4" />
            </ToolbarButton>
            <ToolbarButton onClick={() => editor.chain().focus().setTextAlign('center').run()} isActive={editor.isActive({ textAlign: 'center' })} title="Align Center">
                <AlignCenter className="w-4 h-4" />
            </ToolbarButton>
            <ToolbarButton onClick={() => editor.chain().focus().setTextAlign('right').run()} isActive={editor.isActive({ textAlign: 'right' })} title="Align Right">
                <AlignRight className="w-4 h-4" />
            </ToolbarButton>
            <ToolbarButton onClick={() => editor.chain().focus().setTextAlign('justify').run()} isActive={editor.isActive({ textAlign: 'justify' })} title="Justify">
                <AlignJustify className="w-4 h-4" />
            </ToolbarButton>

            <ToolbarDivider />

            <ToolbarButton onClick={setLink} isActive={editor.isActive('link')} title="Link">
                <LinkIcon className="w-4 h-4" />
            </ToolbarButton>
            <ToolbarButton onClick={addImage} title="Image URL">
                <ImageIcon className="w-4 h-4" />
            </ToolbarButton>
            <ToolbarButton onClick={() => fileInputRef.current?.click()} title="Upload Image">
                <ImageIcon className="w-4 h-4" />
                <input ref={fileInputRef} type="file" accept="image/*" className="hidden" onChange={handleFileUpload} />
            </ToolbarButton>
            <ToolbarButton onClick={addTable} title="Insert Table">
                <TableIcon className="w-4 h-4" />
            </ToolbarButton>
            <ToolbarButton onClick={() => editor.chain().focus().setHorizontalRule().run()} title="Horizontal Rule">
                <Minus className="w-4 h-4" />
            </ToolbarButton>

            <ToolbarDivider />

            <ToolbarButton onClick={() => editor.chain().focus().clearNodes().unsetAllMarks().run()} title="Clear Formatting">
                <RemoveFormatting className="w-4 h-4" />
            </ToolbarButton>

            <div className="ml-auto flex items-center gap-1">
                <label className="text-xs text-gray-500 dark:text-gray-400 mr-1">Color:</label>
                <input
                    type="color"
                    onChange={(e) => editor.chain().focus().setColor(e.target.value).run()}
                    value={editor.getAttributes('textStyle').color || '#000000'}
                    className="w-6 h-6 rounded cursor-pointer border-0 p-0"
                    title="Text Color"
                />
            </div>
        </div>
    );
}

export default function RichTextEditor({
    value = '',
    onChange,
    placeholder = 'Start writing...',
    className = '',
    minHeight = '200px',
}) {
    const editor = useEditor({
        extensions: [
            StarterKit.configure({
                heading: { levels: [1, 2, 3] },
            }),
            Link.configure({ openOnClick: false }),
            Image.configure({ inline: false, allowBase64: true }),
            Underline,
            TextAlign.configure({ types: ['heading', 'paragraph'] }),
            Highlight.configure({ multicolor: false }),
            TextStyle,
            Color,
            Table.configure({ resizable: false }),
            TableRow,
            TableCell,
            TableHeader,
            Blockquote,
            CodeBlock,
            HorizontalRule,
            Placeholder.configure({ placeholder }),
        ],
        content: value,
        onUpdate: ({ editor }) => {
            onChange?.(editor.getHTML());
        },
        editorProps: {
            attributes: {
                class: 'prose prose-sm sm:prose max-w-none dark:prose-invert focus:outline-none px-4 py-3',
                style: `min-height: ${minHeight}`,
            },
        },
    });

    useEffect(() => {
        if (editor && value !== editor.getHTML()) {
            editor.commands.setContent(value || '', false);
        }
    }, [value]);

    return (
        <div className={`border border-gray-300 dark:border-gray-700 rounded-lg overflow-hidden bg-white dark:bg-gray-900 ${className}`}>
            <Toolbar editor={editor} />
            <div className="editor-content">
                <EditorContent editor={editor} />
            </div>
            <style>{`
                .editor-content .tiptap p.is-editor-empty:first-child::before {
                    color: #9ca3af;
                    content: attr(data-placeholder);
                    float: left;
                    height: 0;
                    pointer-events: none;
                }
                .editor-content .tiptap table {
                    border-collapse: collapse;
                    margin: 1rem 0;
                    width: 100%;
                }
                .editor-content .tiptap td,
                .editor-content .tiptap th {
                    border: 1px solid #d1d5db;
                    padding: 0.5rem 0.75rem;
                    text-align: left;
                }
                .editor-content .tiptap th {
                    background-color: #f3f4f6;
                    font-weight: 600;
                }
                .dark .editor-content .tiptap td,
                .dark .editor-content .tiptap th {
                    border-color: #374151;
                }
                .dark .editor-content .tiptap th {
                    background-color: #1f2937;
                }
                .editor-content .tiptap blockquote {
                    border-left: 3px solid #d1d5db;
                    padding-left: 1rem;
                    margin: 1rem 0;
                    color: #6b7280;
                }
                .editor-content .tiptap pre {
                    background: #1f2937;
                    color: #e5e7eb;
                    padding: 1rem;
                    border-radius: 0.5rem;
                    overflow-x: auto;
                }
                .editor-content .tiptap hr {
                    border: none;
                    border-top: 1px solid #e5e7eb;
                    margin: 1.5rem 0;
                }
                .dark .editor-content .tiptap hr {
                    border-top-color: #374151;
                }
                .editor-content .tiptap img {
                    max-width: 100%;
                    height: auto;
                    border-radius: 0.5rem;
                }
                .editor-content .tiptap a {
                    color: #2563eb;
                    text-decoration: underline;
                }
            `}</style>
        </div>
    );
}
