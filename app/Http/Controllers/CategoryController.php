<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin', [
            'except' => ['index', 'indexByParentId', 'create', 'delete', 'update'],
        ]);
        if (!auth('admin')->check()) { //
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }
    }

    // Create category
    public function create(Request $request)
    {
        if (Category::where('name', $request->name)->exists()) {
            return response(['message' => 'Đã có danh mục này'], 409);
        }

        $category = Category::create([
            'name' => $request->input('name'),
            'parent_id' => $request->input('parent_id') ? (int) $request->input('parent_id') : null,
            'image_url' => $request->input('image_url'),
            'active' => true,
        ]);

        return $category
            ? response(['message' => 'Thêm danh mục thành công', 'category' => $category], 201)
            : response(['message' => 'Thêm danh mục không thành công'], 400);
    }

    // Delete category by id
    public function delete(Request $request)
    {
        $id = (int) $request->input('id');
        $category = Category::find($id);

        if (!$category) {
            return response()->json(['message' => 'Không có danh mục'], 404);
        }

        $result = Category::where('id', $id)->orWhere('parent_id', $id)->delete();

        return $result
            ? response()->json(['message' => 'Xóa thành công danh mục'], 200)
            : response()->json(['message' => 'Xóa thành công không danh mục'], 400);
    }

    // Get all categories
    public function index()
    {
        $categories = Category::orderBy('id')->get();

        return $categories->isNotEmpty()
            ? response(['message' => 'Lấy danh mục thành công', 'categories' => $categories], 200)
            : response(['message' => 'Không có danh mục'], 404);
    }

    // Get categories by parent_id
    public function indexByParentId(Request $request)
    {
        $parent_id = $request->input('parent_id');
        $categories = Category::when($parent_id, function ($query, $parent_id) {
            return $query->where('parent_id', $parent_id);
        }, function ($query) {
            return $query->whereNull('parent_id');
        })->where('active', true)->orderBy('id')->get();

        return $categories->isNotEmpty()
            ? response(['message' => 'Lấy danh mục thành công', 'categories' => $categories], 200)
            : response(['message' => 'Không có danh mục có parent_id = ' . $parent_id], 404);
    }

    // Update category by id
    public function update(Request $request)
    {
        $category = Category::find($request->id);

        if (!$category) {
            return response(['message' => 'Không có danh mục'], 404);
        }

        $category->update([
            'name' => $request->input('name'),
            'parent_id' => $request->input('parent_id') !== null && $request->input('parent_id') != $category->id
                ? (int) $request->input('parent_id')
                : null,
            'image_url' => $request->input('image_url'),
            'active' => (boolean) $request->input('active'),
        ]);

        return $category
            ? response(['message' => 'Cập nhật thành công', 'category' => $category], 200)
            : response(['message' => 'Cập nhật không thành công'], 400);
    }
}
