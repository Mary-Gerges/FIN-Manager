<?php
// app/Http/Controllers/Api/InsightController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InsightService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * InsightController
 * AI-powered spending analysis and recommendations
 */
class InsightController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly InsightService $insightService
    ) {}

    /**
     * GET /api/insights
     * Generate financial insights for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $insights = $this->insightService->generateInsights($request->user());
        return $this->successResponse($insights, 'Insights generated successfully');
    }
}
