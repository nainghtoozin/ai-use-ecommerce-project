function readCookie(name) {
    if (typeof document === 'undefined') return '';
    const match = document.cookie.match(new RegExp('(?:^|;\\s*)' + name + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : '';
}

function metaToken() {
    if (typeof document === 'undefined') return '';
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

export function csrfToken() {
    return readCookie('XSRF-TOKEN');
}

export function csrfHeaders() {
    const xsrf = csrfToken();
    const headers = {
        'X-Requested-With': 'XMLHttpRequest',
    };
    if (xsrf) {
        headers['X-XSRF-TOKEN'] = xsrf;
    } else {
        const token = metaToken();
        if (token) headers['X-CSRF-TOKEN'] = token;
    }
    return headers;
}

export async function parseResponse(response) {
    const contentType = response.headers.get('content-type') || '';
    if (!contentType.includes('application/json')) {
        const text = await response.text();
        if (!response.ok) {
            throw new Error(`Request failed with status ${response.status}`);
        }
        return { success: true, text };
    }
    return response.json();
}