const getImagePreviewUrl = (value) => {
    if (!value) return null;

    if (value instanceof File) {
        return URL.createObjectURL(value);
    }

    if (typeof value === 'string') {
        if (
            value.startsWith('http://') ||
            value.startsWith('https://') ||
            value.startsWith('/storage/') ||
            value.startsWith('data:') ||
            value.startsWith('blob:')
        ) {
            return value;
        }
        return `/storage/${value}`;
    }

    return null;
};

export default getImagePreviewUrl;