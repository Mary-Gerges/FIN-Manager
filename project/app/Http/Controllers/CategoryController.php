<?php
// app/Http/Controllers/Api/CategoryController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * CategoryController
 * Manage system and user-defined categories
 */
class CategoryController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/categories
     * Get all categories available to the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $type = $request->get('type'); // 'income' | 'expense' | null

        $query = Category::where('is_active', true)
            ->where(function ($q) use ($request) {
                $q->where('is_system', true)
                  ->orWhere('user_id', $request->user()->id);
            });

        if ($type && in_array($type, ['income', 'expense'])) {
            $query->where(function ($q) use ($type) {
                $q->where('type', $type)->orWhere('type', 'both');
            });
        }

        $categories = $query->orderBy('is_system', 'desc')
                            ->orderBy('name')
                            ->get()
                            ->map(fn($c) => [
                                'id'        => $c->id,
                                'name'      => $c->name,
                                'type'      => $c->type,
                                'icon'      => $c->icon,
                                'color'     => $c->color,
                                'is_system' => $c->is_system,
                            ]);

        return $this->successResponse($categories, 'Categories retrieved successfully');
    }

    /**
     * POST /api/categories
     * Create a custom user category
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'  => ['required', 'string', 'min:2', 'max:100'],
            'type'  => ['required', 'in:income,expense,both'],
            'icon'  => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray());
        }

        // Prevent duplicate names for this user
        $exists = Category::where('user_id', $request->user()->id)
            ->where('name', $request->name)
            ->exists();

        if ($exists) {
            return $this->errorResponse('A category with this name already exists', 409);
        }

        $category = Category::create([
            'user_id'   => $request->user()->id,
            'name'      => $request->name,
            'type'      => $request->type,
            'icon'      => $request->icon ?? 'tag',
            'color'     => $request->color ?? '#6366f1',
            'is_system' => false,
            'is_active' => true,
        ]);

        return $this->successResponse([
            'id'        => $category->id,
            'name'      => $category->name,
            'type'      => $category->type,
            'icon'      => $category->icon,
            'color'     => $category->color,
            'is_system' => false,
        ], 'Category created successfully', 201);
    }

    /**
     * PUT /api/categories/{id}
     * Update a custom category (system categories cannot be modified)
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $category = Category::where('user_id', $request->user()->id)
            ->where('is_system', false)
            ->find($id);

        if (!$category) {
            return $this->notFoundResponse('Category');
        }

        $validator = Validator::make($request->all(), [
            'name'  => ['sometimes', 'string', 'min:2', 'max:100'],
            'type'  => ['sometimes', 'in:income,expense,both'],
            'icon'  => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray());
        }

        $category->update($validator->validated());

        return $this->successResponse($category, 'Category updated successfully');
    }

    /**
     * DELETE /api/categories/{id}
     * Delete a custom category (only if no transactions use it)
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $category = Category::where('user_id', $request->user()->id)
            ->where('is_system', false)
            ->find($id);

        if (!$category) {
            return $this->notFoundResponse('Category');
        }

        // Check for existing transactions
        if ($category->transactions()->exists()) {
            return $this->errorResponse(
                'Cannot delete category with existing transactions. Reassign transactions first.',
                409
            );
        }

        $category->delete();

        return $this->successResponse(null, 'Category deleted successfully');
    }
}
