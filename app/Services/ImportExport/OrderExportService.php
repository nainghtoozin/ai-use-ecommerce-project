<?php

namespace App\Services\ImportExport;

use App\Models\Order;
use App\Services\ImportExport\FormatHandlers\CsvHandler;
use App\Services\ImportExport\FormatHandlers\ExcelHandler;
use App\Services\ImportExport\FormatHandlers\GoogleSheetsHandler;
use Illuminate\Support\Facades\DB;

class OrderExportService
{
    public function __construct(
        private readonly CsvHandler $csvHandler,
        private readonly ExcelHandler $excelHandler,
        private readonly GoogleSheetsHandler $sheetsHandler,
    ) {}

    /**
     * Export orders with filters.
     */
    public function export(string $format, array $filters, int $tenantId)
    {
        $query = Order::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->with(['user', 'paymentMethod', 'city', 'township', 'items']);

        // Apply filters
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('invoice_number', 'LIKE', "%{$filters['search']}%")
                  ->orWhere('customer_name', 'LIKE', "%{$filters['search']}%")
                  ->orWhere('email', 'LIKE', "%{$filters['search']}%")
                  ->orWhere('phone', 'LIKE', "%{$filters['search']}%");
            });
        }

        if (!empty($filters['order_status'])) {
            $query->where('order_status', $filters['order_status']);
        }

        if (!empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (!empty($filters['payment_method_id'])) {
            $query->where('payment_method_id', $filters['payment_method_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        if (!empty($filters['ids'])) {
            $query->whereIn('id', $filters['ids']);
        }

        $orders = $query->orderBy('created_at', 'desc')->get();

        $headers = [
            'Order Number', 'Customer', 'Phone', 'Email',
            'Order Date', 'Status', 'Payment Method',
            'Subtotal', 'Discount', 'Shipping Fee', 'Total',
            'Payment Status', 'City', 'Township', 'Address', 'Notes',
        ];

        $rows = $orders->map(function ($order) {
            return [
                'Order Number' => $order->invoice_number,
                'Customer' => $order->customer_name ?? trim(($order->first_name ?? '') . ' ' . ($order->last_name ?? '')),
                'Phone' => $order->phone ?? '',
                'Email' => $order->email ?? '',
                'Order Date' => $order->created_at?->format('Y-m-d H:i'),
                'Status' => $order->order_status,
                'Payment Method' => $order->paymentMethod?->name ?? '',
                'Subtotal' => $order->subtotal ?? '',
                'Discount' => $order->discount_amount ?? 0,
                'Shipping Fee' => $order->delivery_fee ?? 0,
                'Total' => $order->total_amount,
                'Payment Status' => $order->payment_status,
                'City' => $order->city?->name ?? '',
                'Township' => $order->township?->name ?? '',
                'Address' => $order->address ?? '',
                'Notes' => $order->notes ?? '',
            ];
        })->toArray();

        $filename = 'orders_' . now()->format('Y-m-d_His');

        if ($format === 'xlsx') {
            return $this->excelHandler->write($headers, $rows, $filename);
        }

        if ($format === 'google_sheets') {
            $token = session('google_sheets_token');
            if (!$token) {
                return response()->json(['error' => 'Google Sheets not connected.'], 400);
            }

            $result = $this->sheetsHandler->createSpreadsheet('Order Export - ' . now()->format('Y-m-d'));
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
