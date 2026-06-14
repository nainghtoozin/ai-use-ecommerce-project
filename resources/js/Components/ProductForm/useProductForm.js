import { useState, useCallback } from 'react';
import { router } from '@inertiajs/react';
import { adminUrl } from '@/Utils/adminUrl';

function slugify(text) {
    return text
        .toLowerCase()
        .replace(/[^\w\s-]/g, '')
        .replace(/[\s_]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

export default function useProductForm({ product = null, productType = 'single' } = {}) {
    const isEdit = !!product;

    const [formData, setFormData] = useState({
        name: product?.name || '',
        slug: product?.slug || '',
        short_description: product?.short_description || '',
        description: product?.description || '',
        price: product?.price ?? '',
        base_price: product?.base_price ?? '',
        cost_price: product?.cost_price || '',
        stock: product?.stock ?? 0,
        sku: product?.sku || '',
        barcode: product?.barcode || '',
        low_stock_alert: product?.low_stock_alert ?? 5,
        category_id: product?.category_id || '',
        brand_id: product?.brand_id || '',
        unit_id: product?.unit_id || '',
        status: product?.status || 'active',
        product_type: product?.type || productType,
        meta_title: product?.meta_title || '',
        meta_description: product?.meta_description || '',
        seo_title: product?.seo_title || '',
        seo_description: product?.seo_description || '',
        seo_keywords: product?.seo_keywords || '',
        seo_image: product?.seo_image || '',
        tags: product?.tags || '',
        gallery_images: product?.gallery_images || [],
        gallery_images_url: product?.gallery_images_url || [],
        existing_combo_items: product?.combo_items || [],
    });

    const [variants, setVariants] = useState(
        product?.variants
            ? product.variants.map((v) => {
                  const options = [];
                  if (v.attributes && typeof v.attributes === 'object') {
                      for (const value of Object.values(v.attributes)) {
                          options.push(String(value));
                      }
                  }
                  return {
                      id: v.id || null,
                      sku: v.sku || '',
                      price: v.price ?? '',
                      compare_price: v.compare_price ?? '',
                      cost_price: v.cost_price || '',
                      stock: v.stock ?? 0,
                      options,
                      status: v.status || 'active',
                      imageFile: null,
                      existingImage: v.image || null,
                      existingImageUrl: v.image_url || null,
                      imageRemoved: false,
                  };
              })
            : []
    );

    const [comboItems, setComboItems] = useState([]);

    const [photo1File, setPhoto1File] = useState(null);
    const [photo2File, setPhoto2File] = useState(null);
    const [galleryFiles, setGalleryFiles] = useState([]);
    const [removedGalleryImages, setRemovedGalleryImages] = useState([]);
    const [seoImageFile, setSeoImageFile] = useState(null);
    const [removeSeoImage, setRemoveSeoImage] = useState(false);
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    const setData = useCallback((field, value) => {
        setFormData((prev) => {
            const next = { ...prev, [field]: value };
            if (field === 'name' && !prev.slug) {
                next.slug = slugify(value);
            }
            return next;
        });
        if (errors[field]) {
            setErrors((prev) => {
                const next = { ...prev };
                delete next[field];
                return next;
            });
        }
    }, [errors]);

    const buildPayload = useCallback(() => {
        const form = new FormData();
        form.append('name', formData.name);
        form.append('slug', formData.slug || slugify(formData.name));
        form.append('sku', formData.sku || '');
        form.append('barcode', formData.barcode || '');
        form.append('short_description', formData.short_description || '');
        form.append('description', formData.description || '');
        form.append('price', formData.price);
        form.append('base_price', formData.base_price);
        form.append('cost_price', formData.cost_price || '');
        form.append('stock', formData.stock);
        form.append('low_stock_alert', formData.low_stock_alert ?? 5);
        form.append('category_id', formData.category_id);
        form.append('brand_id', formData.brand_id || '');
        form.append('unit_id', formData.unit_id || '');
        form.append('status', formData.status);
        form.append('type', formData.product_type || 'single');

        if (formData.product_type === 'variable') {
            const sanitizedVariants = variants.map((v, index) => {
                if (v.imageFile) {
                    form.append(`variant_images[${index}]`, v.imageFile);
                }
                return {
                    ...(v.id && typeof v.id === 'number' ? { id: v.id } : {}),
                    sku: v.sku || '',
                    price: v.price !== '' ? v.price : null,
                    compare_price: v.compare_price !== '' ? v.compare_price : null,
                    stock: parseInt(v.stock) || 0,
                    options: v.options || [],
                    existing_image: v.imageFile ? null : (v.imageRemoved ? null : (v.existingImage || null)),
                    image_removed: v.imageRemoved || false,
                };
            });
            form.append('variants', JSON.stringify(sanitizedVariants));
        }

        if (formData.product_type === 'combo') {
            const sanitizedComboItems = comboItems.map((item) => ({
                ...(item.combo_item_id ? { id: item.combo_item_id } : {}),
                combo_product_id: item.product_id,
                linked_variant_id: item.variant_id || null,
                quantity: parseInt(item.quantity) || 1,
            }));
            form.append('combo_items', JSON.stringify(sanitizedComboItems));
        }

        if (photo1File) form.append('photo1', photo1File);
        if (photo2File) form.append('photo2', photo2File);

        form.append('seo_title', formData.seo_title || '');
        form.append('seo_description', formData.seo_description || '');
        form.append('seo_keywords', formData.seo_keywords || '');

        if (seoImageFile) {
            form.append('seo_image', seoImageFile);
        }

        if (removeSeoImage) {
            form.append('remove_seo_image', '1');
        }

        // Gallery images
        const existingToKeep = (formData.gallery_images || []).filter(
            (path) => !removedGalleryImages.includes(path)
        );
        form.append('existing_gallery_images', JSON.stringify(existingToKeep));

        galleryFiles.forEach((file) => {
            form.append('gallery_images[]', file);
        });

        return form;
    }, [formData, variants, comboItems, photo1File, photo2File, galleryFiles, removedGalleryImages, seoImageFile, removeSeoImage]);

    const submit = useCallback((onSuccess) => {
        setProcessing(true);
        const form = buildPayload();

        if (isEdit) {
            form.append('_method', 'PUT');
            router.post(adminUrl(`/admin/products/${product.id}`), form, {
                forceFormData: true,
                preserveScroll: true,
                onError: (pageErrors) => {
                    setErrors(pageErrors);
                    setProcessing(false);
                },
                onSuccess: () => {
                    setProcessing(false);
                    onSuccess?.();
                },
            });
        } else {
            router.post(adminUrl('/admin/products'), form, {
                forceFormData: true,
                preserveScroll: true,
                onError: (pageErrors) => {
                    setErrors(pageErrors);
                    setProcessing(false);
                },
                onSuccess: () => {
                    setProcessing(false);
                    onSuccess?.();
                },
            });
        }
    }, [isEdit, product?.id, buildPayload]);

    const cancel = useCallback(() => {
        router.visit(adminUrl('/admin/products'));
    }, []);

    return {
        formData,
        setFormData,
        setData,
        variants,
        setVariants,
        comboItems,
        setComboItems,
        photo1File,
        setPhoto1File,
        photo2File,
        setPhoto2File,
        galleryFiles,
        setGalleryFiles,
        removedGalleryImages,
        setRemovedGalleryImages,
        seoImageFile,
        setSeoImageFile,
        removeSeoImage,
        setRemoveSeoImage,
        errors,
        processing,
        submit,
        cancel,
        isEdit,
        existingPhoto1Url: product?.photo1_url || null,
        existingPhoto2Url: product?.photo2_url || null,
    };
}
