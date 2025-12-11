<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //add sweet alert2 and datatables scripts and styles in template

        $headstyle = '<link rel="stylesheet" href="/assets/vendor/libs/sweetalert2/sweetalert2.css">';
        $headscript = '<script src="https://code.jquery.com/jquery-3.6.4.min.js" integrity="sha256-oP6HI9z1XaZNBrJURtCoUT5SUnxFr8s3BzRl+cbzUq8=" crossorigin="anonymous"></script>
                <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
                <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                <script src="/assets/vendor/libs/datatables/jquery.dataTables.js"></script>
                <script src="/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js"></script>
                <script src="/assets/vendor/libs/datatables-responsive/datatables.responsive.js"></script>
                <script src="/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.js"></script>
                <script src="/assets/vendor/libs/datatables-checkboxes-jquery/datatables.checkboxes.js"></script>';

        return view('_app.app', [
            'content' => 'category.index',
            'headerdata' => ['pagetitle' => 'Category', 'headstyle' => $headstyle, 'headscript' => $headscript],
            'sidenavdata' => ['active' => 'categories'],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    public function tabledata(Request $request)
    {

        if ($request->filled('id')) {
            $category = Category::query()
                ->select(['id', 'name', 'created_at'])
                ->find($request->integer('id'));

            if (! $category) {
                return response()->json(['error' => 'Category not found'], 404);
            }

            return response()->json(['data' => $this->formatCategory($category)]);
        }

        $categories = Category::query()
            ->select(['id', 'name', 'created_at'])
            ->orderBy('id')
            ->get()
            ->map(fn (Category $category) => $this->formatCategory($category));

        return response()->json(['data' => $categories]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //store category
        $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
        ]);

        if ($request->filled('id')) {
            $request->validate([
                'id' => 'required|integer|unique:categories,id',
            ]);
            $category = new Category([
                'id' => $request->integer('id'),
                'name' => $request->input('name'),
            ]);
        } else {
            $category = new Category([
                'name' => $request->input('name'),
            ]);
        }

        $category->save();

        return response()->json(['message' => 'Category created successfully'], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Category $category)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category)
    {
        //update category
        $request->validate([
            'name' => 'required|string|max:255|unique:categories,name,'.$category->id,
        ]);

        if ($category->id != $request->integer('id')) {
            $request->validate([
                'id' => 'required|integer|unique:categories,id',
            ]);
            $category->id = $request->integer('id');
        }
        $category->name = $request->input('name');
        $category->save();

        return response()->json(['message' => 'Category updated successfully'], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        //destroy
        $category->delete();
        return response()->json(['message' => 'Category deleted successfully'], 200);
    }

    /**
     * Format category data for JSON response.
     */
    private function formatCategory(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'created_at' => $category->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
