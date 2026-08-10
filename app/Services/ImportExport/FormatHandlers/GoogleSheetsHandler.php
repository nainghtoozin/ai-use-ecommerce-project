<?php

namespace App\Services\ImportExport\FormatHandlers;

use Illuminate\Support\Facades\Log;

class GoogleSheetsHandler
{
    private ?object $client = null;
    private bool $available = false;

    public function __construct()
    {
        $this->available = class_exists(\Google\Client::class);
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function getClient()
    {
        if ($this->client) {
            return $this->client;
        }

        if (!$this->available) {
            throw new \RuntimeException('Google API Client is not installed. Run: composer require google/apiclient');
        }

        $this->client = new \Google\Client();
        $this->client->setClientId(config('services.google.client_id'));
        $this->client->setClientSecret(config('services.google.client_secret'));
        $this->client->setScopes([\Google\Service\Sheets::SPREADSHEETS]);
        $this->client->setAccessType('offline');
        $this->client->setRedirectUri(config('services.google.redirect_uri'));

        return $this->client;
    }

    public function getAuthUrl(): string
    {
        return $this->getClient()->createAuthUrl();
    }

    public function authenticate(string $code): array
    {
        return $this->getClient()->fetchAccessTokenWithAuthCode($code);
    }

    public function setAccessToken(array|string $token): void
    {
        $this->getClient()->setAccessToken($token);
    }

    public function isTokenExpired(): bool
    {
        return $this->getClient()->isAccessTokenExpired();
    }

    public function refreshToken(): array
    {
        return $this->getClient()->fetchAccessTokenWithRefreshToken(
            $this->getClient()->getRefreshToken()
        );
    }

    public function read(string $spreadsheetId, string $range): array
    {
        $service = new \Google\Service\Sheets($this->getClient());
        $response = $service->spreadsheets_values->get($spreadsheetId, $range);
        $values = $response->getValues();

        if (empty($values)) {
            return ['headers' => [], 'rows' => []];
        }

        $header = array_map(fn($h) => strtolower(trim((string) $h)), array_shift($values));
        $rows = [];

        foreach ($values as $row) {
            if (count($row) >= 1 && !empty(trim((string) $row[0]))) {
                $padded = array_pad($row, count($header), '');
                $rows[] = array_combine($header, array_slice($padded, 0, count($header)));
            }
        }

        return ['headers' => $header, 'rows' => $rows];
    }

    public function getWorksheets(string $spreadsheetId): array
    {
        $service = new \Google\Service\Sheets($this->getClient());
        $spreadsheet = $service->spreadsheets->get($spreadsheetId);

        $sheets = [];
        foreach ($spreadsheet->getSheets() as $sheet) {
            $properties = $sheet->getProperties();
            $sheets[] = [
                'id' => $properties->getSheetId(),
                'title' => $properties->getTitle(),
                'index' => $properties->getIndex(),
            ];
        }

        return $sheets;
    }

    public function write(string $spreadsheetId, string $range, array $headers, array $rows): void
    {
        $service = new \Google\Service\Sheets($this->getClient());

        $values = [$headers];
        foreach ($rows as $row) {
            $values[] = array_values($row);
        }

        $body = new \Google\Service\Sheets\ValueRange(['values' => $values]);
        $service->spreadsheets_values->update(
            $spreadsheetId,
            $range,
            $body,
            ['valueInputOption' => 'RAW']
        );
    }

    public function createSpreadsheet(string $title): array
    {
        $service = new \Google\Service\Sheets($this->getClient());

        $spreadsheet = new \Google\Service\Sheets\Spreadsheet([
            'properties' => ['title' => $title],
        ]);

        $result = $service->spreadsheets->create($spreadsheet);

        return [
            'spreadsheetId' => $result->getSpreadsheetId(),
            'url' => $result->getSpreadsheetUrl(),
        ];
    }

    public function getSpreadsheetUrl(string $spreadsheetId): string
    {
        return "https://docs.google.com/spreadsheets/d/{$spreadsheetId}";
    }
}
