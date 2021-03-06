<?php

namespace App\Http\Controllers;

use App\Cart;
use App\Order;
use App\Product;
use App\Wishsession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Routing\Controller;
use View;
use Validator;


class ShopController extends Controller
{
    // Cart functions
    public function getAddToCart(Request $request, $id) {
        if (!auth()->check()) {
            return redirect()->route('register');
        }
        if (auth()->user()->role == 'vendor'){
            return redirect()->route('show.index')->with('error',"You can't buy products with a vendor account");
        }

        $product = Product::find($id);
        $cart = new Cart(null);
        $cart->add($product, $product->id);
        $request->session()->put('cart', $cart);
        return redirect()->route('show.checkout');
    }

    public function getAddByOne(Request $request, $id) {
        $product = Product::find($id);
        $oldCart = Session::has('cart') ? Session::get('cart') : null;
        $cart = new Cart($oldCart);
        $cart->add($product, $product->id);
        $request->session()->put('cart', $cart);

        return redirect()->route('product.shoppingCart');
    }
    public function getReduceByOne($id) {

        $oldCart = Session::has('cart') ? Session::get('cart') : null;
        $cart = new Cart($oldCart);
        $cart->reduceByOne($id);

        if (count($cart->items) > 0){
            Session::put('cart', $cart);
        } else {
            Session::forget('cart');
        }

        return redirect()->route('product.shoppingCart');
    }

    public function getRemoveItem($id) {
        $oldCart = Session::has('cart') ? Session::get('cart') : null;
        $cart = new Cart($oldCart);
        $cart->removeItem($id);

        if (count($cart->items) > 0){
            Session::put('cart', $cart);
        } else {
            Session::forget('cart');
        }

        return redirect()->route('product.shoppingCart');
    }
    public function getCart() {
        $randomproducts = Product::all();

        if (!Session::has('cart')) {
            return view('payment.cart',['products' => null]);
        }
        $oldCart = Session::get('cart');
        $cart = new Cart($oldCart);

        return view('payment.cart', [
            'randoms' => $randomproducts,
            'products' => $cart->items,
            'totalPrice' => $cart->totalPrice]);
    }

    // Checkout
    public function postCheckout(Request $request){
        if (!Session::has('cart')) {
            return redirect()->route('product.shoppingCart');
        }
        $oldCart = Session::get('cart');
        $cart = new Cart($oldCart);
            $order = new Order();
            $order->email = $request->input('email');
            $order->address_ext = $request->input('address_ext');
            $order->postcode = $request->input('postcode');
            $order->country = $request->input('country');
            $order->city = $request->input('city');
            $order->phone = $request->input('phone');
            $order->company_name = $request->input('company_name');
            $order->notes = $request->input('notes');
            $order->cart = serialize($cart);
            $order->address = $request->input('address');
            if(auth()->check()) {
                auth()->user()->orders()->save($order);
            }
        Session::forget('cart');
        return redirect()->route('show.index')->with('success','success');
    }
     
       public function getCoupon(Request $request) {

        $coupon = Coupon::where('code', $request->coupon_code)->first();
        if ($coupon!= null) {
        $oldCart = Session::has('cart') ? Session::get('cart') : null;
        $cart = new Cart($oldCart);
        $cart->coupon($coupon,$oldCart);
        $request->session()->put('cart', $cart);
        return redirect()->route('product.shoppingCart' )->with('Success!','The coupon was used');
        }
        return redirect()->route('product.shoppingCart')->withErrors('wrong coupon !');
    }
    
    public function getEarnings(Request $request){
        $content= $request->all();
        $orders = DB::table('orders')->where('vendor',auth()->user()->name)->whereYear('created_at','=',$content['year'])->get();
        $orders->transform(function ($order, $key) {
            $order->cart = unserialize($order->cart);
            return $order;
        });
        $counts = 0;
        $earnings = 0;
        foreach ($orders as $order) {
            foreach ($order->cart->items as $item){
                $earnings = $earnings + $item['price'];
                $counts = $counts +1 ;
            }
        }
        return view('author.earnings',['earnings' => $earnings,'counts' => $counts]);
    }

    public function categoryFilter(Request $request) {
        $content= $request->getContent();
        $data = json_decode($content, true);
        $category = $data["type"];
        $products = DB::table('products')
            ->where('category',$category)->paginate(6);

        return view('filters.productsCategory',['products' => $products]);
    }

    public function showProductsByPrice (Request $request) {
        $content= $request->all();
        $products = DB::table('products')
            ->join('users','products.uploaded_by','=','users.id')
            ->select('products.*','users.avatar as avatar','users.name as username')
            ->where('price','>=',$content['min'])
            ->where('price','<=',$content['max'])
            ->paginate(18)
            ;
        return view('filters.productsPrice',['products' => $products]);
    }



}
