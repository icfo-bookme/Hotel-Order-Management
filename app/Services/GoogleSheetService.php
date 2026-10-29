<?php

namespace App\Services;

use App\Models\HotelBooking;
use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\ValueRange;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;
use Illuminate\Support\Facades\Log;

class GoogleSheetService
{
    public const HEADERS = [
        'Booking ID', 'Customer', 'Mobile', 'WhatsApp', 'Hotel / Room',
        'Check-in / Out', 'Customer Total', 'Partner Total', 'B2B Profit',
        'Received', 'Due', 'Payment Methods', 'Sold By', 'Created At',
    ];

    public function bookingRow(HotelBooking $booking): array
    {
        $booking->loadMissing(['sale', 'rooms.reschedules', 'payments', 'creator']);
        $totals = app(HotelBookingService::class)->totals($booking);

        return [
            (string) $booking->id,
            (string) $booking->sale->customer_name,
            (string) $booking->sale->customer_mobile,
            (string) $booking->sale->whatsapp,
            $booking->rooms->map(fn ($room) => $room->hotel_name.' / '.$room->room_name.($room->extra_bed_count > 0
                ? ' | Extra Beds: '.$room->extra_bed_count.' x '.$room->nights.' nights | Customer/bed/night: '.$room->extra_bed_customer_rate.' | Hotel/bed/night: '.$room->extra_bed_partner_rate.' | Extra bed totals: '.$room->extra_bed_customer_total.' / '.$room->extra_bed_partner_total
                : ''))->implode("\n"),
            $booking->rooms->map(fn ($room) => $room->check_in_date->format('Y-m-d').' / '.$room->check_out_date->format('Y-m-d'))->implode("\n"),
            $totals['customer_sell'],
            $totals['partner_share'],
            $totals['profit'],
            $totals['received'],
            $totals['due'],
            $booking->payments->pluck('payment_method')->unique()->implode(', '),
            $booking->creator?->name ?? '',
            $booking->created_at->timezone('Asia/Dhaka')->format('Y-m-d H:i:s'),
        ];
    }

    public function syncBooking(HotelBooking $booking, bool $replace = false): ?bool
    {
        $spreadsheetId = (string) config('services.google_sheets.spreadsheet_id');
        if ($spreadsheetId === '') {
            return null;
        }

        try {
            $arguments = [$spreadsheetId, $booking->created_at->timezone('Asia/Dhaka')->format('Y-m-d'), $this->bookingRow($booking)];
            if ($replace) {
                $arguments[] = true;
            }
            $this->appendBooking(...$arguments);
            return true;
        } catch (Throwable $exception) {
            Log::error('Hotel booking Google Sheet sync failed.', [
                'booking_id' => $booking->id,
                'error_type' => $exception::class,
                'error_code' => $exception->getCode(),
            ]);
            return false;
        }
    }

    public function appendBooking(string $spreadsheetId, string $tabName, array $row, bool $replace = false): void
    {
        Cache::lock('hotel-sheet:'.hash('sha256', $spreadsheetId), 75)->block(5, function () use ($spreadsheetId, $tabName, $row, $replace) {
            $sheets = $this->client();
            $range = $this->tabRange($sheets, $spreadsheetId, $tabName);
            $header = $sheets->spreadsheets_values->get($spreadsheetId, $range.'!A1:N1')->getValues();
            if ($header && $header[0] !== self::HEADERS) {
                throw new RuntimeException('Google Sheet headers do not match hotel sales columns. Existing data was not changed.');
            }

            $ids = $sheets->spreadsheets_values->get($spreadsheetId, $range.'!A2:A')->getValues() ?? [];
            foreach ($ids as $index => $existing) {
                if ((string) ($existing[0] ?? '') === (string) $row[0]) {
                    if ($replace) {
                        $line = $index + 2;
                        $sheets->spreadsheets_values->update($spreadsheetId, $range.'!A'.$line.':N'.$line, new ValueRange([
                            'values' => [$row],
                        ]), ['valueInputOption' => 'RAW']);
                    }
                    return;
                }
            }

            if (!$header) {
                // Only initialize an empty tab, never overwrite unidentified data.
                $existing = $sheets->spreadsheets_values->get($spreadsheetId, $range.'!A:N')->getValues();
                if ($existing) {
                    throw new RuntimeException('Google Sheet tab is not empty and has no matching header.');
                }
                $sheets->spreadsheets_values->update($spreadsheetId, $range.'!A1:N1', new ValueRange([
                    'values' => [self::HEADERS],
                ]), ['valueInputOption' => 'RAW']);
            }

            $sheets->spreadsheets_values->append($spreadsheetId, $range.'!A:N', new ValueRange([
                'values' => [$row],
            ]), ['valueInputOption' => 'RAW', 'insertDataOption' => 'INSERT_ROWS']);
        });
    }

    public function checkConnection(): string
    {
        $spreadsheetId = (string) config('services.google_sheets.spreadsheet_id');
        if ($spreadsheetId === '') {
            throw new RuntimeException('Google Sheet spreadsheet ID is not configured.');
        }

        $sheets = $this->client();
        $range = $this->tabRange($sheets, $spreadsheetId, now('Asia/Dhaka')->format('Y-m-d'), false);
        if ($range === null) {
            return now('Asia/Dhaka')->format('Y-m-d').' (will be created on first sale)';
        }
        $header = $sheets->spreadsheets_values->get($spreadsheetId, $range.'!A1:N1')->getValues();
        if ($header && $header[0] !== self::HEADERS) {
            throw new RuntimeException('Google Sheet headers do not match hotel sales columns.');
        }

        return $range;
    }

    protected function client(): Sheets
    {
        $client = new Client();
        $client->setAuthConfig(config('services.google_sheets.credentials'));
        $client->addScope(Sheets::SPREADSHEETS);
        $client->setHttpClient(new HttpClient(['timeout' => 5, 'connect_timeout' => 3]));

        return new Sheets($client);
    }

    private function tabRange(Sheets $sheets, string $spreadsheetId, string $tabName, bool $create = true): ?string
    {
        $spreadsheet = $sheets->spreadsheets->get($spreadsheetId, ['fields' => 'sheets.properties']);
        foreach ($spreadsheet->getSheets() as $sheet) {
            if ($sheet->getProperties()->getTitle() === $tabName) {
                return "'".str_replace("'", "''", $sheet->getProperties()->getTitle())."'";
            }
        }

        if (!$create) {
            return null;
        }

        $sheets->spreadsheets->batchUpdate($spreadsheetId, new BatchUpdateSpreadsheetRequest([
            'requests' => [['addSheet' => ['properties' => ['title' => $tabName]]]],
        ]));

        return "'".str_replace("'", "''", $tabName)."'";
    }
}
