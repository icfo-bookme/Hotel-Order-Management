# Hotel sale Google Sheet sync

New hotel sales append one row per booking to a daily `YYYY-MM-DD` tab in the configured spreadsheet. Tab dates use the booking creation time in Asia/Dhaka. Multiple rooms share one row. Later payment verification, rescheduling and deletion do not update that row.

1. Place credentials at `storage/google/service-account.json` (Git ignored).
2. Enable Google Sheets API and share the spreadsheet with the service account email as Editor.
3. Set `GOOGLE_SHEETS_SPREADSHEET_ID` in `.env`; an empty value disables sync.
4. Run `php artisan config:clear` and `php artisan hotel-sheets:check` for a read-only access check.

The sell endpoint commits the database booking, then calls Google directly and waits before returning the response. No queue worker is required. Google latency adds to sell response time. Each HTTP call has a five-second timeout; the sell form waits up to sixty seconds.

The response contains `sheet_synced` (true, false, or null when disabled). Failed sync logs the booking ID and error type/code immediately, and the UI shows a warning while preserving the successful sale and invoice. There is no background retry. Do not create the sale again to retry a failed export.

Missing daily tabs and headers are initialized automatically. Existing mismatched headers are never overwritten. Column A contains Booking ID; a shared cache lock and ID lookup protect against duplicate application writes. Keep all application instances on the same cache store.

The credential file must never be committed. Only newly created hotel sales are synced; historical bookings are not automatically exported.
