# TFC System

The one system of the Tanzania Fertilizer Company: one Laravel application, one database and one sign-in. Everything below is in this repository; no other system or repository is needed to run it.

| Part | Address | What it does | Main code |
|---|---|---|---|
| Portal | `/` | Sign-in, home page, people and stations | `PortalController`, `AuthController`, `Admin/*` |
| Helpdesk | `/helpdesk` | Ask for help; support staff fix it | `HelpdeskController` |
| Uwajibikaji / Tasks | `/uwajibikaji` | Tasks with deadlines, warnings for missed ones | `DutyController` |
| Daily Report | `/reports` | Each station's daily sales report | `DailyReportController` |
| Meetings | `/meetings` | Meetings and series, agenda, attendance, minutes, decisions, action items, calendar, year planner, rooms, groups | `MeetingController`, `MeetingSpaceController`, `Services/Meetings`, `Support/MeetingUi` |
| Protocol | `/protocol` | Approved letter templates, documents with reference numbers, Word and print, the handbook | `ProtocolController`, `Services/ProtocolLetter` |

## Set up

Requires PHP 8.3+ with the `zip` and `pdo_sqlite` extensions, Composer, and Node.js to build the styles.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm install && npm run build
php artisan tfc:admin you@example.com   # creates the first administrator
php artisan serve
```

Run the tests with `php artisan test`. They use an in-memory database.

## How it fits together

- **People.** There is one `users` table. A person's department, job title, `meeting_role` and `protocol_role` are set under People & access. A portal administrator (`is_admin`) is the administrator of Meetings and Protocol too.
- **Departments.** There is one `departments` table, shared by Meetings and Protocol. Its `code` goes into Protocol's letter references.
- **Meetings** uses the `meeting_*` tables. Times are Tanzania local text (`YYYY-MM-DD HH:MM`). Its pages are Blade views in `resources/views/meetings`, with their own layout and the styles and script in `public/meeting-app`.
- **Protocol** has a browser app in `public/protocol-app` that talks to the JSON routes under `/protocol/api`. A template, a document or a handbook rule is one JSON record in its table, in the shape the app uses. Inside those records, person and department ids are text.
- **Letters.** `public/protocol-app/render.js` draws a letter on screen and for printing; `Services/ProtocolLetter` writes the Word file. They share the letterhead layout, so change them together.
- **Files** are kept on the `local` disk: `storage/app/private/meetings` and `storage/app/private/protocol`. Keep the public folders `meeting-app` and `protocol-app` named differently from the addresses `/meetings` and `/protocol`, or nginx will answer with the folder.

## Bringing in the old separate systems

Meetings and Protocol used to run as separate applications. `php artisan tfc:import-old-systems` copies their data into this system once and only reads from them. Add `--fresh` to replace what was copied with a new copy. New installations do not need it.
