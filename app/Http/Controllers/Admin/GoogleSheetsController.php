<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ImportExport\FormatHandlers\GoogleSheetsHandler;
use Illuminate\Http\Request;

class GoogleSheetsController extends Controller
{
    public function __construct(
        private readonly GoogleSheetsHandler $sheetsHandler,
    ) {}

    /**
     * Redirect to Google OAuth consent screen.
     */
    public function auth()
    {
        $url = $this->sheetsHandler->getAuthUrl();
        return redirect($url);
    }

    /**
     * Handle Google OAuth callback.
     */
    public function callback(Request $request)
    {
        $code = $request->input('code');

        if (!$code) {
            return redirect()->route('admin.products.index')
                ->with('error', 'Google authorization failed.');
        }

        try {
            $token = $this->sheetsHandler->authenticate($code);
            session(['google_sheets_token' => $token]);

            return redirect()->route('admin.products.index')
                ->with('success', 'Google Sheets connected successfully.');
        } catch (\Throwable $e) {
            return redirect()->route('admin.products.index')
                ->with('error', 'Failed to connect Google Sheets: ' . $e->getMessage());
        }
    }

    /**
     * List worksheets in a spreadsheet.
     */
    public function worksheets(Request $request)
    {
        $request->validate(['spreadsheet_id' => 'required|string']);

        $token = session('google_sheets_token');
        if (!$token) {
            return response()->json(['error' => 'Not connected to Google Sheets.'], 401);
        }

        try {
            $this->sheetsHandler->setAccessToken($token);
            $worksheets = $this->sheetsHandler->getWorksheets($request->input('spreadsheet_id'));
            return response()->json(['worksheets' => $worksheets]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Check connection status.
     */
    public function status()
    {
        $token = session('google_sheets_token');
        $connected = false;

        if ($token) {
            try {
                $this->sheetsHandler->setAccessToken($token);
                $connected = !$this->sheetsHandler->isTokenExpired();
            } catch (\Throwable $e) {
                $connected = false;
            }
        }

        return response()->json(['connected' => $connected]);
    }

    /**
     * Disconnect Google Sheets.
     */
    public function disconnect()
    {
        session()->forget('google_sheets_token');
        return response()->json(['success' => true]);
    }
}
