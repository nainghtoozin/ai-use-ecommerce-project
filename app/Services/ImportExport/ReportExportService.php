<?php

namespace App\Services\ImportExport;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\PaymentMethod;
use App\Services\ImportExport\FormatHandlers\CsvHandler;
use App\Services\ImportExport\FormatHandlers\ExcelHandler;
use App\Services\ImportExport\FormatHandlers\GoogleSheetsHandler;
use Illuminate\Support\Facades\DB;

class ReportExportService
{
    public function __construct(
        private readonly CsvHandler $csvHandler,
        private readonly ExcelHandler $excelHandler,
        private readonly GoogleSheetsHandler $sheetsHandler,
    ) {}

    /**
     * Export sales report.
     */
    public function exportSales(string $format, array $filters, int $tenantId)
    {
        $query = Order::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->whereIn('order_status', ['confirmed', 'processing', 'shipped', 'delivered', 'completed'])
            ->with(['user', 'paymentMethod']);

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        $orders = $query->orderBy('created_at', 'desc')->get();

        $headers = ['Order Number', 'Customer', 'Date', 'Status', 'Payment Method', 'Subtotal', 'Discount', 'Shipping', 'Total', 'Payment Status'];

        $rows = $orders->map(fn($o) => [
            'Order Number' => $o->invoice_number,
            'Customer' => $o->customer_name ?? '',
            'Date' => $o->created_at?->format('Y-m-d H:i'),
            'Status' => $o->order_status,
            'Payment Method' => $o->paymentMethod?->name ?? '',
            'Subtotal' => $o->subtotal ?? 0,
            'Discount' => $o->discount_amount ?? 0,
            'Shipping' => $o->delivery_fee ?? 0,
            'Total' => $o->total_amount,
            'Payment Status' => $o->payment_status,
        ])->toArray();

        return $this->doExport($headers, $rows, 'sales_report', $format);
    }

