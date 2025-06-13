<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ToppingProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    //lấy danh sách sản phẩm
    public function index()
    {
        $productList = Product::orderby('id')->get();

        return $productList->isNotEmpty()
        ? response()->json([
            'status'  => true,
            'message' => 'Lấy danh sách sản phẩm thành công',
            'data'    => [
                'products' => $productList,
            ],
        ], 200)
        : response()->json([
            'status'  => false,
            'message' => 'Không có sản phẩm nào',
        ], 404);
    }

    // Get all active products
    public function indexActive()
    {
        $productList = Product::where('active', true)->orderby('id')->get();

        return $productList->isNotEmpty()
        ? response()->json([
            'status'  => true,
            'message' => 'Lấy danh sách sản phẩm thành công',
            'data'    => [
                'products' => $productList,
            ],
        ], 200)
        : response()->json([
            'status'  => false,
            'message' => 'Không có sản phẩm nào',
        ], 404);
    }

    //thêm sản phẩm
    public function create(Request $request)
    {
        // Validate dữ liệu đầu vào
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'description' => 'nullable|string',
            'price'       => 'required|numeric|min:0',
            'price_sale'  => 'nullable|numeric|min:0',
            'image_url'   => 'nullable|string',
            'toppings'    => 'nullable|array',
            'toppings.*'  => 'exists:toppings,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Kiểm tra tên sản phẩm đã tồn tại chưa
        $existingProduct = Product::where('name', $request->name)->first();
        if ($existingProduct) {
            return response()->json([
                'status'  => false,
                'message' => 'Đã có sản phẩm này',
            ], 409);
        }

        // Kiểm tra giá hợp lệ
        if (! $this->isValidPrice($request)) {
            return response()->json([
                'status'  => false,
                'message' => 'Giá giảm phải nhỏ hơn giá gốc hoặc vui lòng nhập giá gốc',
            ], 400);
        }

        try {
            DB::beginTransaction();

            $productData           = $request->only(['name', 'category_id', 'description', 'price', 'price_sale', 'image_url']);
            $productData['active'] = true;

            $product = Product::create($productData);

            // Xử lý topping nếu có
            if ($request->has('toppings') && ! empty($request->toppings)) {
                ToppingProduct::create([
                    'product_id' => $product->id,
                    'topping_id' => $request->toppings, // Đây là array, sẽ được cast thành JSON
                ]);
            }

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Thêm sản phẩm thành công',
                'data'    => [
                    'product' => $product,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => false,
                'message' => 'Thêm sản phẩm thất bại',
            ], 400);
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
    public function indexByCategoryId($categoryId)
    {
        $categoryList  = Category::where('id', $categoryId)->get();
        $allCategories = $categoryList->merge($categoryList->map(function ($category) {
            return $this->getChild($category);
        })->flatten());

        $productList = Product::whereIn('category_id', $allCategories->pluck('id'))
            ->where('active', true)
            ->orderby('id')
            ->get();

        // Chuyển đổi các giá trị số sang dạng số cho từng sản phẩm
        foreach ($productList as $product) {
            $product->price      = (float) $product->price;
            $product->price_sale = (float) $product->price_sale;
        }

        return $productList->isNotEmpty()
        ? response()->json([
            'status'  => true,
            'message' => 'Lấy danh sách sản phẩm thành công',
            'data'    => [
                'products' => $productList,
            ],
        ], 200)
        : response()->json([
            'status'  => false,
            'message' => 'Không có sản phẩm nào',
        ], 404);
    }

    //lấy thông tin sản phẩm
    public function getProductInfo($id)
    {
        $productInfo = Product::where('id', $id)
            ->where('active', true)
            ->first();

        // Kiểm tra xem sản phẩm có tồn tại không
        if (! $productInfo) {
            return response()->json([
                'status'  => false,
                'message' => 'Không có sản phẩm này trong dữ liệu',
            ], 404);
        }

        // Chuyển đổi các giá trị số sang dạng số
        $productInfo->price      = (float) $productInfo->price;
        $productInfo->price_sale = (float) $productInfo->price_sale;
        $productInfo->toppings   = $productInfo->toppings();
        //không cho topping_products xuất hiện trong response
        $productInfo->makeHidden('toppingProducts');

        $sameProductList = Product::where('category_id', $productInfo->category_id)
            ->where('active', true)
            ->where('id', '<>', $productInfo->id) // Loại bỏ sản phẩm hiện tại
            ->get();

        // Chuyển đổi các giá trị số sang dạng số cho từng sản phẩm
        foreach ($sameProductList as $product) {
            $product->price      = (float) $product->price;
            $product->price_sale = (float) $product->price_sale;
        }

        return response()->json([
            'status'  => true,
            'message' => 'Lấy thông tin sản phẩm thành công',
            'data'    => [
                'product'       => $productInfo,
                'same_products' => $sameProductList,
            ],
        ], 200);
    }

    //cập nhật sản phẩm
    public function update($id, Request $request)
    {
        $product = Product::find($id);
        if (! $product) {
            return response()->json([
                'status'  => false,
                'message' => 'Không có sản phẩm này trong dữ liệu',
            ], 404);
        }

        // Validate dữ liệu đầu vào
        $validator = Validator::make($request->all(), [
            'name'        => 'sometimes|string|max:255',
            'category_id' => 'sometimes|exists:categories,id',
            'description' => 'sometimes|string',
            'price'       => 'sometimes|numeric|min:0',
            'price_sale'  => 'sometimes|numeric|min:0',
            'image_url'   => 'sometimes|string',
            'active'      => 'sometimes|boolean',
            'toppings'    => 'sometimes|array',
            'toppings.*'  => 'exists:toppings,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors'  => $validator->errors(),
            ], 422);
        }

        if (! $this->isValidPrice($request)) {
            return response()->json([
                'status'  => false,
                'message' => 'Giá giảm phải nhỏ hơn giá gốc hoặc vui lòng nhập giá gốc',
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Cập nhật thông tin sản phẩm
            $productData = $request->only(['name', 'category_id', 'description', 'price', 'price_sale', 'image_url', 'active']);
            $product->update($productData);

            // Xử lý topping
            if ($request->has('toppings')) {
                // Xóa topping cũ nếu có
                ToppingProduct::where('product_id', $product->id)->delete();

                // Thêm topping mới nếu có
                if (! empty($request->toppings)) {
                    ToppingProduct::create([
                        'product_id' => $product->id,
                        'topping_id' => $request->toppings, // Đây là array, sẽ được cast thành JSON
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Cập nhật thành công',
                'data'    => [
                    'product' => $product,
                ],
            ], 200);
        } catch (\Exception $err) {
            DB::rollBack();
            return response()->json([
                'status'  => false,
                'message' => 'Cập nhật thất bại',
                'error'   => $err->getMessage(),
            ], 400);
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
    public function delete($id)
    {
        $product = Product::find($id);
        if (! $product) {
            return response()->json([
                'status'  => false,
                'message' => 'Không có sản phẩm này trong dữ liệu',
            ], 404);
        }

        try {
            // Xóa các bản ghi liên quan trong ToppingProduct
            ToppingProduct::where('product_id', $product->id)->delete();

            // Thử xóa sản phẩm
            $product->delete();

            return response()->json([
                'status'  => true,
                'message' => 'Xóa sản phẩm thành công',
            ], 200);
        } catch (\Exception $e) {
            // Nếu xóa không thành công, đánh dấu sản phẩm là không hoạt động
            try {
                $product->update(['active' => false]);
                return response()->json([
                    'status'  => true,
                    'message' => 'Xóa sản phẩm không thành công, đã đánh dấu sản phẩm là không hoạt động',
                ], 200);
            } catch (\Exception $updateException) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Xóa sản phẩm thất bại và không thể đánh dấu sản phẩm là không hoạt động',
                    'error'   => $updateException->getMessage(),
                ], 400);
            }
        }
    }
}
