<?php

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\CandidateRepository;
use App\Repositories\EmployerRepository;
use App\Repositories\RecruiterRepository;
use App\Services\AccountService;
use App\Services\Auth\AuthService;

class AccountsController extends ApiController
{
    public function __construct(
        AuthService $auth,
        private AccountService $accounts,
        private CandidateRepository $candidates,
        private EmployerRepository $employers,
        private RecruiterRepository $recruiters
    ) {
        parent::__construct($auth);
    }

    public function index(Request $request, string $type): Response
    {
        $repositories = [
            'candidates' => $this->candidates,
            'employers' => $this->employers,
            'recruiters' => $this->recruiters,
        ];

        if (!isset($repositories[$type])) {
            return $this->error('Unsupported account type.', 404);
        }

        $perPage = max(1, (int) $request->query('per_page', 25));
        $page = max(1, (int) $request->query('page', 1));
        $paginator = $repositories[$type]->query()->paginate($perPage, $page);

        $items = array_map(static function ($item) {
            if (is_object($item) && method_exists($item, 'toArray')) {
                return $item->toArray();
            }

            return (array) $item;
        }, $paginator['data']);

        return $this->success($items, 200, $paginator['meta']);
    }

    public function store(Request $request, string $type): Response
    {
        return match ($type) {
            'candidates' => $this->success(['user' => $this->accounts->registerCandidate($request->all())->toArray()], 201),
            'employers' => $this->success(['user' => $this->accounts->registerEmployer($request->all())->toArray()], 201),
            'recruiters' => $this->success(['user' => $this->accounts->registerRecruiter($request->all())->toArray()], 201),
            default => $this->error('Unsupported account type.', 404),
        };
    }
}
