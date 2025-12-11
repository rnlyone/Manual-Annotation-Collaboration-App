<h1 align="center">Manual Annotation Collaboration App</h1>

This project is a Laravel-based platform for teams that annotate text data together. It pairs a streamlined annotator workbench with an administrator console so you can plan packages, track sessions, and requeue low-quality annotations without leaving the browser.

> 🎓 Built by **rnlyone (Roesman Ridwna Raja)** to power NLP-focused research initiatives, with a special emphasis on high-quality manual text annotation workflows.

## Key Features

- **Annotator workbench** – Assigned annotators get a focused view with category shortcuts, keyboard-friendly controls, and automatic routing to unannotated records only.
- **Annotation map** – Visual timeline of the current session that lets annotators jump to any saved item or continue sequentially while keeping context.
- **Session tracking** – Every work session records annotation IDs, timestamps, and the owning package so admins can audit progress.
- **Package assignment** – Packages can be linked to one or more annotators, and access control ensures only assigned users can annotate a package.
- **Management dashboard** – Filter the annotation table by scope (all, package, or session) plus annotator, and review enriched metadata (categories, session label, humanized timestamps).
- **Package progress overview** – Inline progress bars summarize each package’s completed vs. total items and highlight remaining workload plus assignees.
- **Bulk requeue workflow** – Select rows (or auto-select every “No categories” item across pages) and trigger a confirmation modal that deletes annotations and notifies responsible annotators.
- **Notification service** – Annotators receive contextual messages (e.g., when items are requeued) so nothing falls through the cracks.

## Tech Stack

- Laravel 10 + PHP 8
- Blade templates with Bootstrap 5 and jQuery/DataTables for the management UI
- SweetAlert2 for confirmation flows
- Vite + Laravel Mix asset pipeline

## Local Development

1. Clone the repo and install PHP dependencies:

```bash
composer install
```

2. Install frontend dependencies:

```bash
npm install && npm run dev
```

3. Copy `.env.example` to `.env`, configure your database, and run migrations + seeders as needed:

```bash
php artisan migrate --seed
```

4. Start the local server:

```bash
php artisan serve
```

## Testing

Run the default Laravel test suite:

```bash
php artisan test
```

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
