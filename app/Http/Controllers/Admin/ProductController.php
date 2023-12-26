<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Topping;
use App\Models\ToppingProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class ProductController extends Controller
{
    //lay danh sach san pham
    public function index()
    {
        $productList = Product::where('active', 1)
            ->orderby('id')
            ->get();
        return response([
            'products' => $productList,
        ]);
    }
    //them san pham
    public function create(Request $request)
    {
        if (Product::where('name', $request->name)->first()) {
            return response([
                'message' => 'Đã có sản phẩm này',
            ]);
        }
        $product = Product::create([ 
            'name' => $request->name,
            'category_id' => $request->category_id,
            'description' => $request->description,
            'price' => $request->price,
            'price_sale' => $request->price_sale,
            'active' => 1,
            'image_url' => $request->image_url,
        ]);
        //tạo topping cho sản phẩm nếu là đồ ăn thì mặc định là topping 6
        if ($request->category_id == 13||$request->category_id == 14||$request->category_id == 16) {
            $topping = ToppingProduct::create([
                'product_id' => $product->id,
                'topping_id' => [6]
            ]);
        } else {
            $topping = ToppingProduct::create([
                'product_id' => $product->id,
                'topping_id' => [1,2,3,4,5]
            ]);
        }

        return response([
            'message' => "Thêm sản phẩm thành công",
            'product' => $product,
            'topping' => $topping
        ], 200);
    }
    //lay danh sach danh muc con theo danh muc cha
    public function getChild($parent)
    {
        $childList = Category::select('id')  //chọn cột id
            ->where('parent_id', $parent->id) //có điều kiện là cột parent_id = id của parent
            ->orderby('id')
            ->get();
        return $childList;
    }
    //lay danh sach san pham theo danh muc
    //ý tưởng : lấy danh sách danh mục con của danh mục request đến, 
    //sau đó lấy danh sách sản phẩm của tất cả danh mục con đó
    public function indexByCategoryId(Request $request)
    {
        $categoryList = collect(); 
        $childList = collect();
        $productList = collect();

        $categoryList = Category::select('id')
            ->where('id', $request->category_id)
            ->get();
        for ($i = 0; $i < $categoryList->count(); $i++) {
            $childList = $this->getChild($categoryList[$i]);
            foreach ($childList as $child) {
                if (!($categoryList->contains($child)))
                    $categoryList->push($child); //push vào cuối mảng categoryList
            }
        }
        
        foreach ($categoryList as $category) {
            $product_list = Product::where('category_id', $category->id)
                ->where('active', 1)
                ->orderby('id')
                ->get();
            foreach ($product_list as $product) {
                $productList->push($product);
            }
        }
        return response([
            'products' => $productList,
        ]);
    }
    //lấy danh sách topping của sản phẩm và info của topping
    public function getToppingInfo($product_id)
    {
        $toppingList = ToppingProduct::select('topping_id')
            ->where('product_id', $product_id)
            ->first();
        $toppingInfoList = collect();
        foreach ($toppingList->topping_id as $topping_id) {
            $toppingInfo = Topping::select('id', 'name', 'price')
                ->where('id', $topping_id)
                ->first();
            $toppingInfoList->push($toppingInfo); 
        }
        //vd toppingInfoList có dạng json như sau [{"id":1,"name":"Trân châu đen","price":10000},{"id":2,"name":"Trân châu trắng","price":10000}]
        return $toppingInfoList;
    }
    //lay thong tin chi tiet san pham
    public function getProductInfo(Request $request)
    {
        $productInfo = Product::select(
            'id',
            'name',
            'category_id',
            'description',
            'price',
            'price_sale',
            'image_url'
        )
            ->where('id', $request->product_id) 
            ->where('active', 1)
            ->first(); // first() là hàm lấy ra bản ghi đầu tiên thỏa mãn điều kiện

        $sameProductList = Product::select(
            'id',
            'name',
            'category_id',
            'description',
            'price',
            'price_sale',
            'image_url'
        )
            ->where('category_id', $productInfo->category_id)
            ->where('active', 1)
            ->where('id', '<>', $productInfo->id) // <> là phép so sánh khác 
            ->get();
        //gọi hàm getToppingInfo để lấy danh sách topping của sản phẩm
        $toppings= $this->getToppingInfo($request->product_id);
        
        return response([
            'message' => 'Lấy thông tin sản phẩm thành công',
            'product' => $productInfo,
            'toppings' => $toppings,
            'same_products' => $sameProductList

        ], 200);
    }
    //cap nhat san pham
    public function update(Request $request)
    {
        $product = Product::where('id', $request->input('id'))->first();
        $isValidPrice = $this->isValidPrice($request);
        if ($isValidPrice === false) {
            return false;
        }

        try {
            $product->fill($request->input());
            $product->save();
            return response([
                'message' => 'Cập nhật thành công',
                'product' => $product,
            ], 200);
        } catch (\Exception $err) {
            return response([
                'message' => 'Cập nhật thất bại',
                'error' => $err->getMessage(),
            ], 500);
        }
    }
    //check xem gia giam co nho hon gia goc hay khong
    protected function isValidPrice($request)
    {
        if (
            $request->input('price') != 0 && $request->input('price_sale') != 0
            && $request->input('price_sale') > $request->input('price')
        ) {
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
        $product = Product::where('id', $request->input('id'))->first();
        if ($product) {
            $product->delete();
            return response()->json([
                'message' => 'Xóa thành công sản phẩm'
            ], 200);
        } else return response([
            'message' => 'Không có sản phẩm này trong dữ liệu'
        ], 500);
    }

}
