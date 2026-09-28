# Greenridge Estate — Services Desk

A full booking &amp; management system for estate household services: **Standard
Laundry, Car Wash Basic, Grocery Shopping, House Cleaning (2BR), Plumbing
Repair, Food Delivery, Haircut &amp; Style.**

Real backend + frontend, split cleanly:

- **Backend** — `server.js`. Plain Node.js (`http` module only — **no
  `npm install` required**). Serves a JSON REST API and the frontend's static
  files. Data is persisted to `data/db.json`, a small file-based database.
- **Frontend** — `public/`. Plain HTML/CSS/JS that calls the API with
  `fetch()`. No build step.

## Run it

Requires only Node.js 18+ (nothing else to install).

```bash
node server.js
```

Then open **http://localhost:3000**.

Data (services, staff, bookings) is saved to `data/db.json` on every change,
so it survives restarts. Delete that file's contents and restart, or copy the
seed shape back in, to reset to a clean slate.

## Project layout

```
estate-services-system/
├─ server.js          # HTTP server + REST API + static file serving
├─ package.json
├─ data/
│  └─ db.json          # persisted data (services, staff, bookings)
└─ public/
   ├─ index.html        # app shell
   ├─ styles.css
   └─ app.js             # frontend logic (fetch calls to the API)
```

## API reference

All endpoints are under `/api` and return JSON.

| Method | Path                  | Description                                   |
|--------|------------------------|------------------------------------------------|
| GET    | `/api/services`         | List all services                              |
| POST   | `/api/services`         | Add a service `{ name, category, price, duration }` |
| PATCH  | `/api/services/:id`     | Update or deactivate a service                 |
| GET    | `/api/staff`            | List staff                                     |
| POST   | `/api/staff`            | Add a staff member `{ name, role, phone }`     |
| DELETE | `/api/staff/:id`        | Remove a staff member                          |
| GET    | `/api/bookings`         | List bookings — filter with `?status=`, `?serviceId=`, `?q=` |
| POST   | `/api/bookings`         | Create a booking `{ serviceId, resident, unit, phone, date, time, notes }` |
| PATCH  | `/api/bookings/:ref`    | Update `{ status, staffId, notes }` — status changes are timestamped in `history` |
| DELETE | `/api/bookings/:ref`    | Delete a booking                               |
| GET    | `/api/stats`            | Dashboard aggregates (totals, revenue, breakdowns) |

Booking statuses: `Pending → Confirmed → In Progress → Completed`, or
`Cancelled` at any point.

### Example: create a booking

```bash
curl -X POST http://localhost:3000/api/bookings \
  -H "Content-Type: application/json" \
  -d '{
    "serviceId": "cleaning",
    "resident": "Wanjiru Kamau",
    "unit": "B4-12",
    "phone": "0712 345 678",
    "date": "2026-09-02",
    "time": "09:00",
    "notes": "Gate code 4521"
  }'
```

## What's included

- **New Booking** — pick a service, fill in resident/unit/phone/date/time,
  see the price up front, submit.
- **Bookings Ledger** — searchable, filterable list of every booking with a
  reference code (`EST-0001`…), live status updates, and staff assignment.
- **Staff** — add/remove staff, assign them to bookings from the ledger.
- **Dashboard** — totals, completed/cancelled counts, booked value, and
  breakdowns by service and by status.

## Extending it

- **Swap the database**: replace `readDB`/`writeDB`/`mutateDB` in
  `server.js` with calls to Postgres/SQLite/etc. — the route handlers only
  touch the `db` object, so the rest of the API doesn't need to change.
- **Add auth**: the API has no authentication yet — anyone who can reach the
  server can book, cancel, or edit staff. Add a login/session layer before
  putting this on a network beyond your own machine.
- **Notifications**: hook SMS/WhatsApp/email sends into the `POST
  /api/bookings` and status-change handlers in `server.js`.
