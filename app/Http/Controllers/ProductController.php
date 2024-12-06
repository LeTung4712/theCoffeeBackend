<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Topping;
use App\Models\ToppingProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class ProductController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin', [
            'except' => ['index', 'indexByCategoryId', 'getProductInfo', 'update', 'create', 'delete']
        ]);
        if (!auth('admin')->check()) { //
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }
    }

    //lấy danh sách sản phẩm
    public function index()
    {
        $productList = Product::orderby('id')->get();

        return $productList->isNotEmpty()
            ? response(['products' => $productList], 200)
            : response(['message' => 'Không có sản phẩm nào'], 404);
    }

    //thêm sản phẩm
    public function create(Request $request)
    {
        $existingProduct = Product::where('name', $request->name)->first();
        if ($existingProduct) {
            return response()->json(['message' => 'Đã có sản phẩm này'], 409);
        }

        $productData = $request->only(['name', 'category_id', 'description', 'price', 'price_sale', 'image_url']);
        $productData['active'] = true;

        try {
            $product = Product::create($productData);

            if ($request->has('toppings')) {
                $toppings = array_map(function ($toppingId) use ($product) {
                    return ['product_id' => $product->id, 'topping_id' => $toppingId];
                }, $request->toppings);
                ToppingProduct::insert($toppings);
            }

            return response()->json(['message' => 'Thêm sản phẩm thành công'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Thêm sản phẩm thất bại', 'error' => $e->getMessage()], 400);
        }
    }

    //lấy danh sách category con
    public function getChild($parent)
    {
        return Category::where('parent_id', $parent->id)->orderby('id')->get();
    }

    //lấy danh sách sản phẩm theo category_id
    //ý tưởng: lấy danh sách category theo category_id, 
    //sau đó lấy tất cả category con của category đó, sau đó lấy tất cả product theo category_id
    public function indexByCategoryId(Request $request)
    {
        $categoryList = Category::where('id', $request->category_id)->get();
        $allCategories = $categoryList->merge($categoryList->map(function ($category) {
            return $this->getChild($category);
        })->flatten());

        $productList = Product::whereIn('category_id', $allCategories->pluck('id'))
            ->where('active', true)
            ->orderby('id')
            ->get();

        // Chuyển đổi các giá trị số sang dạng số cho từng sản phẩm
        foreach ($productList as $product) {
            $product->price = (float) $product->price;
            $product->price_sale = (float) $product->price_sale;
        }
        

        return $productList->isNotEmpty()
            ? response(['message' => 'Lấy danh sách sản phẩm thành công', 'products' => $productList], 200)
            : response(['message' => 'Không có sản phẩm nào'], 404);
    }


    //lấy thông tin sản phẩm
    public function getProductInfo(Request $request)
    {
        $productInfo = Product::with('toppingProducts')
            ->where('id', $request->product_id)
            ->where('active', true)
            ->first();

        // Kiểm tra xem sản phẩm có tồn tại không
        if (!$productInfo) {
            return response(['message' => 'Không có sản phẩm này trong dữ liệu'], 404);
        }

        // Chuyển đổi các giá trị số sang dạng số
        $productInfo->price = (float) $productInfo->price;
        $productInfo->price_sale = (float) $productInfo->price_sale;

        $sameProductList = Product::where('category_id', $productInfo->category_id)
            ->where('active', true)
            ->where('id', '<>', $productInfo->id) // Loại bỏ sản phẩm hiện tại
            ->get();

        // Chuyển đổi các giá trị số sang dạng số cho từng sản phẩm
        foreach ($sameProductList as $product) {
            $product->price = (float) $product->price;
            $product->price_sale = (float) $product->price_sale;
        }
        
        return response([
            'message' => 'Lấy thông tin sản phẩm thành công',
            'product' => $productInfo,
            'same_products' => $sameProductList,
        ], 200);
    }

    //cập nhật sản phẩm
    public function update(Request $request)
    {
        $product = Product::find($request->input('id'));
        if (!$product) {
            return response()->json(['message' => 'Không có sản phẩm này trong dữ liệu'], 404);
        }

        if (!$this->isValidPrice($request)) {
            return response()->json(['message' => 'Giá giảm phải nhỏ hơn giá gốc hoặc vui lòng nhập giá gốc'], 400);
        }

        try {
            $product->update($request->all());
            return response()->json(['message' => 'Cập nhật thành công', 'product' => $product], 200);
        } catch (\Exception $err) {
            return response()->json(['message' => 'Cập nhật thất bại', 'error' => $err->getMessage()], 400);
        }
    }

    protected function isValidPrice($request)
    {
        if ($request->input('price_sale') > $request->input('price')) {
            Session::flash('error', 'Giá giảm phải nhỏ hơn giá gốc');
            return false;
        }

        if ($request->input('price_sale') != 0 && (int) $request->input('price') == 0) {
            Session::flash('error', 'Vui lòng nhập giá gốc');
            return false;
        }

        return true;
    }

    //xóa sản phẩm
    public function delete(Request $request)
    {
        $product = Product::find($request->input('id'));
        if (!$product) {
            return response()->json(['message' => 'Không có sản phẩm này trong dữ liệu'], 404);
        }

        try {
            $product->delete();
            return response()->json(['message' => 'Xóa sản phẩm thành công'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Xóa sản phẩm thất bại', 'error' => $e->getMessage()], 400);
        }
    }
}
