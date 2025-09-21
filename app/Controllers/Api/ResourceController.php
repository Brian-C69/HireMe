<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Models\Admin;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\Employer;
use App\Models\JobPosting;
use App\Models\Payment;
use App\Models\Recruiter;
use App\Models\Resume;
use App\Models\ResumeUnlock;
use App\Models\StripePayment;
use Illuminate\Database\Eloquent\Model;

class ResourceController extends ApiController
{
    /**
     * Map of API resource names to their backing Eloquent models.
     *
     * @var array<string, class-string<Model>>
     */
    private const RESOURCE_MAP = [
        'admins' => Admin::class,
        'candidates' => Candidate::class,
        'employers' => Employer::class,
        'recruiters' => Recruiter::class,
        'jobs' => JobPosting::class,
        'job-postings' => JobPosting::class,
        'applications' => Application::class,
        'resumes' => Resume::class,
        'resume-unlocks' => ResumeUnlock::class,
        'payments' => Payment::class,
        'stripe-payments' => StripePayment::class,
    ];

    public function index(Request $request, string $resource): Response
    {
        $modelClass = $this->resolveModel($resource);
        if ($modelClass === null) {
            return $this->error('Unknown resource.', 404);
        }

        $perPage = (int) $request->query('per_page', 25);
        $perPage = $perPage > 0 ? min($perPage, 100) : 25;
        $page = (int) $request->query('page', 1);
        $page = $page > 0 ? $page : 1;

        $query = $modelClass::query();
        $total = (clone $query)->count();
        $items = $query
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(static fn (Model $model) => $model->toArray())
            ->all();

        $meta = [
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'total_pages' => $perPage > 0 ? (int) ceil($total / $perPage) : 0,
        ];

        return $this->success($items, 200, $meta);
    }

    public function show(Request $request, string $resource, int|string $id): Response
    {
        $modelClass = $this->resolveModel($resource);
        if ($modelClass === null) {
            return $this->error('Unknown resource.', 404);
        }

        $record = $modelClass::find($id);
        if ($record === null) {
            return $this->error('Resource not found.', 404);
        }

        return $this->success($record->toArray());
    }

    public function store(Request $request, string $resource): Response
    {
        $modelClass = $this->resolveModel($resource);
        if ($modelClass === null) {
            return $this->error('Unknown resource.', 404);
        }

        $payload = $this->payload($request);
        unset($payload['id']);

        /** @var Model $record */
        $record = $modelClass::create($payload);

        return $this->success($record->toArray(), 201);
    }

    public function update(Request $request, string $resource, int|string $id): Response
    {
        $modelClass = $this->resolveModel($resource);
        if ($modelClass === null) {
            return $this->error('Unknown resource.', 404);
        }

        $record = $modelClass::find($id);
        if ($record === null) {
            return $this->error('Resource not found.', 404);
        }

        $payload = $this->payload($request);
        unset($payload['id']);

        $record->fill($payload);
        $record->save();

        return $this->success($record->toArray());
    }

    public function destroy(Request $request, string $resource, int|string $id): Response
    {
        $modelClass = $this->resolveModel($resource);
        if ($modelClass === null) {
            return $this->error('Unknown resource.', 404);
        }

        $record = $modelClass::find($id);
        if ($record === null) {
            return $this->error('Resource not found.', 404);
        }

        $record->delete();

        return $this->success(['deleted' => true]);
    }

    /**
     * Resolve the model class for a resource key.
     */
    private function resolveModel(string $resource): ?string
    {
        $key = strtolower(trim($resource));
        return self::RESOURCE_MAP[$key] ?? null;
    }

    /**
     * Extract the payload from the request, preferring JSON bodies.
     *
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $json = $request->json();
        if (is_array($json)) {
            return $json;
        }

        $input = $request->all();
        if (!is_array($input)) {
            return [];
        }

        return $this->withoutQueryParameters($input, $request);
    }

    /**
     * Remove common query parameters from a payload array to avoid
     * accidentally persisting pagination/filter controls.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function withoutQueryParameters(array $input, Request $request): array
    {
        $queryKeys = array_keys($request->query());
        foreach ($queryKeys as $key) {
            unset($input[$key]);
        }

        return $input;
    }
}
