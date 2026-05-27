import { useState } from 'react';
import FormInput from '../FormInput';
import FormToggle from '../FormToggle';

export default function InventorySection({ data, setData, errors }) {
    const trackInventory = data.track_inventory !== false;
    const continueSelling = data.continue_selling_when_out_of_stock || false;
    const stockValue = parseInt(data.stock) || 0;
    const lowStockThreshold = parseInt(data.low_stock_alert) || 5;
    const isLowStock = trackInventory && stockValue > 0 && stockValue <= lowStockThreshold;
    const isOutOfStock = trackInventory && stockValue === 0;

    return (
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            {/* Header */}
            <div className="px-5 py-4 border-b border-gray-100">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="flex-shrink-0 w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center">
                            <svg className="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                        </div>
                        <div>
                            <h3 className="text-base font-semibold text-gray-900">Inventory</h3>
                            <p className="text-xs text-gray-500 mt-0.5">Stock tracking, SKU, and availability</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium ${
                            trackInventory
                                ? isOutOfStock
                                    ? 'bg-red-100 text-red-700'
                                    : isLowStock
                                        ? 'bg-amber-100 text-amber-700'
                                        : 'bg-green-100 text-green-700'
                                : 'bg-gray-100 text-gray-500'
                        }`}>
                            <span className={`w-1.5 h-1.5 rounded-full ${
                                trackInventory
                                    ? isOutOfStock
                                        ? 'bg-red-500'
                                        : isLowStock
                                            ? 'bg-amber-500'
                                            : 'bg-green-500'
                                    : 'bg-gray-400'
                            }`} />
                            {trackInventory
                                ? isOutOfStock
                                    ? 'Out of stock'
                                    : isLowStock
                                        ? 'Low stock'
                                        : `${stockValue} in stock`
                                : 'Not tracked'
                            }
                        </span>
                    </div>
                </div>
            </div>

            <div className="px-5 py-5">
                {/* Toggles */}
                <div className="space-y-0 divide-y divide-gray-100 border border-gray-200 rounded-lg px-4 mb-5">
                    <FormToggle
                        label="Track Inventory"
                        description="Monitor stock levels and get alerts when inventory runs low"
                        checked={trackInventory}
                        onChange={(val) => setData('track_inventory', val)}
                        name="track_inventory"
                    />
                    <FormToggle
                        label="Continue Selling When Out of Stock"
                        description="Allow customers to purchase even when stock reaches zero"
                        checked={continueSelling}
                        onChange={(val) => setData('continue_selling_when_out_of_stock', val)}
                        name="continue_selling_when_out_of_stock"
                        disabled={!trackInventory}
                    />
                </div>

                {/* Stock Controls */}
                {trackInventory && (
                    <div className="space-y-4">
                        {/* Stock Quantity */}
                        <div>
                            <FormInput
                                label="Quantity in Stock"
                                name="stock"
                                type="number"
                                value={data.stock ?? 0}
                                onChange={(e) => setData('stock', e.target.value)}
                                placeholder="0"
                                error={errors.stock}
                                min="0"
                                helpText="Number of items currently available"
                            />
                        </div>

                        {/* Low Stock Alert */}
                        <div>
                            <FormInput
                                label="Low Stock Alert Threshold"
                                name="low_stock_alert"
                                type="number"
                                value={data.low_stock_alert || ''}
                                onChange={(e) => setData('low_stock_alert', e.target.value)}
                                placeholder="5"
                                error={errors.low_stock_alert}
                                min="0"
                                helpText="You'll be notified when stock drops below this number"
                            />
                        </div>

                        {/* Stock Warning Banner */}
                        {(isLowStock || isOutOfStock) && !continueSelling && (
                            <div className={`rounded-lg border px-4 py-3 ${
                                isOutOfStock
                                    ? 'bg-red-50 border-red-200'
                                    : 'bg-amber-50 border-amber-200'
                            }`}>
                                <div className="flex items-start gap-3">
                                    <svg className={`w-5 h-5 flex-shrink-0 mt-0.5 ${
                                        isOutOfStock ? 'text-red-500' : 'text-amber-500'
                                    }`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    <div>
                                        <p className={`text-sm font-medium ${
                                            isOutOfStock ? 'text-red-800' : 'text-amber-800'
                                        }`}>
                                            {isOutOfStock
                                                ? 'This product is out of stock'
                                                : `Only ${stockValue} item${stockValue !== 1 ? 's' : ''} remaining`
                                            }
                                        </p>
                                        <p className={`text-xs mt-0.5 ${
                                            isOutOfStock ? 'text-red-600' : 'text-amber-600'
                                        }`}>
                                            {isOutOfStock
                                                ? 'Customers cannot purchase this product until restocked'
                                                : `Restock soon to avoid losing sales`
                                            }
                                        </p>
                                    </div>
                                </div>
                            </div>
                        )}

                        {continueSelling && isOutOfStock && (
                            <div className="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3">
                                <div className="flex items-start gap-3">
                                    <svg className="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <div>
                                        <p className="text-sm font-medium text-blue-800">Backorder mode active</p>
                                        <p className="text-xs mt-0.5 text-blue-600">Customers can still order even though stock is zero</p>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {/* Not tracking inventory message */}
                {!trackInventory && (
                    <div className="rounded-lg border border-gray-200 bg-gray-50 px-4 py-4 text-center">
                        <svg className="w-8 h-8 text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        <p className="text-sm text-gray-600 font-medium">Inventory tracking is disabled</p>
                        <p className="text-xs text-gray-400 mt-1">Stock levels won't be monitored for this product</p>
                    </div>
                )}

                {/* Divider */}
                <div className="border-t border-gray-100 my-5" />

                {/* Product Codes Section */}
                <div className="space-y-4">
                    <div>
                        <h4 className="text-sm font-medium text-gray-900 mb-3">Product Codes</h4>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <FormInput
                                label="SKU"
                                name="sku"
                                value={data.sku || ''}
                                onChange={(e) => setData('sku', e.target.value)}
                                placeholder="e.g., TSHIRT-BLK-M"
                                error={errors.sku}
                                helpText="Unique identifier for this product"
                            />
                            <FormInput
                                label="Barcode (ISBN, UPC, GTIN)"
                                name="barcode"
                                value={data.barcode || ''}
                                onChange={(e) => setData('barcode', e.target.value)}
                                placeholder="e.g., 123456789012"
                                error={errors.barcode}
                                helpText="Optional — used for POS and shipping"
                            />
                        </div>
                    </div>
                </div>

                {/* Future Features Preview */}
                <div className="mt-5 pt-4 border-t border-gray-100">
                    <div className="flex items-center justify-between mb-3">
                        <h4 className="text-sm font-medium text-gray-700">Advanced Inventory</h4>
                        <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">
                            <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-4-4v4h-8V7a4 4 0 00-4 4v4h16z" />
                            </svg>
                            Pro
                        </span>
                    </div>
                    <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        {[
                            { label: 'Multi-warehouse', icon: '🏭' },
                            { label: 'Variant stock', icon: '📦' },
                            { label: 'Stock history', icon: '📊' },
                        ].map((feature) => (
                            <div
                                key={feature.label}
                                className="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 opacity-60"
                            >
                                <span className="text-sm">{feature.icon}</span>
                                <span className="text-xs text-gray-500">{feature.label}</span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}
