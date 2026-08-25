export function sanitizeStorefrontHtml(value) {
    if (!value || typeof value !== 'string') return '';
    if (typeof DOMParser === 'undefined') return value.replace(/<script[\s\S]*?>[\s\S]*?<\/script>/gi, '');

    const document = new DOMParser().parseFromString(value, 'text/html');
    document.querySelectorAll('script, iframe, object, embed, style, link').forEach((node) => node.remove());
    document.querySelectorAll('*').forEach((element) => {
        [...element.attributes].forEach((attribute) => {
            const name = attribute.name.toLowerCase();
            const content = attribute.value.trim().toLowerCase();
            if (name.startsWith('on') || ((name === 'href' || name === 'src' || name === 'action') && /^(javascript|data:text\/html|vbscript):/.test(content))) element.removeAttribute(attribute.name);
        });
    });
    return document.body.innerHTML;
}
