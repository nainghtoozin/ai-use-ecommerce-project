import { useState, useCallback } from 'react';
import { router } from '@inertiajs/react';

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
        track_inventory: product?.track_inventory ?? true,
        continue_selling_when_out_of_stock: product?.continue_selling_when_out_of_stock ?? false,
        category_id: product?.category_id || '',
        status: product?.status || 'draft',
        product_type: product?.type || productType,
        meta_title: product?.meta_title || '',
        meta_description: product?.meta_description || '',
        tags: product?.tags || '',
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
                  };
              })
            : []
    );

    const [comboItems, setComboItems] = useState([]);

    const [photo1File, setPhoto1File] = useState(null);
    const [photo2File, setPhoto2File] = useState(null);
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    const setData = useCallback((field, value) => {
        setFormData((prev) => ({ ...prev, [field]: value }));
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
        form.append('sku', formData.sku || '');
        form.append('description', formData.description || '');
        form.append('price', formData.price);
        form.append('base_price', formData.base_price);
        form.append('stock', formData.stock);
        form.append('category_id', formData.category_id);
        form.append('status', formData.status);
        form.append('type', formData.product_type || 'single');

        if (formData.product_type === 'variable') {
            const sanitizedVariants = variants.map((v) => ({
                ...(v.id && typeof v.id === 'number' ? { id: v.id } : {}),
                sku: v.sku || '',
                price: v.price !== '' ? v.price : null,
                compare_price: v.compare_price !== '' ? v.compare_price : null,
                stock: parseInt(v.stock) || 0,
                options: v.options || [],
            }));
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

        return form;
    }, [formData, variants, comboItems, photo1File, photo2File]);

    const submit = useCallback((onSuccess) => {
        setProcessing(true);
        const form = buildPayload();

        if (isEdit) {
            form.append('_method', 'PUT');
            router.post(`/admin/products/${product.id}`, form, {
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
            router.post('/admin/products', form, {
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
        router.visit('/admin/products');
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
        errors,
        processing,
        submit,
        cancel,
        isEdit,
        existingPhoto1Url: product?.photo1_url || null,
        existingPhoto2Url: product?.photo2_url || null,
    };
}
