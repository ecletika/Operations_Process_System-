<?php

declare(strict_types=1);

namespace App\Modules\Process\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Helpers\PhoneHelper;
use App\Helpers\PlateHelper;
use App\Modules\Process\Repositories\ProcessRepository;

/**
 * RF-0036 / RF-0037 - Pesquisa Global e Pesquisa Inteligente.
 */
final class SearchController extends Controller
{
    public function index(Request $request): never
    {
        $query = trim((string) $request->input('q', ''));

        $results = $query !== ''
            ? (new ProcessRepository())->search($query, PlateHelper::normalize($query), PhoneHelper::normalize($query))
            : [];

        $this->view('Process/Views/search', [
            'query' => $query,
            'results' => $results,
        ]);
    }
}
