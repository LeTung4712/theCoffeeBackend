<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{

    // Create category
    public function create(Request $request)
    {
        if (Category::where('name', $request->name)->exists()) {
            return response(['message' => 'Đã có danh mục này'], 409);
        }

        $category = Category::create([
            'name'      => $request->input('name'),
            'parent_id' => $request->input('parent_id') ? (int) $request->input('parent_id') : null,
            'image_url' => $request->input('image_url'),
            'active'    => true,
        ]);

        return $category
        ? response()->json([
            'status'  => true,
            'message' => 'Thêm danh mục thành công',
            'data'    => [
                'category' => $category,
            ],
        ], 201)
        : response()->json([
            'status'  => false,
            'message' => 'Thêm danh mục không thành công',
        ], 400);
    }

    // Delete category by id
    public function delete($id)
    {
        $category = Category::find($id);

        if (! $category) {
            return response()->json(['message' => 'Không có danh mục'], 404);
        }

        $result = Category::where('id', $id)->orWhere('parent_id', $id)->delete();

        return $result
        ? response()->json([
            'status'  => true,
            'message' => 'Xóa thành công danh mục',
        ], 200)
        : response()->json([
            'status'  => false,
            'message' => 'Xóa thành công không danh mục',
        ], 400);
    }

    // Get all categories
    public function index()
    {
        $categories = Category::orderBy('id')->get();

        return $categories->isNotEmpty()
        ? response()->json([
            'status'  => true,
            'message' => 'Lấy danh mục thành công',
            'data'    => [
                'categories' => $categories,
            ],
        ], 200)
        : response()->json([
            'status'  => false,
            'message' => 'Không có danh mục',
        ], 404);
    }

    // Get all active categories
    public function indexActive()
    {
        $categories = Category::where('active', true)->orderBy('id')->get();

        return $categories->isNotEmpty()
        ? response()->json([
            'status'  => true,
            'message' => 'Lấy danh mục thành công',
            'data'    => [
                'categories' => $categories,
            ],
        ], 200)
        : response()->json([
            'status'  => false,
            'message' => 'Không có danh mục',
        ], 404);
    }

    // Get categories by parent_id
    public function indexByParentId($parent_id)
    {
        $parent = Category::where('id', $parent_id)
            ->where('active', true)
            ->first();
        if (! $parent) {
            return response()->json([
                'status'  => false,
                'message' => 'Danh mục cha không tồn tại hoặc đã bị vô hiệu hóa',
            ], 404);
        }
        $subcategories = Category::where('parent_id', $parent_id)
            ->where('active', true)
            ->orderBy('id')
            ->get();

        if ($subcategories->isEmpty()) {
            return response()->json([
                'status'  => false,
                'message' => 'Không có danh mục con phù hợp với parent_id',
            ], 404);
        }

        return response()->json([
            'status'  => true,
            'message' => 'Lấy danh mục con thành công',
            'data'    => [
                'categories' => $subcategories,
            ],
        ], 200);
    }

    // Update category by id
    public function update($id, Request $request)
    {
        $category = Category::find($id);

        if (! $category) {
            return response(['message' => 'Không có danh mục'], 404);
        }

        $category->update([
            'name'      => $request->input('name'),
            'parent_id' => $request->input('parent_id') !== null && $request->input('parent_id') != $category->id
            ? (int) $request->input('parent_id')
            : null,
            'image_url' => $request->input('image_url'),
            'active'    => (boolean) $request->input('active'),
        ]);

        return $category
        ? response()->json([
            'status'  => true,
            'message' => 'Cập nhật thành công',
            'data'    => [
                'category' => $category,
            ],
        ], 200)
        : response()->json([
            'status'  => false,
            'message' => 'Cập nhật không thành công',
        ], 400);
    }
}
