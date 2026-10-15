# Application structure

This application uses Laravel's standard folders. Keep features in these layers:

- Routes define authenticated endpoints and preserve existing route names.
- Form Requests authorize and validate input; controllers use `validated()`.
- Controllers coordinate services and return views or JSON responses.
- Services own business operations, transactions, and queries.
- Models own relationships, casts, and calculated attributes.
- Blade components/partials contain shared markup; page JavaScript belongs in `resources/js`.

Hotel booking workflow: one ticket sale has bookings, each with rooms and payments.
Room dates and nightly rates are saved as snapshots. Room totals and booking
profit/due are calculated using integer cents; totals are not editable inputs.
Ticket `hotel_status` uses pending, feedbacked, and sold. Legacy booked records
remain readable. Booking status uses pending, confirmed, and cancelled.

`ShipTicketQueryService` handles the ticket DataTable and dashboard queries.
`HotelRemarkService` handles feedback. The three booking services handle
bookings, rooms, and payments. The sell operation is atomic and locks the ticket
to prevent duplicate sales.

Never rewrite applied migrations or remove financial records as cleanup.
Use a new migration for schema changes and test against an isolated database.
Keep `.env` and runtime data out of source changes.

Validation: `npm run build`, `php artisan view:cache`, `php artisan route:list`,
and `php artisan test`. Production deployment instructions are in `DEPLOYMENT.md`.

All write actions follow Controller → Form Request → Service. Controllers never
validate input inline, authenticate users, or persist models directly. Read
actions with filters use list requests. View-only actions can render views
directly when there is no validation or business operation to perform.

AccountService handles registration/profile changes/deletion. AuthSessionService
handles login/logout, session rotation, and login throttling. PasswordService
handles password changes, confirmation, and reset broker operations. Passwords
are hashed by the User model's existing hashed cast.
