<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{

    // Create category
    public function create(Request $request)
    {
        if (Category::where('name', $request->name)->exists()) {
            return response()->json([
                'status'  => false,
                'message' => 'Đã có danh mục này',
            ], 409);
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
            return response()->json([
                'status'  => false,
                'message' => 'Không tìm thấy danh mục',
            ], 404);
        }

        try {
            // Lấy tất cả danh mục con
            $childCategories = Category::where('parent_id', $id)->get();
            $categoryIds     = array_merge([$id], $childCategories->pluck('id')->toArray());

            // Kiểm tra xem có sản phẩm nào thuộc danh mục này hoặc danh mục con không
            $hasProducts = Product::whereIn('category_id', $categoryIds)->exists();

            if ($hasProducts) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Không thể xóa danh mục vì đang có sản phẩm thuộc danh mục này',
                ], 400);
            }

            DB::beginTransaction();

            // Xóa danh mục con trước
            if ($childCategories->isNotEmpty()) {
                Category::whereIn('id', $childCategories->pluck('id'))->delete();
            }

            // Sau đó mới xóa danh mục cha
            $category->delete();

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Xóa danh mục thành công',
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => false,
                'message' => 'Không thể xóa danh mục: ' . $e->getMessage(),
            ], 500);
        }
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
            return response()->json([
                'status'  => false,
                'message' => 'Không có danh mục',
            ], 404);
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
