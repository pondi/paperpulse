<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\User;
use App\Services\SharingService;
use Exception;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CategoryController extends Controller
{
    protected $sharingService;

    public function __construct(SharingService $sharingService)
    {
        $this->sharingService = $sharingService;
    }

    /**
     * Display a listing of the categories.
     */
    public function index()
    {
        $categories = auth()->user()->categories()
            ->ordered()
            ->withCount(['receipts', 'documents'])
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'color' => $category->color,
                    'icon' => $category->icon,
                    'description' => $category->description,
                    'receipt_count' => $category->receipts_count,
                    'document_count' => $category->documents_count,
                    'total_amount' => $category->total_amount,
                    'is_active' => $category->is_active,
                    'sort_order' => $category->sort_order,
                ];
            });

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
        ]);
    }

    /**
     * Store a newly created category.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'icon' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
        ]);

        auth()->user()->categories()->create([
            'name' => $request->name,
            'slug' => Category::generateUniqueSlug($request->name, auth()->id()),
            'color' => $request->color ?? '#6B7280',
            'icon' => $request->icon,
            'description' => $request->description,
            'sort_order' => auth()->user()->categories()->max('sort_order') + 1,
        ]);

        return redirect()->back()->with('success', 'Category created successfully.');
    }

    /**
     * Update the specified category.
     */
    public function update(Request $request, Category $category)
    {
        $this->authorize('update', $category);

        $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'icon' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);

        $data = [
            'name' => $request->name,
            'color' => $request->color ?? '#6B7280',
            'icon' => $request->icon,
            'description' => $request->description,
            'is_active' => $request->is_active ?? true,
        ];

        // Only update slug if name changed
        if ($request->name !== $category->name) {
            $data['slug'] = Category::generateUniqueSlug($request->name, auth()->id(), $category->id);
        }

        $category->update($data);

        return redirect()->back()->with('success', 'Category updated successfully.');
    }

    /**
     * Remove the specified category.
     */
    public function destroy(Category $category)
    {
        $this->authorize('delete', $category);

        // Check if category has receipts or documents
        if ($category->receipts()->exists() || $category->documents()->exists()) {
            return redirect()->back()->with('error', 'Cannot delete category with items. Please reassign items first.');
        }

        $category->delete();

        return redirect()->back()->with('success', 'Category deleted successfully.');
    }

    /**
     * Update the sort order of categories.
     */
    public function updateOrder(Request $request)
    {
        $request->validate([
            'categories' => 'required|array',
            'categories.*.id' => 'required|integer|exists:categories,id',
            'categories.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($request->categories as $categoryData) {
            $category = Category::find($categoryData['id']);

            // Ensure the user owns the category
            if ($category && $category->user_id === auth()->id()) {
                $category->update(['sort_order' => $categoryData['sort_order']]);
            }
        }

        return response()->json(['message' => 'Sort order updated successfully']);
    }

    /**
     * Create default categories for a new user.
     */
    public function createDefaults()
    {
        $user = auth()->user();

        // Check if user already has categories
        if ($user->categories()->exists()) {
            return redirect()->back()->with('info', 'You already have categories.');
        }

        $defaults = Category::getDefaultCategories();

        foreach ($defaults as $index => $default) {
            $user->categories()->create([
                'name' => $default['name'],
                'slug' => $default['slug'],
                'color' => $default['color'],
                'icon' => $default['icon'],
                'sort_order' => $index,
            ]);
        }

        return redirect()->back()->with('success', 'Default categories created successfully.');
    }

    /**
     * Share category with another user
     */
    public function share(Request $request, Category $category)
    {
        $this->authorize('share', $category);

        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
            'permission' => 'required|in:view,edit',
        ]);

        try {
            $user = User::where('email', $validated['email'])->first();

            if ($user->id === auth()->id()) {
                return back()->with('error', 'You cannot share with yourself');
            }

            $this->sharingService->shareCategory(
                $category,
                $validated['email'],
                $validated['permission']
            );

            return back()->with('success', 'Category shared successfully');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Remove category share
     */
    public function unshare(Category $category, int $userId)
    {
        $this->authorize('share', $category);

        try {
            $this->sharingService->unshareCategory($category, $userId);

            return back()->with('success', 'Share removed successfully');
        } catch (Exception) {
            return back()->with('error', 'Failed to remove share');
        }
    }
}
