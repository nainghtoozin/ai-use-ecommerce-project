export default function RichContent({ content, className = '' }) {
    if (!content) {
        return (
            <div className="text-center py-12">
                <div className="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                    <i className="bi bi-file-text text-2xl text-gray-400 dark:text-gray-500"></i>
                </div>
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    This page has no content yet.
                </p>
            </div>
        );
    }

    return (
        <div
            className={`prose prose-gray dark:prose-invert max-w-none
                prose-headings:scroll-mt-20
                prose-a:text-blue-600 dark:prose-a:text-blue-400 prose-a:no-underline hover:prose-a:underline
                prose-img:rounded-xl prose-img:shadow-md
                prose-blockquote:border-blue-500 prose-blockquote:bg-blue-50 dark:prose-blockquote:bg-blue-900/20 prose-blockquote:py-1 prose-blockquote:px-4 prose-blockquote:rounded-r-lg
                prose-code:bg-gray-100 dark:prose-code:bg-gray-800 prose-code:px-1.5 prose-code:py-0.5 prose-code:rounded prose-code:text-sm prose-code:font-mono
                prose-pre:bg-gray-900 dark:prose-pre:bg-gray-950 prose-pre:rounded-xl prose-pre:shadow-md
                prose-table:rounded-lg prose-table:overflow-hidden prose-table:border prose-table:border-gray-200 dark:prose-table:border-gray-700
                prose-th:bg-gray-50 dark:prose-th:bg-gray-800 prose-th:px-4 prose-th:py-3 prose-th:text-left prose-th:text-sm prose-th:font-semibold
                prose-td:px-4 prose-td:py-3 prose-td:text-sm
                prose-hr:border-gray-200 dark:prose-hr:border-gray-700
                ${className}`}
            dangerouslySetInnerHTML={{ __html: content }}
        />
    );
}
