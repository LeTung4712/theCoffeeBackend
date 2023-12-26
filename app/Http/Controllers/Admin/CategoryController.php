<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    //create category
    public function create(Request $request)
    {
        if (Category::where('name', $request->name)->first()) {
            return response([
                'message' => 'Đã có danh mục này',
            ]);
        }
        $category = Category::create([
            'name' => (string) $request->input('name'),
            'parent_id' => (int) $request->input('parent_id'),
            'image_url' => (string) $request->input('image_url')
        ]);
        if ($category) {
            return response([
                'message' => 'Thêm danh mục thành công',
                'category' => $category,
            ], 200);
        }
        return response([
            'message' => 'Thêm danh mục không thành công',
        ], 500);
    }
    //delete category by id
    public function delete(Request $request)
    {
        $id = (int) $request->input('id');
        $category = Category::where('id', $id)->first();
        if ($category) {
            $result = Category::where('id', $id)->orWhere('parent_id', $id)->delete();
        }
        if ($result) {
            return response()->json([
                'message' => 'Xóa thành công danh mục',
            ], 200);
        }

        return response()->json([
            'message' => 'Xóa thành công không danh mục',
        ], 500);
    }
    //get all category
    public function index()
    {
        $categories = Category::orderby('id')->get();
        if ($categories) {
            return response([
                'categories' => $categories,
            ], 200);
        }
        return response([
            'message' => 'Không có danh mục',
        ], 500);
    }
    //get category by parent_id
    public function indexByParentId(Request $request)
    {
        $categories = Category::where('parent_id', $request->parent_id)
            ->orderby('id')->get();
        if ($categories) {
            return response([
                'categories' => $categories,
            ], 200);
        }
        return response([
            'message' => 'Không có danh mục',
        ], 500);
    }
    //update category by id
    public function update(Request $request) 
    {
        $category = Category::find($request->id);
        if ($request->input('parent_id') != null && $request->input('parent_id') != $category->id) {
            $category->parent_id = (int) $request->input('parent_id');
        }

        $category->name = (string) $request->input('name');
        $category->save();
        if ($category) {
            return response([
                'message' => 'Cập nhật thành công',
                'category' => $category,
            ], 200);
        }
        return response([
            'message' => 'Cập nhật không thành công',
        ], 500);
               
    }

}