    /**
     * Export product sales report.
     */
    public function exportProductSales(string $format, array $filters, int $tenantId)
    {
        $query = OrderItem::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.order_status', ['confirmed', 'processing', 'shipped', 'delivered', 'completed'])
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
            ->select(
                'order_items.product_id',
                'order_items.product_variant_id',
                'products.name as product_name',
                'products.sku as product_sku',
                'product_variants.sku as variant_sku',
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('SUM(order_items.total) as total_revenue'),
                DB::raw('COUNT(DISTINCT order_items.order_id) as order_count')
            );

        if (!empty($filters['date_from'])) {
            $query->where('orders.created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('orders.created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }
        if (!empty($filters['category_id'])) {
            $query->where('products.category_id', $filters['category_id']);
        }

        $results = $query->groupBy('order_items.product_id', 'order_items.product_variant_id', 'products.name', 'products.sku', 'product_variants.sku')
            ->orderByDesc('total_revenue')
            ->get();

        $headers = ['Product', 'SKU', 'Variant SKU', 'Quantity Sold', 'Revenue', 'Orders'];

        $rows = $results->map(fn($r) => [
            'Product' => $r->product_name ?? 'Unknown',
            'SKU' => $r->product_sku ?? '',
            'Variant SKU' => $r->variant_sku ?? '',
            'Quantity Sold' => $r->total_quantity,
            'Revenue' => $r->total_revenue,
            'Orders' => $r->order_count,
        ])->toArray();

        return $this->doExport($headers, $rows, 'product_sales_report', $format);
    }

    /**
     * Export payment report.
     */
    public function exportPayments(string $format, array $filters, int $tenantId)
    {
        $query = Order::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('payment_status', 'paid')
            ->with(['paymentMethod']);

        if (!empty($filters['date_from'])) {
            $query->where('payment_verified_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('payment_verified_at', '<=', $filters['date_to'] . ' 23:59:59');
        }
        if (!empty($filters['payment_method_id'])) {
            $query->where('payment_method_id', $filters['payment_method_id']);
        }

        $orders = $query->orderBy('payment_verified_at', 'desc')->get();

        $headers = ['Order Number', 'Customer', 'Payment Method', 'Amount', 'Verified At', 'Transaction ID'];

        $rows = $orders->map(fn($o) => [
            'Order Number' => $o->invoice_number,
            'Customer' => $o->customer_name ?? '',
            'Payment Method' => $o->paymentMethod?->name ?? '',
            'Amount' => $o->total_amount,
            'Verified At' => $o->payment_verified_at?->format('Y-m-d H:i'),
            'Transaction ID' => $o->transaction_id ?? '',
        ])->toArray();

        return $this->doExport($headers, $rows, 'payment_report', $format);
    }

    /**
     * Export inventory report.
     */
    public function exportInventory(string $format, array $filters, int $tenantId)
    {
        $query = Product::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->with(['category', 'brand', 'unit', 'variants']);

        if (!empty($filters['stock_status'])) {
            switch ($filters['stock_status']) {
                case 'out_of_stock':
                    $query->where('stock', '<=', 0);
                    break;
                case 'low_stock':
                    $query->where('stock', '>', 0)->where('stock', '<', 10);
                    break;
                case 'in_stock':
                    $query->where('stock', '>=', 10);
                    break;
            }
        }

        $products = $query->orderBy('name')->get();

        $headers = ['Product', 'SKU', 'Category', 'Brand', 'Unit', 'Stock', 'Price', 'Cost Price', 'Status'];

        $rows = $products->map(fn($p) => [
            'Product' => $p->name,
            'SKU' => $p->sku ?? '',
            'Category' => $p->category?->name ?? '',
            'Brand' => $p->brand?->name ?? '',
            'Unit' => $p->unit?->name ?? '',
            'Stock' => $p->stock,
            'Price' => $p->price,
            'Cost Price' => $p->cost_price ?? '',
            'Status' => $p->status,
        ])->toArray();

        return $this->doExport($headers, $rows, 'inventory_report', $format);
    }

    /**
     * Export customer report.
     */
    public function exportCustomers(string $format, array $filters, int $tenantId)
    {
        $query = Order::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->select(
                'email',
                'customer_name',
                'phone',
                DB::raw('COUNT(*) as order_count'),
                DB::raw('SUM(total_amount) as total_spent'),
                DB::raw('MAX(created_at) as last_order_date')
            )
            ->whereNotNull('email')
            ->groupBy('email', 'customer_name', 'phone');

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        $customers = $query->orderByDesc('total_spent')->get();

        $headers = ['Name', 'Email', 'Phone', 'Orders', 'Total Spent', 'Last Order'];

        $rows = $customers->map(fn($c) => [
            'Name' => $c->customer_name ?? '',
            'Email' => $c->email,
            'Phone' => $c->phone ?? '',
            'Orders' => $c->order_count,
            'Total Spent' => $c->total_spent,
            'Last Order' => $c->last_order_date ? \Carbon\Carbon::parse($c->last_order_date)->format('Y-m-d') : '',
        ])->toArray();

        return $this->doExport($headers, $rows, 'customer_report', $format);
    }

    /**
     * Shared export handler.
     */
    private function doExport(array $headers, array $rows, string $name, string $format)
    {
        $filename = $name . '_' . now()->format('Y-m-d_His');

        if ($format === 'xlsx') {
            return $this->excelHandler->write($headers, $rows, $filename);
        }

        if ($format === 'google_sheets') {
            $token = session('google_sheets_token');
            if (!$token) {
                return response()->json(['error' => 'Google Sheets not connected.'], 400);
            }

            $result = $this->sheetsHandler->createSpreadsheet($name . ' - ' . now()->format('Y-m-d'));
            $this->sheetsHandler->setAccessToken($token);
            $this->sheetsHandler->write($result['spreadsheetId'], 'Sheet1!A1', $headers, $rows);

            return response()->json([
                'success' => true,
                'url' => $result['url'],
                'spreadsheetId' => $result['spreadsheetId'],
            ]);
        }

        return $this->csvHandler->write($headers, $rows, $filename);
    }
}
