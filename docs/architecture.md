# Application Architecture Overview

This document walks through how HireMe handles a request, from rendering the
front page to executing the various operations of the system.

## Bootstrapping

1. A request enters the application via **`public/index.php`**.  This file is the
   front controller and sets up the request routing.
2. It loads **`app/bootstrap.php`**, which:
   - Registers autoloaders for the `App\` namespace.
   - Loads environment variables from `.env`.
   - Configures error handling and sessions.
   - Initializes the dependency container and an Eloquent ORM connection.

## Routing

`public/index.php` creates an instance of **`App\Core\Router`** and registers a
series of routes.  Each route maps a path and HTTP method to a controller method.
The router normalizes the incoming URL and dispatches the request to the matching
handler.  Unmatched paths return a `404` view if available.

## Rendering the Front Page

The home page is served by **`App\Controllers\HomeController::index`**.  It
chooses the view file `app/Views/home.php` and includes `app/Views/layout.php`
so that the home view is rendered within the common layout.

## Core Operations

Major features are implemented through dedicated controllers:

- **Authentication** – `AuthController` manages login, registration and password
  resets.
- **Jobs** – `JobController` lets employers create, edit and manage job postings
  while candidates browse and apply.
- **Applications** – `ApplicationController` handles the submission and status
  of job applications.
- **Profiles** – `CandidateController` and `EmployerController` manage personal
  or company profiles.
- **Payments** – `PaymentController` supports premium badges, credit purchases
  and Stripe webhooks.
- **Administration** – `AdminController` exposes CRUD interfaces and metrics for
  administrators.

Controllers may delegate complex logic to service classes in `app/Services` and
persist data through Eloquent models found under `app/Models`.

### Operation steps

Each feature follows a model–view–controller cycle:

1. **Model** – an Eloquent model interacts with the database.
2. **Controller** – the controller method invokes the model and prepares data.
3. **View** – a PHP template renders HTML (or JSON for API calls).

For example:

- **Authentication** – the `User` model verifies credentials, `AuthController`
  calls it and passes errors or the user object to views under
  `app/Views/auth/`.
- **Jobs** – `JobPosting` persists job data, `JobController` loads the
  collection and renders job lists and forms in `app/Views/jobs/`.
- **Applications** – the `Application` model stores submissions, while
  `ApplicationController` shows forms or status pages in
  `app/Views/applications/`.
- **Profiles** – `Candidate` and `Employer` models hold profile information;
  their respective controllers send the data to views in `app/Views/profile/`.
- **Payments** – `Payment` and `StripePayment` models record transactions;
  `PaymentController` handles purchase requests and returns receipts via views
  in `app/Views/payments/`.
- **Administration** – `Admin` models expose metrics and CRUD operations; the
  `AdminController` renders administrative tables and forms in
  `app/Views/admin/`.

## Object-Relational Mapping

Models extend `App\Models\BaseModel`, which in turn extends Laravel's
`Illuminate\Database\Eloquent\Model`.  This provides an Active Record style
ORM: models map directly to database tables, offer query-builder methods like
`find`, `create`, and relationship helpers, and automatically serialize records
to arrays or JSON.

## Web Services (REST API)

Besides HTML pages, HireMe exposes a REST-style API under `/api/*` routes.
`public/index.php` registers endpoints such as `/api/users/{type}` and
`/api/accounts/{type}`.  `UserController` and `AccountController` respond to
HTTP verbs (`GET`, `POST`) and return JSON.  Controllers retrieve data through
the same Eloquent models and send responses via `echo` with the appropriate
`Content-Type` header.

## Views and Layout

Views live in `app/Views` and are simple PHP templates.  Controllers set a
`$viewFile` and include `app/Views/layout.php`, which provides shared markup and
access to the `BASE_URL` constant for generating links.

## Summary

Requests flow from `public/index.php` through the router to controller methods.
Controllers orchestrate services and models, then render PHP views within a
common layout to produce the HTML response.
