<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Botble\Ecommerce\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Botble\Ecommerce\Models\Order;
use Botble\Ecommerce\Models\OrderHistory;
use Botble\Ecommerce\Enums\ShippingMethodEnum;
use Botble\Ecommerce\Enums\OrderStatusEnum;
use Botble\Ecommerce\Enums\OrderHistoryActionEnum;
use Botble\Ecommerce\Services\CreatePaymentForOrderService;
use Botble\Ecommerce\Models\OrderAddress;
use Botble\Ecommerce\Models\Address;
use Botble\Ecommerce\Models\Product;
use Botble\Ecommerce\Models\OrderProduct;
use Botble\Ecommerce\Models\Invoice;
use Botble\Ecommerce\Models\InvoiceItem;
use Botble\Ecommerce\Facades\Discount;
use Botble\Ecommerce\Models\DiscountProduct;
use Botble\Ecommerce\Models\Discount as DiscountModel;
use Botble\Ecommerce\Models\MobileVerification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function storeOrder(Request $request, CreatePaymentForOrderService $createPaymentForOrderService) {
        // echo "<pre>";print_r($request->all());die;
        $validator = Validator::make($request->all(), [
            'products'      => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors());
        }

        $barcodes = [];

        foreach ($request->input('products') as $product) {
            $exisProduct = Product::where('id', $product['product_id'])->first();
            if($exisProduct->quantity < $product['quantity']) {
                return response()->json([
                    'qtyMessage'          => $product['product_name'].' is Out Of Stock.'
                ]);
            }

            // $url = "https://c21341-ifservice.cloudiax.com/api/ECommerce/StockStatus?itemCode=123456";
            // $url = "https://c21341-ifservice.cloudiax.com/api/ECommerce/StockStatus?itemCode=".$exisProduct->barcode;

            // $ch = curl_init();

            // curl_setopt($ch, CURLOPT_URL, $url);
            // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            // // Set the request method to POST
            // curl_setopt($ch, CURLOPT_POST, true);
            // curl_setopt($ch, CURLOPT_HTTPHEADER, [
            //     "Accept: application/json",
            //     "Company: KSA", 
            //     "Authorization: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJVc2VySUQiOiJhZG1pbiIsIkVtcElEIjoiMTAyNDgiLCJDb21wYW55IjoiIiwiV2hzQ29kZSI6IidDdXN0b20nLCdETV8wMScsJ0ZHXzAxJywnRk9DJywnSUNfVUFFJywnUE1fMDEnLCdTUF8wMDEnLCdTUF8wMDInLCdTUF8wMDMnLCdTUF8wMDQnLCdTUF8wMDUnLCdTUF8wMDYnLCdTUF8wMDcnLCdTUF8wMDgnLCdTUF8wMDknLCdTUF8wMTAnLCdTUF8wMTEnLCdTUF8wMTInLCdTUF8wMTMnLCdTUF8wMTQnLCcwMScsJ0NOMDAxXzAxJywnQ3VzdG9tJywnRE1fMDEnLCdGR18wMScsJ0ZHXzAyJywnRkdfMDMnLCdGT0MnLCdJQ18wMScsJ0lDX1VBRScsJ1BNXzAxJywnU1BfMDAxJywnU1BfMDAxXzEnLCdTUF8wMDInLCdTUF8wMDMnLCdTUF8wMDNfMScsJ1NQXzAwNCcsJ1NQXzAwNScsJ1NQXzAwNicsJ1NQXzAwNycsJ1NQXzAwOCcsJ1NQXzAwOScsJ1NQXzAxMCcsJ1NQXzAxMScsJ1NQXzAxMicsJ1NQXzAxMycsJ1NQXzAxNCcsJ1NQXzAxNScsJ1NQXzAxNicsJ1NQXzAxNycsJ1NQXzAxOScsJ1NQXzAyMCcsJ1NQXzAyMF8xJywnU1BfMDIxJywnU1BfMDIyJywnU1BfMDIzJywnU1BfMDI0JywnU1BfMDI1JywnU1BfMDI2JywnU1BfMDI3JywnU1BfMDI4JywnU1BfMDI4XzEnLCdTUF8wMjhfMicsJ1NQXzAyOScsJ1NQXzAzMCcsJ1NQXzAzMScsJ1ZOXzAwMScsJ0N1c3RvbScsJ0RNXzAxJywnRkdfMDEnLCdGT0MnLCdJQ19VQUUnLCdQTV8wMScsJ1NQXzAwMScsJ1NQXzAwMicsJ1NQXzAwMycsJ1NQXzAwNCcsJ1NQXzAwNScsJ1NQXzAwNicsJ1NQXzAwNycsJ1NQXzAwOCcsJzAxJywnQ3VzdG9tJywnRE1fMDEnLCdGR18wMScsJ0ZPQycsJ0lDXzAxJywnSUNfTW92JywnSUNfT0FQJywnSUNfVUFFJywnUE1fMDEnLCdTUF8wMDEnLCdTUF8wMDInLCdTUF8wMDMnLCdTUF8wMDQnLCdTUF8wMDUnLCdTUF8wMDYnLCdTUF8wMDcnLCdTUF8wMDgnLCdTUF8wMDknLCdTUF8wMTAnLCdTUF8wMTEnLCdTUF8wMTInLCdTUF8wMTMnLCdTUF8wMTQnLCdTUF8wMTUnLCdTUF8wMTYnLCdTUF8wMTcnLCdTUF8wMTgnLCdTUF8wMTknLCdTUF8wMjAnLCdTUF8wMjEnLCdTUF8wMjInLCdTUF8wMjMnLCdTUF8wMjQnLCdTUF8wMjUnLCdTUF8wMjYnLCdTUF8wMjcnLCdTUF8wMjgnLCdTUF8wMjknLCdTUF8wMzAnLCdTUF8wMzEnLCdTUF8wMzInLCdTUF8wMzMnLCdTUF8wMzQnLCdTUF8wMzUnLCdTUF8wMzYnLCdTUF8wMzcnLCdTUF8wMzgnLCdTUF8wMzknLCdTUF8wNDAnLCdTUF8wNDEnLCdTUF8wNDInLCdTUF8wNDMnLCdTUF8wNDQnLCdTUF8wNDUnLCdTUF8wNDYnLCdTUF8wNDcnLCdTUF8wNDgnLCdTUF8wNDknLCdTUF8wNTAnLCdTUF8wNTEnLCdTUF8wNTInLCdTUF8wNTMnLCdTUF8wNTQnLCdTUF8wNTUnLCdTUF8wNTYnLCdTUF8wNTcnLCdTUF8wNTgnLCdTUF8wNTknLCdTUF8wNjAnLCdTUF8wNjEnLCdUWVNfMDEnLCcwMScsJ0NOMDAxXzAxJywnQ04wMDJfMDEnLCdDTjAwM18wMScsJ0NOMDA0XzAxJywnQ04wMDVfMDEnLCdDTjAwNl8wMScsJ0N1c3RvbScsJ0RNXzAxJywnRkdfMDEnLCdGR18wMicsJ0ZPQycsJ0lDX09NTicsJ0lDX1RZUycsJ0lDX1VBRScsJ1BNXzAxJywnU01QXzAxJywnU1BfMDAxJywnU1BfMDAyJywnU1BfMDAzJywnU1BfMDA0JywnU1BfMDA1JywnU1BfMDA2JywnU1BfMDA3JywnU1BfMDA4JywnU1BfMDA5JywnU1BfMDEwJywnU1BfMDExJywnU1BfMDEyJywnU1BfMDEzJywnU1BfMDE1JywnU1BfMDE2JywnU1BfMDE3JywnU1BfMDE4JywnU1BfMDE5JywnU1BfMDIwJywnU1BfMDIxJywnU1BfMDIyJywnMDEnLCdBbWF6b24nLCdBVF8wMScsJ0JLXzAxJywnQlJBTkQnLCdDMDIwMjM1NicsJ0NOMDAxXzAxJywnQ04wMDJfMDEnLCdDTjAwM18wMScsJ0NOMDA0XzAxJywnQ04wMDVfMDEnLCdDTjAwNl8wMScsJ0NOMDA3XzAxJywnQ04wMDhfMDEnLCdDV19TTTAwMCcsJ0NXX1NNMDAxJywnQ1dfU00wMDInLCdDV19TTTAwMycsJ0NXX1NNMDA0JywnQ1dfU00wMDUnLCdDV19TTTAwNicsJ0NXX1NNMDA3JywnQ1dfU00wMDgnLCdDV19TTTAwOScsJ0NXX1NNMDEwJywnRE1fMDEnLCdETV8wMicsJ0RNXzAzJywnRE1fMDQnLCdETV8wNScsJ0RNXzA2JywnRUNfMDEnLCdGR18wMScsJ0ZPQycsJ0dGXzAxJywnSUNfQU1QJywnSUNfQkhSJywnSUNfS1NBJywnSUNfTW92JywnSUNfT01OJywnSUNfUUFUJywnSVQnLCdJVDAyJywnUEtfMDEnLCdQTV8wMScsJ1BNXzAyJywnUUNfMDEnLCdSJkQnLCdTS18wMScsJ1NMXzAxJywnU01QXzAxJywnU1BfMDAxJywnU1BfMDAyJywnU1BfMDAzJywnU1BfMDA0JywnU1BfMDA1JywnU1BfMDA2JywnU1BfMDA3JywnU1BfMDA4JywnU1BfMDA5JywnU1BfMDEwJywnU1BfMDExJywnU1BfMDEyJywnU1BfMDEzJywnU1BfMDE0JywnU1BfMDE1JywnU1BfMDE2JywnU1BfMDE3JywnU1BfMDE4JywnU1BfMDE5JywnU1BfMDIwJywnU1BfMDIxJywnU1BfMDIyJywnU1BfMDIzJywnU1BfMDI0JywnU1BfMDI1JywnU1BfMDI2JywnU1BfMDI3JywnU1BfMDI4JywnU1BfMDI5JywnU1BfMDMwJywnU1BfMDMxJywnU1BfMDMyJywnU1BfMDMyXzEnLCdTUF8wMzMnLCdTUF8wMzQnLCdTUF8wMzUnLCdTUF8wMzYnLCdTUF8wMzcnLCdTUF8wMzgnLCdTUF8wMzknLCdTUF8wNDAnLCdTUF8wNDEnLCdTUF8wNDInLCdTUF8wNDMnLCdTUF8wNDQnLCdTUF8wNDUnLCdTUF8wNDYnLCdTUF8wNDcnLCdTUF8wNDgnLCdTUF8wNDknLCdTUF8wNTAnLCdTUF8wNTEnLCdTUF8wNTInLCdTUF8wNTMnLCdTUF8wNTQnLCdTUF8wNTUnLCdTUF8wNTYnLCdTUF8wNTcnLCdTUF8wNTgnLCdTUF8wNTknLCdTUF8wNjAnLCdTUF8wNjEnLCdTUF8wNjInLCdTUF8wNjMnLCdTUF8wNjQnLCdTUF8wNjUnLCdTUF8wNjYnLCdTUF8wNjcnLCdTUF8wNjgnLCdTUF8wNjknLCdTUF8wNzAnLCdTUF8wNzEnLCdTUF8wNzInLCdTUF8wNzMnLCdTUF8wNzQnLCdTUF8wNzUnLCdTUF8wNzYnLCdTUF8wNzcnLCdTUF8wNzknLCdTUF8wODAnLCdTUF8wODEnLCdTUF8wODInLCdTUF8wODMnLCdTUF8wODQnLCdTUF8wODUnLCdTUF8wODYnLCdTUF8wODgnLCdTUF8wODknLCdTUF8wOTAnLCdTUF8wOTEnLCdTUF8wOTInLCdXSF8wMScsJ1dIXzAyJywnV0hfMDMnLCdXSF8wNCcsJ1dIXzA1JywnV0hfMDYnLCdXSF9EUk0nLCdXSF9WZW5kJyIsIlN0b3JlSUQiOiInJywnSE8nLCdPRkInLCdITycsJ0hPJywnUCZFJywnU01BJywnQktXJywnQkNDJywnQlNUJywnSERMJywnREFNJywnSklEJywnQlVLJywnUkFNJywnQ0NCJywnSE1UJywnTUhSJywnQU1CJywnQlNTJywnJywnSE8nLCdITycsJycsJ0pETycsJ01ETycsJ0hPJywnSE8nLCcnLCdITycsJ1AmRScsJ0tBUycsJ0tBU1MnLCdKUUInLCdEQVQnLCdEQVRTJywnTk9SJywnQVNNJywnVEJBJywnQVpNJywnQktSJywnU0tEJywnVEdNJywnT0JNJywnSlVNJywnUUJBJywnS09TJywnU1NKJywnTU9OJywnU0FGJywnUUJGJywnS01TJywnS01TUycsJ01BRycsJ1lSTScsJ01VRycsJ01SSicsJ1NRSicsJ01ESCcsJ01ERycsJ01DVCcsJ01DVFMnLCdWTUNUJywnUkhCJywnT0JIJywnQkFTJywnS1NWJywnJywnSE8nLCcnLCdITycsJ0hPJywnUCZFJywnS1NNJywnSlJLJywnS01BJywnS09EJywnR0FUJywnQkxWJywnTUdUJywnTUdDJywnJywnSE8nLCdITycsJ09GTycsJ0hPJywnSE8nLCdITycsJ0hPJywnSE8nLCdQJkUnLCdTTVQnLCdTS0snLCdTRUInLCdCUksnLCdTTEwnLCdTVVInLCdOSVonLCdTV1EnLCdTT00nLCdTQU0nLCdCUk0nLCdFQlInLCdTQlgnLCdCRFknLCdLQlInLCdBTVInLCdTTk0nLCdBVk0nLCdMV00nLCdKTE4nLCdBS00nLCdBS0InLCdNU04nLCdTTlcnLCdSU1QnLCdCUkEnLCdZQU4nLCdTTE4nLCdTTFUnLCdTQUQnLCdNT00nLCdRVVInLCdCSUQnLCdLQU0nLCdLVUQnLCdTTUwnLCdTTlMnLCdDQ00nLCdNT08nLCdDQ1MnLCdKTFMnLCdPQVMnLCdTU1MnLCdETksnLCdCSEwnLCdNQVQnLCdBTlMnLCdBU0snLCdLQlMnLCdTTVMnLCdGTEonLCdEUU0nLCdFQlMnLCdGQU4nLCdCRFMnLCdBTVMnLCdCREQnLCdPT1MnLCdUTUQnLCdTV1MnLCdNVVMnLCdITycsJycsJycsJycsJycsJycsJycsJycsJycsJycsJ09GUScsJ0hPJywnJywnSE8nLCdITycsJ0hPJywnUCZFJywnSE8nLCdBWlknLCdTSEYnLCdOU1InLCdESEYnLCdNUVInLCdBTUonLCdET00nLCdBTUsnLCdMQkInLCdBV1MnLCdNUksnLCdBRlMnLCdXQVEnLCdRT1MnLCdRUk4nLCdJR1cnLCdFWkQnLCdWSUwnLCdOQVMnLCdTSE4nLCdXQVQnLCcnLCdITycsJ0hPJywnSE8nLCcnLCcnLCcnLCcnLCcnLCdITycsJ0hPJywnSE8nLCdITycsJ0hPJywnSE8nLCdITycsJ0hPJywnSE8nLCdITycsJ0hPJywnSE8nLCdITycsJ0hPJywnSE8nLCdITycsJ0hPJywnSE8nLCdITycsJ0hPJywnSE8nLCdITycsJ0FFQycsJ0hPJywnSE8nLCdITycsJ0hPJywnSE8nLCdITycsJ0hPJywnSE8nLCdITycsJ0hPJywnSE8nLCdITycsJ1AmRScsJ0hPJywnSE8nLCcnLCcnLCdITycsJ0hPJywnREZNJywnQlNNJywnQk5ZJywnQ1RNJywnRE1LJywnS0hMJywnQUpDJywnTVpNJywnQUZNJywnQUFNJywnQldNJywnQlNHJywnQlNYJywnQUdNJywnQUJNJywnQUJDJywnTUZDJywnRFJDJywnREFGJywnRkpNJywnQUtIJywnS0hLJywnTU5NJywnUkFLJywnU0hNJywnTVJEJywnU1JDJywnU0JTJywnU01NJywnTUFNJywnVUFRJywnSlJOJywnSlJNJywnU1FNJywnUk1aJywnQVNTJywnQkFSJywnS0hNJywnTU9RJywnRExNJywnQVlSJywnVUNKJywnQUdaJywnUkhNJywnVUNBJywnVUNCJywnRkNDJywnR0JWJywnRFJNJywnU0NIJywnSFRUJywnTVNGJywnSk1NJywnWkNDJywnR1lNJywnRkNNJywnTVNNJywnREhEJywnUklGJywnS0JNJywnSE1EJywnUldEJywnS1dTJywnQUFLJywnQlJTJywnRE9TJywnU0xNJywnREVSJywnU0NEJywnS0xGJywnU0JBJywnTURNJywnSlJGJywnTExaJywnRkpTJywnUkZNJywnRE1CJywnTVJCJywnREhNJywnSURXJywnSkNQJywnRFNTJywnTVNLJywnSE1BJywnRElCJywnRFNRJywnVU1CJywnQUtEJywnSFRTJywnWUFTJywnR0JJJywnSE8nLCdITycsJ0hPJywnSE8nLCdITycsJ0hPJywnRFdTJywnJyIsIlRlcm1pbmFsSUQiOiIiLCJzYWxlc1BlcnNvbklkIjoiIiwiem9uZUlkIjoiJyonIiwiZXhwIjoxNzczNTU5MjYyfQ.JZfGnaPSXmCanQfq3OWPRkYqqzy_rM9LLyLLiTLMFOo"
            // ]);

            // $response = curl_exec($ch);

            // if (curl_errno($ch)) {
            //     echo 'Error: ' . curl_error($ch);
            // }

            // curl_close($ch);
            // $resp = json_decode($response);
            // // print_r($resp->data);die;
            //  if(isset($resp->data) && $resp->data < $product['quantity']) {
            //     return response()->json([
            //         'qtyMessage'          => $product['product_name'].' is Out Of Stock.'
            //     ]);
            // }

            if(isset($product['discount']) && !is_null($product['discount'])) {
                $exisProduct->discount = DiscountProduct::select('value', 'start_date', 'end_date')->where('product_id', $product['product_id'])->whereNull('code')->whereDate('start_date', '<=', now())->whereDate('end_date', '>=', now())->join('ec_discounts', 'ec_discounts.id', '=', 'ec_discount_products.discount_id', 'left')->first();
                if(is_null($exisProduct->discount)) {
                    return response()->json([
                        'discountMessage'          => 'One or more Products were removed. Please add them again to continue.'
                    ]);
                }
            }
        }

        $coupon_code = $request->input('couponCode');
        if(isset($coupon_code) && !empty($request->input('couponCode'))) {
            $coupon = DiscountModel::where('code', $request->input('couponCode'))->where('start_date', '<=', now())->where('end_date', '>=', now())->first();
            if(!$coupon) {
                return response()->json(['couponMessage' => 'Invalid Coupon Code']);
            }
            $order_address = OrderAddress::where('phone', $request->input('billingAddress.mobile'))->first();
            // echo $order_address;
            if($order_address) {
                $order = Order::where('id', $order_address->order_id)->first();
                $customer_discount = DB::table('ec_customer_used_coupons')->where('customer_id', $order->user_id)->where('discount_id', $coupon->id)->first();
                if($customer_discount) {
                    return response()->json(['couponMessage' => 'You Have Already Used this Coupon Code']);
                }
            }

            array_push($barcodes, $exisProduct->barcode);
        }
        // die('000');
        $customer_id = $request->input('customer_id');

        if (!$customer_id) {
            $validator = Validator::make($request->all(), [
                'billingAddress.first_name'      => 'required|string|max:255',
                'billingAddress.last_name'      => 'required|string|max:255',
                'billingAddress.email'     => 'required|string|max:255',
                'billingAddress.mobile'     => 'required|numeric',
                'billingAddress.area'     => 'required|string',
                'billingAddress.building'     => 'required|string',
                'billingAddress.province'     => 'required|string',
                ]);
    
            if ($validator->fails()) {
                return response()->json($validator->errors());
            }
            
            $exisCustomer = Customer::where('email', $request->billingAddress['email'])->orWhere('phone', $request->billingAddress['mobile'])->first();

            // echo "<pre>";print_r($exisCustomer);die;
    
            if (!$exisCustomer) {
                $customer = Customer::create([
                    'name'      => $request->input('billingAddress.first_name').' '.$request->input('billingAddress.last_name'),
                    'email'     => $request->input('billingAddress.email'),
                    'phone'     => $request->input('billingAddress.mobile'),
                    'password'  => $request->input('password') ? Hash::make($request->input('password')) : Hash::make('123456')
                ]);

                Address::create([
                    'name'      => $request->input('billingAddress.first_name').' '.$request->input('billingAddress.last_name'),
                    'email'     => $request->input('billingAddress.email'),
                    'phone'     => $request->input('billingAddress.mobile'),
                    'state' => $request->input('billingAddress.province'),
                    'city' => $request->input('billingAddress.province'),
                    'country' => $request->input('billingAddress.country'),
                    'address' => $request->input('billingAddress.area').' '.$request->input('billingAddress.building'),
                    'customer_id' => $customer->id,
                ]);

                // $otp = rand(1111, 9999);

                // $ch = curl_init();

                // $passw = "11F2";
                // $pass = "$";
                // $p = "E89_6C3";
                // $password = $passw.$pass.$p;

                // curl_setopt($ch, CURLOPT_URL, "https://myinboxmedia.in/api/mim/SendSMS?userid=MIM2300278&pwd=".$password."&mobile=966".$request->input('billingAddress.mobile')."&sender=Ahmedper&msg=".$otp."".urlencode(' is your OTP for Registration')."&msgtype=16");
                // curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                // curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");

                // $result = curl_exec($ch);
                // if (curl_errno($ch)) {
                //     echo 'Error:' . curl_error($ch);die;
                // }
                // curl_close ($ch);

                // $customer->otp = $otp;
                // $customer->save();

                $customer_id = $customer->id;
            } else {
                $exisAddress = Address::where('customer_id', $exisCustomer->id)->first();
                if(!$exisAddress) {
                    Address::create([
                        'name'      => $request->input('billingAddress.first_name').' '.$request->input('billingAddress.last_name'),
                        'email'     => $request->input('billingAddress.email'),
                        'phone'     => $request->input('billingAddress.mobile'),
                        'state' => $request->input('billingAddress.province'),
                        'city' => $request->input('billingAddress.province'),
                        'country' => $request->input('billingAddress.country'),
                        'address' => $request->input('billingAddress.area').' '.$request->input('billingAddress.building'),
                        'customer_id' => $exisCustomer->id,
                    ]);
                }
                $customer_id = $exisCustomer->id;
            }
        }

        // echo "<pre>";print_r(([
        //     'user_id' => $customer_id,
        //     'shipping_method' => $request->input('shipping_method') ? : ShippingMethodEnum::DEFAULT,
        //     'shipping_option' => $request->input('shipping_option'),
        //     'shipping_amount' => $request->input('shippingPrice') ? : 0,
        //     'tax_amount' => (($request->input('finalPrice') - 3) / 100) * 5 ? : 0,
        //     'sub_total' => $request->input('totalPrice') ? : 0,
        //     'amount' => $request->input('finalPrice') ? : 0,
        //     'coupon_code' => $request->input('coupon_code'),
        //     'discount_amount' => $request->input('discount_amount') ? : 0,
        //     'promotion_amount' => $request->input('promotion_amount') ? : 0,
        //     'discount_description' => $request->input('discount_description'),
        //     'description' => $request->input('note'),
        //     'is_confirmed' => 1,
        //     'is_finished' => 1,
        //     'status' => OrderStatusEnum::PROCESSING,
        //     'lang' => $request->input('locale'),
        // ]));die();
        // echo "<pre>";print_r([
        //     'user_id' => $customer_id,
        //     'shipping_method' => $request->input('shipping_method') ? : ShippingMethodEnum::DEFAULT,
        //     'shipping_option' => $request->input('shipping_option'),
        //     'shipping_amount' => $request->input('shippingPrice') ? : 0,
        //     'tax_amount' => (($request->input('finalPrice') - 3) / 100) * 5 ? : 0,
        //     'sub_total' => $request->input('totalPrice') ? : 0,
        //     'amount' => $request->input('finalPrice') ? : 0,
        //     'coupon_code' => $request->input('coupon_code'),
        //     'discount_amount' => $request->input('discount_amount') ? : 0,
        //     'promotion_amount' => $request->input('promotion_amount') ? : 0,
        //     'discount_description' => $request->input('discount_description'),
        //     'description' => $request->input('note'),
        //     'is_confirmed' => 1,
        //     'is_finished' => 1,
        //     'status' => OrderStatusEnum::PROCESSING,
        //     'order_lang' => $request->input('locale'),
        // ]);die();
        // foreach ($request->input('products') as $product) {
        //     if(isset($product['is_gift']) && $product['is_gift'] == true) {
        //         $gift_product = Product::where('ec_products.id', $product['product_id'])->first();
        //         $without_vat_price = $gift_product->price / (1 + ($request->input('vatTax') / 100));
        //     }
        // }
        $order = Order::create([
            'user_id' => $customer_id,
            'shipping_method' => $request->input('shipping_method') ? : ShippingMethodEnum::DEFAULT,
            'shipping_option' => $request->input('shipping_option'),
            'shipping_amount' => $request->input('shippingPrice') / (1 + ($request->input('vatTax') / 100)),
            'shipping_amount_vat' => $request->input('shippingPrice') / (1 + ($request->input('vatTax') / 100)) * ($request->input('vatTax') / 100),
            'service_amount' => $request->input('servicePrice') / (1 + ($request->input('vatTax') / 100)),
            'service_amount_vat' => $request->input('servicePrice') / (1 + ($request->input('vatTax') / 100)) * ($request->input('vatTax') / 100),
            'vat' => $request->input('vatTax'),
            'tax_amount' => ($request->input('totalPrice') / (1 + ($request->input('vatTax') / 100)) * ($request->input('vatTax') / 100)) + ($request->input('shippingPrice') / (1 + ($request->input('vatTax') / 100)) * ($request->input('vatTax') / 100)) + ($request->input('servicePrice') / (1 + ($request->input('vatTax') / 100)) * ($request->input('vatTax') / 100)),
            'sub_total' => $request->input('totalPrice') ? : 0,
            'amount' => $request->input('finalPrice') ? : 0,
            'coupon_code' => $request->input('couponCode'),
            'discount_amount' => $request->input('discount_amount') ? : 0,
            'promotion_amount' => $request->input('promotion_amount') ? : 0,
            'discount_description' => $request->input('discount_description'),
            'description' => $request->input('note'),
            'is_confirmed' => 1,
            'is_finished' => 1,
            'status' => OrderStatusEnum::PROCESSING,
            'lang' => $request->input('locale'),
            'sub_total_tax' => isset($without_vat_price) ? ($request->input('totalPrice') / (1 + ($request->input('vatTax') / 100)) * ($request->input('vatTax') / 100)) + ($request->input('shippingPrice') / (1 + ($request->input('vatTax') / 100)) * ($request->input('vatTax') / 100)) + ($request->input('servicePrice') / (1 + ($request->input('vatTax') / 100)) * ($request->input('vatTax') / 100)) + (($without_vat_price / 100) * $request->input('vatTax')) : 0.00,
            'discount_sub_total' => isset($without_vat_price) ? $without_vat_price : 0.00,
            'discount_sub_total_tax' => isset($without_vat_price) ? (($without_vat_price / 100) * $request->input('vatTax')) : 0.00,
            'discount' => isset($without_vat_price) ? $without_vat_price + (($without_vat_price / 100) * $request->input('vatTax')) : 0.00,
            'campaign' => isset($gift_product) ? 'free_gift_fathers_day_2025_campaign' : null,
        ]);

        // echo "<pre>";print_r($order);die();

        if($order) {

            if($request->input('customer_id')) {
                $loggedInCustomer = Customer::where('id', $request->input('customer_id'))->first();
                $loggedInCustomerAdd = Address::where('customer_id', $loggedInCustomer->id)->first();
                if(!$loggedInCustomerAdd) {
                    Address::create([
                        'name'      => $loggedInCustomer->name,
                        'email'     => $loggedInCustomer->email,
                        'phone'     => $loggedInCustomer->phone,
                        'state' => $request->input('billingAddress.province'),
                        'city' => $request->input('billingAddress.province'),
                        'country' => $request->input('billingAddress.country'),
                        'address' => $request->input('billingAddress.area').' '.$request->input('billingAddress.building'),
                        'customer_id' => $loggedInCustomer->id,
                    ]);
                    $loggedInCustomerAdd = Address::where('customer_id', $loggedInCustomer->id)->first();
                }
                OrderAddress::query()->create([
                    'name' => $request->input('shippingAddress.first_name') ? $request->input('shippingAddress.first_name').' '.$request->input('shippingAddress.last_name') : $loggedInCustomer->name,
                    'phone' => $request->input('shippingAddress.mobile') ? $request->input('shippingAddress.mobile') : $loggedInCustomer->phone,
                    'email' => $request->input('shippingAddress.email') ? $request->input('shippingAddress.email') : $loggedInCustomer->email,
                    'state' => $request->input('shippingAddress.province') ? $request->input('shippingAddress.province') : $loggedInCustomerAdd->state,
                    'city' => $request->input('shippingAddress.province') ? $request->input('shippingAddress.province') : $loggedInCustomerAdd->city,
                    'country' => $request->input('shippingAddress.country') ? $request->input('shippingAddress.country') : $loggedInCustomerAdd->country,
                    'address' => $request->input('shippingAddress.area') ? $request->input('shippingAddress.area').' '.$request->input('shippingAddress.building') : $loggedInCustomerAdd->address,
                    'order_id' => $order->id,
                    'type' => $request->input('shippingAddress.first_name') ? 'shipping_address' : 'billing_address',
                ]);

                if($request->input('payment_method') == 'payfort') {
                    $data = [
                        "customer_name"=> $request->input('shippingAddress.first_name') ? $request->input('shippingAddress.first_name').' '.$request->input('shippingAddress.last_name') : $loggedInCustomer->name,
                        "customer_email"=> $request->input('shippingAddress.email') ? $request->input('shippingAddress.email') : $loggedInCustomer->email,
                        "phone_number"=> $request->input('shippingAddress.mobile') ? $request->input('shippingAddress.mobile') : $loggedInCustomer->phone,
                        "billing_street"=> $request->input('shippingAddress.area') ? $request->input('shippingAddress.area').' '.$request->input('shippingAddress.building') : $loggedInCustomerAdd->address,
                        "billing_city"=> $request->input('shippingAddress.province') ? $request->input('shippingAddress.province') : $loggedInCustomerAdd->city,
                        "billing_stateProvince"=> $request->input('shippingAddress.province') ? $request->input('shippingAddress.province') : $loggedInCustomerAdd->state,
                        // "country"=> "KSA",
                        // "zip"=> "54321"
                    ];
                    // $resp = $this->payFortPayment($request, $data);
                    // return response()->json([
                    //     'redirect_url'     => $resp['redirect_url']
                    // ]);
                }

                if($request->input('payment_method') == 'tabby') {
                    $date = Carbon::parse($loggedInCustomer->created_at); // Assuming this is local time
                    $dateUtc = $date->utc(); // Convert to UTC
                    $data = [
                        "customer_name"=> $request->input('shippingAddress.first_name') ? $request->input('shippingAddress.first_name').' '.$request->input('shippingAddress.last_name') : $loggedInCustomer->name,
                        "customer_email"=> $request->input('shippingAddress.email') ? $request->input('shippingAddress.email') : $loggedInCustomer->email,
                        "phone_number"=> $request->input('shippingAddress.mobile') ? $request->input('shippingAddress.mobile') : $loggedInCustomer->phone,
                        "billing_address"=> $request->input('shippingAddress.area') ? $request->input('shippingAddress.area').' '.$request->input('shippingAddress.building') : $loggedInCustomerAdd->address,
                        "billing_city"=> $request->input('shippingAddress.province') ? $request->input('shippingAddress.province') : $loggedInCustomerAdd->city,
                        "billing_zip"=> "0000",
                        'customer_created_at'=> $dateUtc->toIso8601String(),
                        'customer_order_count'=> Order::where('customer_id', $request->input('customer_id'))->count(),
                        'customer_order_history'=> Order::where('customer_id', $request->input('customer_id'))->get(),
                        // "country"=> "KSA",
                        // "zip"=> "54321"
                    ];
                    // $resp = $this->tabbyPayment($request, $data);
                    // return response()->json([
                    //     'redirect_url'     => $resp['redirect_url']
                    // ]);
                }

            } else {
                OrderAddress::query()->create([
                    'name' => $request->input('shippingAddress.first_name') ? $request->input('shippingAddress.first_name').' '.$request->input('shippingAddress.last_name') : $request->input('billingAddress.first_name').' '.$request->input('billingAddress.last_name'),
                    'phone' => $request->input('shippingAddress.mobile') ? $request->input('shippingAddress.mobile') : $request->input('billingAddress.mobile'),
                    'email' => $request->input('shippingAddress.email') ? $request->input('shippingAddress.email') : $request->input('billingAddress.email'),
                    'state' => $request->input('shippingAddress.province') ? $request->input('shippingAddress.province') : $request->input('billingAddress.province'),
                    'city' => $request->input('shippingAddress.province') ? $request->input('shippingAddress.province') : $request->input('billingAddress.province'),
                    // 'zip_code' => $request->input('shippingAddress.zip_code'),
                    'country' => $request->input('shippingAddress.country') ? $request->input('shippingAddress.country') : $request->input('billingAddress.country'),
                    'address' => $request->input('shippingAddress.area') ? $request->input('shippingAddress.area').' '.$request->input('shippingAddress.building') : $request->input('billingAddress.area').' '.$request->input('billingAddress.building'),
                    'order_id' => $order->id,
                    'type' => $request->input('shippingAddress.first_name') ? 'shipping_address' : 'billing_address',
                ]);

                if($request->input('payment_method') == 'payfort') {
                    $data = [
                        "customer_name"=> $request->input('shippingAddress.first_name') ? $request->input('shippingAddress.first_name').' '.$request->input('shippingAddress.last_name') : $request->input('billingAddress.first_name').' '.$request->input('billingAddress.last_name'),
                        "customer_email"=> $request->input('shippingAddress.email') ? $request->input('shippingAddress.email') : $request->input('billingAddress.email'),
                        "phone_number"=> $request->input('shippingAddress.mobile') ? $request->input('shippingAddress.mobile') : $request->input('billingAddress.mobile'),
                        "billing_street"=> $request->input('shippingAddress.area') ? $request->input('shippingAddress.area').' '.$request->input('shippingAddress.building') : $request->input('billingAddress.area').' '.$request->input('billingAddress.building'),
                        "billing_city"=> $request->input('shippingAddress.province') ? $request->input('shippingAddress.province') : $request->input('billingAddress.province'),
                        "billing_stateProvince"=> $request->input('shippingAddress.province') ? $request->input('shippingAddress.province') : $request->input('billingAddress.province'),
                        // "country"=> "KSA",
                        // "zip"=> "54321"
                    ];
                    // $resp = $this->payFortPayment($request, $data);
                    // return response()->json([
                    //     'redirect_url'     => $resp['redirect_url']
                    // ]);
                }

                if($request->input('payment_method') == 'tabby') {
                    $data = [
                        "customer_name"=> $request->input('shippingAddress.first_name') ? $request->input('shippingAddress.first_name').' '.$request->input('shippingAddress.last_name') : $request->input('billingAddress.first_name').' '.$request->input('billingAddress.last_name'),
                        "customer_email"=> $request->input('shippingAddress.email') ? $request->input('shippingAddress.email') : $request->input('billingAddress.email'),
                        "phone_number"=> $request->input('shippingAddress.mobile') ? $request->input('shippingAddress.mobile') : $request->input('billingAddress.mobile'),
                        "billing_address"=> $request->input('shippingAddress.area') ? $request->input('shippingAddress.area').' '.$request->input('shippingAddress.building') : $request->input('billingAddress.area').' '.$request->input('billingAddress.building'),
                        "billing_city"=> $request->input('shippingAddress.province') ? $request->input('shippingAddress.province') : $request->input('billingAddress.province'),
                        "billing_zip"=> "0000",
                        'customer_created_at'=> Carbon::now()->utc()->toIso8601ZuluString(),
                        'customer_order_count'=> 1,
                        'customer_order_history'=> [],
                        // "country"=> "KSA",
                        // "zip"=> "54321"
                    ];
                    // $resp = $this->tabbyPayment($request, $data);
                    // return response()->json([
                    //     'redirect_url'     => $resp['redirect_url']
                    // ]);
                }
            }
            // die('00000');
            OrderHistory::query()->create([
                'action' => OrderHistoryActionEnum::CREATE_ORDER_FROM_WEBSITE,
                'description' => trans('plugins/ecommerce::order.create_order_from_website'),
                'order_id' => $order->getKey(),
            ]);

            OrderHistory::query()->create([
                'action' => OrderHistoryActionEnum::CREATE_ORDER,
                'description' => trans(
                    'plugins/ecommerce::order.new_order',
                    ['order_id' => $order->code]
                ),
                'order_id' => $order->getKey(),
            ]);

            OrderHistory::query()->create([
                'action' => OrderHistoryActionEnum::CONFIRM_ORDER,
                'description' => trans('plugins/ecommerce::order.order_was_verified_by'),
                'order_id' => $order->getKey(),
                'user_id' => $customer_id,
            ]);

            $prod = array();
    
            foreach ($request->input('products') as $product) {
                
                $quantity = $product['quantity'] ? $product['quantity'] : 1;

                $exisProduct = Product::where('ec_products.id', $product['product_id'])
                // ->join('ec_tax_products', 'ec_products.id', '=', 'ec_tax_products.product_id')->join('ec_taxes', 'ec_taxes.id', '=', 'ec_tax_products.tax_id')
                ->first();

                $exisProduct->discount = DiscountProduct::select('value', 'start_date', 'end_date')->where('product_id', $product['product_id'])->whereNull('code')->whereDate('start_date', '<=', now())->whereDate('end_date', '>=', now())->join('ec_discounts', 'ec_discounts.id', '=', 'ec_discount_products.discount_id', 'left')->first();

                $coupons = DiscountProduct::select('code', 'value', 'start_date', 'end_date')->where('product_id', $product['product_id'])->whereNotNull('code')->whereDate('start_date', '<=', now())->whereDate('end_date', '>=', now())->join('ec_discounts', 'ec_discounts.id', '=', 'ec_discount_products.discount_id', 'left')->get();

                // Store in a temporary property or a new array
                $couponData = [];
                foreach ($coupons as $coupon) {
                    $couponData[strtolower($coupon->code)] = [
                        'code' => strtolower($coupon->code),
                        'value' => $coupon->value,
                        'start_date' => $coupon->start_date,
                        'end_date' => $coupon->end_date,
                    ];
                }

                $exisProduct->coupon = $couponData;

                $exisProduct->qty = $quantity;

                // print_r($exisProduct);

                // if((isset($product['is_gift']) && $product['is_gift'] == true)) {
                //     $exisProduct->is_gift = 1;
                // }

                array_push($prod, $exisProduct);

                // $discount_price = '';
                // $sale_price = '';
                if(!is_null($exisProduct->discount)) {
                    $price = $exisProduct->price / (1 + ($request->input('vatTax') / 100));
                    $total_amount = $price * $quantity;
                    $discount_percent = $exisProduct->discount->value;
                    $discount_amount = ($total_amount / 100) * $discount_percent;
                    $net_amount = $total_amount - $discount_amount;
                    $tax_amount = ($net_amount / 100) * $request->input('vatTax');
                    $gross_amount = $net_amount + $tax_amount;
                    $options = array('name' => $exisProduct->name, 'image' => $exisProduct->image, 'attributes' => ' ', 'taxRate' => $exisProduct->percentage, 'options' => [], 'extras' => [], 'sku' => $exisProduct->sku, 'weight' => $exisProduct->weight, 'original_price' => $exisProduct->price, 'product_type' => $exisProduct->product_type);
                
                    $orderProduct = [
                        'order_id' => $order->id,
                        'product_id' => $product['product_id'],
                        'product_name' => $exisProduct->name,
                        'product_image' => $exisProduct->image,
                        'qty' => $quantity,
                        'weight' => $exisProduct->weight,
                        'price' => $price,
                        'total_amount' => $total_amount,
                        'discount_percent' => $discount_percent,
                        'discount_amount' => $discount_amount,
                        'net_amount' => $net_amount,
                        'tax_amount' => $tax_amount,
                        'gross_amount' => $gross_amount,
                        'product_options' => [],
                        'options' => json_encode($options),
                        'product_type' => $exisProduct->product_type,
                        'product_category' => $product['category_name'],
                        'product_subcategory' => isset($product['subcategory_name']) ? $product['subcategory_name'] : '',
                        'vat' => $request->input('vatTax'),
                    ];
                } elseif(!empty($product['coupon']) && !is_null($exisProduct->coupon) && !empty($exisProduct->coupon) && isset($exisProduct->coupon) && isset($exisProduct->coupon[strtolower($request->input('couponCode'))]) && $exisProduct->coupon[strtolower($request->input('couponCode'))]['code'] == strtolower($request->input('couponCode'))) {
                    $price = $exisProduct->price / (1 + ($request->input('vatTax') / 100));
                    $total_amount = $price * $quantity;
                    $discount_percent = $exisProduct->coupon[strtolower($request->input('couponCode'))]['value'];
                    $discount_amount = ($total_amount / 100) * $discount_percent;
                    $net_amount = $total_amount - $discount_amount;
                    $tax_amount = ($net_amount / 100) * $request->input('vatTax');
                    $gross_amount = $net_amount + $tax_amount;
                    $options = array('name' => $exisProduct->name, 'image' => $exisProduct->image, 'attributes' => ' ', 'taxRate' => $exisProduct->percentage, 'options' => [], 'extras' => [], 'sku' => $exisProduct->sku, 'weight' => $exisProduct->weight, 'original_price' => $exisProduct->price, 'product_type' => $exisProduct->product_type);
                
                    $orderProduct = [
                        'order_id' => $order->id,
                        'product_id' => $product['product_id'],
                        'product_name' => $exisProduct->name,
                        'product_image' => $exisProduct->image,
                        'qty' => $quantity,
                        'weight' => $exisProduct->weight,
                        'price' => $price,
                        'total_amount' => $total_amount,
                        'discount_percent' => $discount_percent,
                        'discount_amount' => $discount_amount,
                        'net_amount' => $net_amount,
                        'tax_amount' => $tax_amount,
                        'gross_amount' => $gross_amount,
                        'product_options' => [],
                        'options' => json_encode($options),
                        'product_type' => $exisProduct->product_type,
                        'product_category' => $product['category_name'],
                        'product_subcategory' => isset($product['subcategory_name']) ? $product['subcategory_name'] : '',
                        'vat' => $request->input('vatTax'),
                    ];
                } elseif(!is_null($exisProduct->sale_price)) {
                    $price = $exisProduct->price / (1 + ($request->input('vatTax') / 100));
                    $total_amount = $price * $quantity;
                    $discount_percent = $exisProduct->sale_price;
                    $discount_amount = ($total_amount / 100) * $discount_percent;
                    $net_amount = $total_amount - $discount_amount;
                    $tax_amount = ($net_amount / 100) * $request->input('vatTax');
                    $gross_amount = $net_amount + $tax_amount;
                    $options = array('name' => $exisProduct->name, 'image' => $exisProduct->image, 'attributes' => ' ', 'taxRate' => $exisProduct->percentage, 'options' => [], 'extras' => [], 'sku' => $exisProduct->sku, 'weight' => $exisProduct->weight, 'original_price' => $exisProduct->price, 'product_type' => $exisProduct->product_type);
                
                    $orderProduct = [
                        'order_id' => $order->id,
                        'product_id' => $product['product_id'],
                        'product_name' => $exisProduct->name,
                        'product_image' => $exisProduct->image,
                        'qty' => $quantity,
                        'weight' => $exisProduct->weight,
                        'price' => $price,
                        'total_amount' => $total_amount,
                        'discount_percent' => $discount_percent,
                        'discount_amount' => $discount_amount,
                        'net_amount' => $net_amount,
                        'tax_amount' => $tax_amount,
                        'gross_amount' => $gross_amount,
                        'product_options' => [],
                        'options' => json_encode($options),
                        'product_type' => $exisProduct->product_type,
                        'product_category' => $product['category_name'],
                        'product_subcategory' => isset($product['subcategory_name']) ? $product['subcategory_name'] : '',
                        'vat' => $request->input('vatTax'),
                    ];
                }
                // elseif(isset($product['is_gift']) && $product['is_gift'] == true) {
                //     $price = $exisProduct->price / (1 + ($request->input('vatTax') / 100));
                //     $total_amount = $price * $quantity;
                //     $discount_percent = 0;
                //     $discount_amount = 0.00;
                //     $net_amount = $total_amount;
                //     $tax_amount = ($net_amount / 100) * $request->input('vatTax');
                //     $gross_amount = $net_amount + $tax_amount;
                //     $options = array('name' => $exisProduct->name, 'image' => $exisProduct->image, 'attributes' => ' ', 'taxRate' => $exisProduct->percentage, 'options' => [], 'extras' => [], 'sku' => $exisProduct->sku, 'weight' => $exisProduct->weight, 'original_price' => $exisProduct->price, 'product_type' => $exisProduct->product_type);
                
                //     $orderProduct = [
                //         'order_id' => $order->id,
                //         'product_id' => $product['product_id'],
                //         'product_name' => $exisProduct->name,
                //         'product_image' => $exisProduct->image,
                //         'qty' => $quantity,
                //         'weight' => $exisProduct->weight,
                //         'price' => $price,
                //         'total_amount' => $total_amount,
                //         'discount_percent' => $discount_percent,
                //         'discount_amount' => $discount_amount,
                //         'net_amount' => $net_amount,
                //         'tax_amount' => $tax_amount,
                //         'gross_amount' => $gross_amount,
                //         'product_options' => [],
                //         'options' => json_encode($options),
                //         'product_type' => $exisProduct->product_type,
                //         'product_category' => '',
                //         'product_subcategory' => '',
                //         'vat' => $request->input('vatTax'),
                //         'is_gift' => 1,
                //         'campaign' => 'free_gift_fathers_day_2025_campaign',
                //     ];
                // }
                else {
                    $price = $exisProduct->price / (1 + ($request->input('vatTax') / 100));
                    $total_amount = $price * $quantity;
                    $discount_percent = 0;
                    $discount_amount = 0.00;
                    $net_amount = $total_amount - $discount_amount;
                    $tax_amount = ($net_amount / 100) * $request->input('vatTax');
                    $gross_amount = $net_amount + $tax_amount;
                    $options = array('name' => $exisProduct->name, 'image' => $exisProduct->image, 'attributes' => ' ', 'taxRate' => $exisProduct->percentage, 'options' => [], 'extras' => [], 'sku' => $exisProduct->sku, 'weight' => $exisProduct->weight, 'original_price' => $exisProduct->price, 'product_type' => $exisProduct->product_type);
                
                    $orderProduct = [
                        'order_id' => $order->id,
                        'product_id' => $product['product_id'],
                        'product_name' => $exisProduct->name,
                        'product_image' => $exisProduct->image,
                        'qty' => $quantity,
                        'weight' => $exisProduct->weight,
                        'price' => $price,
                        'total_amount' => $total_amount,
                        'discount_percent' => $discount_percent,
                        'discount_amount' => $discount_amount,
                        'net_amount' => $net_amount,
                        'tax_amount' => $tax_amount,
                        'gross_amount' => $gross_amount,
                        'product_options' => [],
                        'options' => json_encode($options),
                        'product_type' => $exisProduct->product_type,
                        'product_category' => $product['category_name'],
                        'product_subcategory' => isset($product['subcategory_name']) ? $product['subcategory_name'] : '',
                        'vat' => $request->input('vatTax'),
                    ];
                }

                OrderProduct::query()->create($orderProduct);

                Product::query()
                    ->where('id', $product['product_id'])
                    ->where('with_storehouse_management', 1)
                    ->where('quantity', '>=', $quantity)
                    ->decrement('quantity', $quantity);

                // $url = "https://c21341-ifservice.cloudiax.com/api/ECommerce/StockStatus?itemCode=123456";
                // // $url = "https://c21341-ifservice.cloudiax.com/api/ECommerce/StockStatus?itemCode=".$exisProduct->barcode;

                // $ch = curl_init();

                // curl_setopt($ch, CURLOPT_URL, $url);
                // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                // // Set the request method to POST
                // curl_setopt($ch, CURLOPT_POST, true);
                // curl_setopt($ch, CURLOPT_HTTPHEADER, [
                //     "Accept: application/json",
                //     "Company: KSA", 
                //     "Authorization: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJVc2VySUQiOiJhZG1pbiIsIkVtcElEIjoiMTAyNDgiLCJDb21wYW55IjoiIiwiV2hzQ29kZSI6IidDdXN0b20nLCdETV8wMScsJ0ZHXzAxJywnRk9DJywnSUNfVUFFJywnUE1fMDEnLCdTUF8wMDEnLCdTUF8wMDInLCdTUF8wMDMnLCdTUF8wMDQnLCdTUF8wMDUnLCdTUF8wMDYnLCdTUF8wMDcnLCdTUF8wMDgnLCdTUF8wMDknLCdTUF8wMTAnLCdTUF8wMTEnLCdTUF8wMTInLCdTUF8wMTMnLCdTUF8wMTQnLCcwMScsJ0NOMDAxXzAxJywnQ3VzdG9tJywnRE1fMDEnLCdGR18wMScsJ0ZHXzAyJywnRkdfMDMnLCdGT0MnLCdJQ18wMScsJ0lDX1VBRScsJ1BNXzAxJywnU1BfMDAxJywnU1BfMDAxXzEnLCdTUF8wMDInLCdTUF8wMDMnLCdTUF8wMDNfMScsJ1NQXzAwNCcsJ1NQXzAwNScsJ1NQXzAwNicsJ1NQXzAwNycsJ1NQXzAwOCcsJ1NQXzAwOScsJ1NQXzAxMCcsJ1NQXzAxMScsJ1NQXzAxMicsJ1NQXzAxMycsJ1NQXzAxNCcsJ1NQXzAxNScsJ1NQXzAxNicsJ1NQXzAxNycsJ1NQXzAxOScsJ1NQXzAyMCcsJ1NQXzAyMF8xJywnU1BfMDIxJywnU1BfMDIyJywnU1BfMDIzJywnU1BfMDI0JywnU1BfMDI1JywnU1BfMDI2JywnU1BfMDI3JywnU1BfMDI4JywnU1BfMDI4XzEnLCdTUF8wMjhfMicsJ1NQXzAyOScsJ1NQXzAzMCcsJ1NQXzAzMScsJ1ZOXzAwMScsJ0N1c3RvbScsJ0RNXzAxJywnRkdfMDEnLCdGT0MnLCdJQ19VQUUnLCdQTV8wMScsJ1NQXzAwMScsJ1NQXzAwMicsJ1NQXzAwMycsJ1NQXzAwNCcsJ1NQXzAwNScsJ1NQXzAwNicsJ1NQXzAwNycsJ1NQXzAwOCcsJzAxJywnQ3VzdG9tJywnRE1fMDEnLCdGR18wMScsJ0ZPQycsJ0lDXzAxJywnSUNfTW92JywnSUNfT0FQJywnSUNfVUFFJywnUE1fMDEnLCdTUF8wMDEnLCdTUF8wMDInLCdTUF8wMDMnLCdTUF8wMDQnLCdTUF8wMDUnLCdTUF8wMDYnLCdTUF8wMDcnLCdTUF8wMDgnLCdTUF8wMDknLCdTUF8wMTAnLCdTUF8wMTEnLCdTUF8wMTInLCdTUF8wMTMnLCdTUF8wMTQnLCdTUF8wMTUnLCdTUF8wMTYnLCdTUF8wMTcnLCdTUF8wMTgnLCdTUF8wMTknLCdTUF8wMjAnLCdTUF8wMjEnLCdTUF8wMjInLCdTUF8wMjMnLCdTUF8wMjQnLCdTUF8wMjUnLCdTUF8wMjYnLCdTUF8wMjcnLCdTUF8wMjgnLCdTUF8wMjknLCdTUF8wMzAnLCdTUF8wMzEnLCdTUF8wMzInLCdTUF8wMzMnLCdTUF8wMzQnLCdTUF8wMzUnLCdTUF8wMzYnLCdTUF8wMzcnLCdTUF8wMzgnLCdTUF8wMzknLCdTUF8wNDAnLCdTUF8wNDEnLCdTUF8wNDInLCdTUF8wNDMnLCdTUF8wNDQnLCdTUF8wNDUnLCdTUF8wNDYnLCdTUF8wNDcnLCdTUF8wNDgnLCdTUF8wNDknLCdTUF8wNTAnLCdTUF8wNTEnLCdTUF8wNTInLCdTUF8wNTMnLCdTUF8wNTQnLCdTUF8wNTUnLCdTUF8wNTYnLCdTUF8wNTcnLCdTUF8wNTgnLCdTUF8wNTknLCdTUF8wNjAnLCdTUF8wNjEnLCdUWVNfMDEnLCcwMScsJ0NOMDAxXzAxJywnQ04wMDJfMDEnLCdDTjAwM18wMScsJ0NOMDA0XzAxJywnQ04wMDVfMDEnLCdDTjAwNl8wMScsJ0N1c3RvbScsJ0RNXzAxJywnRkdfMDEnLCdGR18wMicsJ0ZPQycsJ0lDX09NTicsJ0lDX1RZUycsJ0lDX1VBRScsJ1BNXzAxJywnU01QXzAxJywnU1BfMDAxJywnU1BfMDAyJywnU1BfMDAzJywnU1BfMDA0JywnU1BfMDA1JywnU1BfMDA2JywnU1BfMDA3JywnU1BfMDA4JywnU1BfMDA5JywnU1BfMDEwJywnU1BfMDExJywnU1BfMDEyJywnU1BfMDEzJywnU1BfMDE1JywnU1BfMDE2JywnU1BfMDE3JywnU1BfMDE4JywnU1BfMDE5JywnU1BfMDIwJywnU1BfMDIxJywnU1BfMDIyJywnMDEnLCdBbWF6b24nLCdBVF8wMScsJ0JLXzAxJywnQlJBTkQnLCdDMDIwMjM1NicsJ0NOMDAxXzAxJywnQ04wMDJfMDEnLCdDTjAwM18wMScsJ0NOMDA0XzAxJywnQ04wMDVfMDEnLCdDTjAwNl8wMScsJ0NOMDA3XzAxJywnQ04wMDhfMDEnLCdDV19TTTAwMCcsJ0NXX1NNMDAxJywnQ1dfU00wMDInLCdDV19TTTAwMycsJ0NXX1NNMDA0JywnQ1dfU00wMDUnLCdDV19TTTAwNicsJ0NXX1NNMDA3JywnQ1dfU00wMDgnLCdDV19TTTAwOScsJ0NXX1NNMDEwJywnRE1fMDEnLCdETV8wMicsJ0RNXzAzJywnRE1fMDQnLCdETV8wNScsJ0RNXzA2JywnRUNfMDEnLCdGR18wMScsJ0ZPQycsJ0dGXzAxJywnSUNfQU1QJywnSUNfQkhSJywnSUNfS1NBJywnSUNfTW92JywnSUNfT01OJywnSUNfUUFUJywnSVQnLCdJVDAyJywnUEtfMDEnLCdQTV8wMScsJ1BNXzAyJywnUUNfMDEnLCdSJkQnLCdTS18wMScsJ1NMXzAxJywnU01QXzAxJywnU1BfMDAxJywnU1BfMDAyJywnU1BfMDAzJywnU1BfMDA0JywnU1BfMDA1JywnU1BfMDA2JywnU1BfMDA3JywnU1BfMDA4JywnU1BfMDA5JywnU1BfMDEwJywnU1BfMDExJywnU1BfMDEyJywnU1BfMDEzJywnU1BfMDE0JywnU1BfMDE1JywnU1BfMDE2JywnU1BfMDE3JywnU1BfMDE4JywnU1BfMDE5JywnU1BfMDIwJywnU1BfMDIxJywnU1BfMDIyJywnU1BfMDIzJywnU1BfMDI0JywnU1BfMDI1JywnU1BfMDI2JywnU1BfMDI3JywnU1BfMDI4JywnU1BfMDI5JywnU1BfMDMwJywnU1BfMDMxJywnU1BfMDMyJywnU1BfMDMyXzEnLCdTUF8wMzMnLCdTUF8wMzQnLCdTUF8wMzUnLCdTUF8wMzYnLCdTUF8wMzcnLCdTUF8wMzgnLCdTUF8wMzknLCdTUF8wNDAnLCdTUF8wNDEnLCdTUF8wNDInLCdTUF8wNDMnLCdTUF8wNDQnLCdTUF8wNDUnLCdTUF8wNDYnLCdTUF8wNDcnLCdTUF8wNDgnLCdTUF8wNDknLCdTUF8wNTAnLCdTUF8wNTEnLCdTUF8wNTInLCdTUF8wNTMnLCdTUF8wNTQnLCdTUF8wNTUnLCdTUF8wNTYnLCdTUF8wNTcnLCdTUF8wNTgnLCdTUF8wNTknLCdTUF8wNjAnLCdTUF8wNjEnLCdTUF8wNjInLCdTUF8wNjMnLCdTUF8wNjQnLCdTUF8wNjUnLCdTUF8wNjYnLCdTUF8wNjcnLCdTUF8wNjgnLCdTUF8wNjknLCdTUF8wNzAnLCdTUF8wNzEnLCdTUF8wNzInLCdTUF8wNzMnLCdTUF8wNzQnLCdTUF8wNzUnLCdTUF8wNzYnLCdTUF8wNzcnLCdTUF8wNzknLCdTUF8wODAnLCdTUF8wODEnLCdTUF8wODInLCdTUF8wODMnLCdTUF8wODQnLCdTUF8wODUnLCdTUF8wODYnLCdTUF8wODgnLCdTUF8wODknLCdTUF8wOTAnLCdTUF8wOTEnLCdTUF8wOTInLCdXSF8wMScsJ1dIXzAyJywnV0hfMDMnLCdXSF8wNCcsJ1dIXzA1JywnV0hfMDYnLCdXSF9EUk0nLCdXSF9WZW5kJyIsIlN0b3JlSUQiOiInJywnSE8nLCdPRkInLCdITycsJ0hPJywnUCZFJywnU01BJywnQktXJywnQkNDJywnQlNUJywnSERMJywnREFNJywnSklEJywnQlVLJywnUkFNJywnQ0NCJywnSE1UJywnTUhSJywnQU1CJywnQlNTJywnJywnSE8nLCdITycsJycsJ0pETycsJ01ETycsJ0hPJywnSE8nLCcnLCdITycsJ1AmRScsJ0tBUycsJ0tBU1MnLCdKUUInLCdEQVQnLCdEQVRTJywnTk9SJywnQVNNJywnVEJBJywnQVpNJywnQktSJywnU0tEJywnVEdNJywnT0JNJywnSlVNJywnUUJBJywnS09TJywnU1NKJywnTU9OJywnU0FGJywnUUJGJywnS01TJywnS01TUycsJ01BRycsJ1lSTScsJ01VRycsJ01SSicsJ1NRSicsJ01ESCcsJ01ERycsJ01DVCcsJ01DVFMnLCdWTUNUJywnUkhCJywnT0JIJywnQkFTJywnS1NWJywnJywnSE8nLCcnLCdITycsJ0hPJywnUCZFJywnS1NNJywnSlJLJywnS01BJywnS09EJywnR0FUJywnQkxWJywnTUdUJywnTUdDJywnJywnSE8nLCdITycsJ09GTycsJ0hPJywnSE8nLCdITycsJ0hPJywnSE8nLCdQJkUnLCdTTVQnLCdTS0snLCdTRUInLCdCUksnLCdTTEwnLCdTVVInLCdOSVonLCdTV1EnLCdTT00nLCdTQU0nLCdCUk0nLCdFQlInLCdTQlgnLCdCRFknLCdLQlInLCdBTVInLCdTTk0nLCdBVk0nLCdMV00nLCdKTE4nLCdBS00nLCdBS0InLCdNU04nLCdTTlcnLCdSU1QnLCdCUkEnLCdZQU4nLCdTTE4nLCdTTFUnLCdTQUQnLCdNT00nLCdRVVInLCdCSUQnLCdLQU0nLCdLVUQnLCdTTUwnLCdTTlMnLCdDQ00nLCdNT08nLCdDQ1MnLCdKTFMnLCdPQVMnLCdTU1MnLCdETksnLCdCSEwnLCdNQVQnLCdBTlMnLCdBU0snLCdLQlMnLCdTTVMnLCdGTEonLCdEUU0nLCdFQlMnLCdGQU4nLCdCRFMnLCdBTVMnLCdCREQnLCdPT1MnLCdUTUQnLCdTV1MnLCdNVVMnLCdITycsJycsJycsJycsJycsJycsJycsJycsJycsJycsJ09GUScsJ0hPJywnJywnSE8nLCdITycsJ0hPJywnUCZFJywnSE8nLCdBWlknLCdTSEYnLCdOU1InLCdESEYnLCdNUVInLCdBTUonLCdET00nLCdBTUsnLCdMQkInLCdBV1MnLCdNUksnLCdBRlMnLCdXQVEnLCdRT1MnLCdRUk4nLCdJR1cnLCdFWkQnLCdWSUwnLCdOQVMnLCdTSE4nLCdXQVQnLCcnLCdITycsJ0hPJywnSE8nLCcnLCcnLCcnLCcnLCcnLCdITycsJ0hPJywnSE8nLCdITycsJ0hPJywnSE8nLCdITycsJ0hPJywnSE8nLCdITycsJ0hPJywnSE8nLCdITycsJ0hPJywnSE8nLCdITycsJ0hPJywnSE8nLCdITycsJ0hPJywnSE8nLCdITycsJ0FFQycsJ0hPJywnSE8nLCdITycsJ0hPJywnSE8nLCdITycsJ0hPJywnSE8nLCdITycsJ0hPJywnSE8nLCdITycsJ1AmRScsJ0hPJywnSE8nLCcnLCcnLCdITycsJ0hPJywnREZNJywnQlNNJywnQk5ZJywnQ1RNJywnRE1LJywnS0hMJywnQUpDJywnTVpNJywnQUZNJywnQUFNJywnQldNJywnQlNHJywnQlNYJywnQUdNJywnQUJNJywnQUJDJywnTUZDJywnRFJDJywnREFGJywnRkpNJywnQUtIJywnS0hLJywnTU5NJywnUkFLJywnU0hNJywnTVJEJywnU1JDJywnU0JTJywnU01NJywnTUFNJywnVUFRJywnSlJOJywnSlJNJywnU1FNJywnUk1aJywnQVNTJywnQkFSJywnS0hNJywnTU9RJywnRExNJywnQVlSJywnVUNKJywnQUdaJywnUkhNJywnVUNBJywnVUNCJywnRkNDJywnR0JWJywnRFJNJywnU0NIJywnSFRUJywnTVNGJywnSk1NJywnWkNDJywnR1lNJywnRkNNJywnTVNNJywnREhEJywnUklGJywnS0JNJywnSE1EJywnUldEJywnS1dTJywnQUFLJywnQlJTJywnRE9TJywnU0xNJywnREVSJywnU0NEJywnS0xGJywnU0JBJywnTURNJywnSlJGJywnTExaJywnRkpTJywnUkZNJywnRE1CJywnTVJCJywnREhNJywnSURXJywnSkNQJywnRFNTJywnTVNLJywnSE1BJywnRElCJywnRFNRJywnVU1CJywnQUtEJywnSFRTJywnWUFTJywnR0JJJywnSE8nLCdITycsJ0hPJywnSE8nLCdITycsJ0hPJywnRFdTJywnJyIsIlRlcm1pbmFsSUQiOiIiLCJzYWxlc1BlcnNvbklkIjoiIiwiem9uZUlkIjoiJyonIiwiZXhwIjoxNzczNTU5MjYyfQ.JZfGnaPSXmCanQfq3OWPRkYqqzy_rM9LLyLLiTLMFOo"
                // ]);

                // $response = curl_exec($ch);

                // if (curl_errno($ch)) {
                //     echo 'Error: ' . curl_error($ch);
                // }

                // curl_close($ch);
            }
            // die(';;;');

            // $url = "https://c21341-ifservice.cloudiax.com/api/ECommerce/StockStatus?itemCode=".implode(',', $barcodes);

            // $ch = curl_init();

            // curl_setopt($ch, CURLOPT_URL, $url);
            // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            // // Set the request method to POST
            // curl_setopt($ch, CURLOPT_POST, true);
            // curl_setopt($ch, CURLOPT_HTTPHEADER, [
            //     "Accept: application/json",
            //     "Company: KSA", 
            //     "Authorization: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJVc2VySUQiOiJhZG1pbiIsIkVtcElEIjoiMTAyNDgiLCJDb21wYW55IjoiIiwiV2hzQ29kZSI6IidDdXN0b20nLCdETV8wMScsJ0ZHXzAxJywnRk9DJywnSUNfVUFFJywnUE1fMDEnLCdTUF8wMDEnLCdTUF8wMDInLCdTUF8wMDMnLCdTUF8wMDQnLCdTUF8wMDUnLCdTUF8wMDYnLCdTUF8wMDcnLCdTUF8wMDgnLCdTUF8wMDknLCdTUF8wMTAnLCdTUF8wMTEnLCdTUF8wMTInLCdTUF8wMTMnLCdTUF8wMTQnLCcwMScsJ0NOMDAxXzAxJywnQ3VzdG9tJywnRE1fMDEnLCdGR18wMScsJ0ZHXzAyJywnRkdfMDMnLCdGT0MnLCdJQ18wMScsJ0lDX1VBRScsJ1BNXzAxJywnU1BfMDAxJywnU1BfMDAxXzEnLCdTUF8wMDInLCdTUF8wMDMnLCdTUF8wMDNfMScsJ1NQXzAwNCcsJ1NQXzAwNScsJ1NQXzAwNicsJ1NQXzAwNycsJ1NQXzAwOCcsJ1NQXzAwOScsJ1NQXzAxMCcsJ1NQXzAxMScsJ1NQXzAxMicsJ1NQXzAxMycsJ1NQXzAxNCcsJ1NQXzAxNScsJ1NQXzAxNicsJ1NQXzAxNycsJ1NQXzAxOScsJ1NQXzAyMCcsJ1NQXzAyMF8xJywnU1BfMDIxJywnU1BfMDIyJywnU1BfMDIzJywnU1BfMDI0JywnU1BfMDI1JywnU1BfMDI2JywnU1BfMDI3JywnU1BfMDI4JywnU1BfMDI4XzEnLCdTUF8wMjhfMicsJ1NQXzAyOScsJ1NQXzAzMCcsJ1NQXzAzMScsJ1ZOXzAwMScsJ0N1c3RvbScsJ0RNXzAxJywnRkdfMDEnLCdGT0MnLCdJQ19VQUUnLCdQTV8wMScsJ1NQXzAwMScsJ1NQXzAwMicsJ1NQXzAwMycsJ1NQXzAwNCcsJ1NQXzAwNScsJ1NQXzAwNicsJ1NQXzAwNycsJ1NQXzAwOCcsJzAxJywnQ3VzdG9tJywnRE1fMDEnLCdGR18wMScsJ0ZPQycsJ0lDXzAxJywnSUNfTW92JywnSUNfT0FQJywnSUNfVUFFJywnUE1fMDEnLCdTUF8wMDEnLCdTUF8wMDInLCdTUF8wMDMnLCdTUF8wMDQnLCdTUF8wMDUnLCdTUF8wMDYnLCdTUF8wMDcnLCdTUF8wMDgnLCdTUF8wMDknLCdTUF8wMTAnLCdTUF8wMTEnLCdTUF8wMTInLCdTUF8wMTMnLCdTUF8wMTQnLCdTUF8wMTUnLCdTUF8wMTYnLCdTUF8wMTcnLCdTUF8wMTgnLCdTUF8wMTknLCdTUF8wMjAnLCdTUF8wMjEnLCdTUF8wMjInLCdTUF8wMjMnLCdTUF8wMjQnLCdTUF8wMjUnLCdTUF8wMjYnLCdTUF8wMjcnLCdTUF8wMjgnLCdTUF8wMjknLCdTUF8wMzAnLCdTUF8wMzEnLCdTUF8wMzInLCdTUF8wMzMnLCdTUF8wMzQnLCdTUF8wMzUnLCdTUF8wMzYnLCdTUF8wMzcnLCdTUF8wMzgnLCdTUF8wMzknLCdTUF8wNDAnLCdTUF8wNDEnLCdTUF8wNDInLCdTUF8wNDMnLCdTUF8wNDQnLCdTUF8wNDUnLCdTUF8wNDYnLCdTUF8wNDcnLCdTUF8wNDgnLCdTUF8wNDknLCdTUF8wNTAnLCdTUF8wNTEnLCdTUF8wNTInLCdTUF8wNTMnLCdTUF8wNTQnLCdTUF8wNTUnLCdTUF8wNTYnLCdTUF8wNTcnLCdTUF8wNTgnLCdTUF8wNTknLCdTUF8wNjAnLCdTUF8wNjEnLCdUWVNfMDEnLCcwMScsJ0NOMDAxXzAxJywnQ04wMDJfMDEnLCdDTjAwM18wMScsJ0NOMDA0XzAxJywnQ04wMDVfMDEnLCdDTjAwNl8wMScsJ0N1c3RvbScsJ0RNXzAxJywnRkdfMDEnLCdGR18wMicsJ0ZPQycsJ0lDX09NTicsJ0lDX1RZUycsJ0lDX1VBRScsJ1BNXzAxJywnU01QXzAxJywnU1BfMDAxJywnU1BfMDAyJywnU1BfMDAzJywnU1BfMDA0JywnU1BfMDA1JywnU1BfMDA2JywnU1BfMDA3JywnU1BfMDA4JywnU1BfMDA5JywnU1BfMDEwJywnU1BfMDExJywnU1BfMDEyJywnU1BfMDEzJywnU1BfMDE1JywnU1BfMDE2JywnU1BfMDE3JywnU1BfMDE4JywnU1BfMDE5JywnU1BfMDIwJywnU1BfMDIxJywnU1BfMDIyJywnMDEnLCdBbWF6b24nLCdBVF8wMScsJ0JLXzAxJywnQlJBTkQnLCdDMDIwMjM1NicsJ0NOMDAxXzAxJywnQ04wMDJfMDEnLCdDTjAwM18wMScsJ0NOMDA0XzAxJywnQ04wMDVfMDEnLCdDTjAwNl8wMScsJ0NOMDA3XzAxJywnQ04wMDhfMDEnLCdDV19TTTAwMCcsJ0NXX1NNMDAxJywnQ1dfU00wMDInLCdDV19TTTAwMycsJ0NXX1NNMDA0JywnQ1dfU00wMDUnLCdDV19TTTAwNicsJ0NXX1NNMDA3JywnQ1dfU00wMDgnLCdDV19TTTAwOScsJ0NXX1NNMDEwJywnRE1fMDEnLCdETV8wMicsJ0RNXzAzJywnRE1fMDQnLCdETV8wNScsJ0RNXzA2JywnRUNfMDEnLCdGR18wMScsJ0ZPQycsJ0dGXzAxJywnSUNfQU1QJywnSUNfQkhSJywnSUNfS1NBJywnSUNfTW92JywnSUNfT01OJywnSUNfUUFUJywnSVQnLCdJVDAyJywnUEtfMDEnLCdQTV8wMScsJ1BNXzAyJywnUUNfMDEnLCdSJkQnLCdTS18wMScsJ1NMXzAxJywnU01QXzAxJywnU1BfMDAxJywnU1BfMDAyJywnU1BfMDAzJywnU1BfMDA0JywnU1BfMDA1JywnU1BfMDA2JywnU1BfMDA3JywnU1BfMDA4JywnU1BfMDA5JywnU1BfMDEwJywnU1BfMDExJywnU1BfMDEyJywnU1BfMDEzJywnU1BfMDE0JywnU1BfMDE1JywnU1BfMDE2JywnU1BfMDE3JywnU1BfMDE4JywnU1BfMDE5JywnU1BfMDIwJywnU1BfMDIxJywnU1BfMDIyJywnU1BfMDIzJywnU1BfMDI0JywnU1BfMDI1JywnU1BfMDI2JywnU1BfMDI3JywnU1BfMDI4JywnU1BfMDI5JywnU1BfMDMwJywnU1BfMDMxJywnU1BfMDMyJywnU1BfMDMyXzEnLCdTUF8wMzMnLCdTUF8wMzQnLCdTUF8wMzUnLCdTUF8wMzYnLCdTUF8wMzcnLCdTUF8wMzgnLCdTUF8wMzknLCdTUF8wNDAnLCdTUF8wNDEnLCdTUF8wNDInLCdTUF8wNDMnLCdTUF8wNDQnLCdTUF8wNDUnLCdTUF8wNDYnLCdTUF8wNDcnLCdTUF8wNDgnLCdTUF8wNDknLCdTUF8wNTAnLCdTUF8wNTEnLCdTUF8wNTInLCdTUF8wNTMnLCdTUF8wNTQnLCdTUF8wNTUnLCdTUF8wNTYnLCdTUF8wNTcnLCdTUF8wNTgnLCdTUF8wNTknLCdTUF8wNjAnLCdTUF8wNjEnLCdTUF8wNjInLCdTUF8wNjMnLCdTUF8wNjQnLCdTUF8wNjUnLCdTUF8wNjYnLCdTUF8wNjcnLCdTUF8wNjgnLCdTUF8wNjknLCdTUF8wNzAnLCdTUF8wNzEnLCdTUF8wNzInLCdTUF8wNzMnLCdTUF8wNzQnLCdTUF8wNzUnLCdTUF8wNzYnLCdTUF8wNzcnLCdTUF8wNzknLCdTUF8wODAnLCdTUF8wODEnLCdTUF8wODInLCdTUF8wODMnLCdTUF8wODQnLCdTUF8wODUnLCdTUF8wODYnLCdTUF8wODgnLCdTUF8wODknLCdTUF8wOTAnLCdTUF8wOTEnLCdTUF8wOTInLCdXSF8wMScsJ1dIXzAyJywnV0hfMDMnLCdXSF8wNCcsJ1dIXzA1JywnV0hfMDYnLCdXSF9EUk0nLCdXSF9WZW5kJyIsIlN0b3JlSUQiOiInJywnSE8nLCdPRkInLCdITycsJ0hPJywnUCZFJywnU01BJywnQktXJywnQkNDJywnQlNUJywnSERMJywnREFNJywnSklEJywnQlVLJywnUkFNJywnQ0NCJywnSE1UJywnTUhSJywnQU1CJywnQlNTJywnJywnSE8nLCdITycsJycsJ0pETycsJ01ETycsJ0hPJywnSE8nLCcnLCdITycsJ1AmRScsJ0tBUycsJ0tBU1MnLCdKUUInLCdEQVQnLCdEQVRTJywnTk9SJywnQVNNJywnVEJBJywnQVpNJywnQktSJywnU0tEJywnVEdNJywnT0JNJywnSlVNJywnUUJBJywnS09TJywnU1NKJywnTU9OJywnU0FGJywnUUJGJywnS01TJywnS01TUycsJ01BRycsJ1lSTScsJ01VRycsJ01SSicsJ1NRSicsJ01ESCcsJ01ERycsJ01DVCcsJ01DVFMnLCdWTUNUJywnUkhCJywnT0JIJywnQkFTJywnS1NWJywnJywnSE8nLCcnLCdITycsJ0hPJywnUCZFJywnS1NNJywnSlJLJywnS01BJywnS09EJywnR0FUJywnQkxWJywnTUdUJywnTUdDJywnJywnSE8nLCdITycsJ09GTycsJ0hPJywnSE8nLCdITycsJ0hPJywnSE8nLCdQJkUnLCdTTVQnLCdTS0snLCdTRUInLCdCUksnLCdTTEwnLCdTVVInLCdOSVonLCdTV1EnLCdTT00nLCdTQU0nLCdCUk0nLCdFQlInLCdTQlgnLCdCRFknLCdLQlInLCdBTVInLCdTTk0nLCdBVk0nLCdMV00nLCdKTE4nLCdBS00nLCdBS0InLCdNU04nLCdTTlcnLCdSU1QnLCdCUkEnLCdZQU4nLCdTTE4nLCdTTFUnLCdTQUQnLCdNT00nLCdRVVInLCdCSUQnLCdLQU0nLCdLVUQnLCdTTUwnLCdTTlMnLCdDQ00nLCdNT08nLCdDQ1MnLCdKTFMnLCdPQVMnLCdTU1MnLCdETksnLCdCSEwnLCdNQVQnLCdBTlMnLCdBU0snLCdLQlMnLCdTTVMnLCdGTEonLCdEUU0nLCdFQlMnLCdGQU4nLCdCRFMnLCdBTVMnLCdCREQnLCdPT1MnLCdUTUQnLCdTV1MnLCdNVVMnLCdITycsJycsJycsJycsJycsJycsJycsJycsJycsJycsJ09GUScsJ0hPJywnJywnSE8nLCdITycsJ0hPJywnUCZFJywnSE8nLCdBWlknLCdTSEYnLCdOU1InLCdESEYnLCdNUVInLCdBTUonLCdET00nLCdBTUsnLCdMQkInLCdBV1MnLCdNUksnLCdBRlMnLCdXQVEnLCdRT1MnLCdRUk4nLCdJR1cnLCdFWkQnLCdWSUwnLCdOQVMnLCdTSE4nLCdXQVQnLCcnLCdITycsJ0hPJywnSE8nLCcnLCcnLCcnLCcnLCcnLCdITycsJ0hPJywnSE8nLCdITycsJ0hPJywnSE8nLCdITycsJ0hPJywnSE8nLCdITycsJ0hPJywnSE8nLCdITycsJ0hPJywnSE8nLCdITycsJ0hPJywnSE8nLCdITycsJ0hPJywnSE8nLCdITycsJ0FFQycsJ0hPJywnSE8nLCdITycsJ0hPJywnSE8nLCdITycsJ0hPJywnSE8nLCdITycsJ0hPJywnSE8nLCdITycsJ1AmRScsJ0hPJywnSE8nLCcnLCcnLCdITycsJ0hPJywnREZNJywnQlNNJywnQk5ZJywnQ1RNJywnRE1LJywnS0hMJywnQUpDJywnTVpNJywnQUZNJywnQUFNJywnQldNJywnQlNHJywnQlNYJywnQUdNJywnQUJNJywnQUJDJywnTUZDJywnRFJDJywnREFGJywnRkpNJywnQUtIJywnS0hLJywnTU5NJywnUkFLJywnU0hNJywnTVJEJywnU1JDJywnU0JTJywnU01NJywnTUFNJywnVUFRJywnSlJOJywnSlJNJywnU1FNJywnUk1aJywnQVNTJywnQkFSJywnS0hNJywnTU9RJywnRExNJywnQVlSJywnVUNKJywnQUdaJywnUkhNJywnVUNBJywnVUNCJywnRkNDJywnR0JWJywnRFJNJywnU0NIJywnSFRUJywnTVNGJywnSk1NJywnWkNDJywnR1lNJywnRkNNJywnTVNNJywnREhEJywnUklGJywnS0JNJywnSE1EJywnUldEJywnS1dTJywnQUFLJywnQlJTJywnRE9TJywnU0xNJywnREVSJywnU0NEJywnS0xGJywnU0JBJywnTURNJywnSlJGJywnTExaJywnRkpTJywnUkZNJywnRE1CJywnTVJCJywnREhNJywnSURXJywnSkNQJywnRFNTJywnTVNLJywnSE1BJywnRElCJywnRFNRJywnVU1CJywnQUtEJywnSFRTJywnWUFTJywnR0JJJywnSE8nLCdITycsJ0hPJywnSE8nLCdITycsJ0hPJywnRFdTJywnJyIsIlRlcm1pbmFsSUQiOiIiLCJzYWxlc1BlcnNvbklkIjoiIiwiem9uZUlkIjoiJyonIiwiZXhwIjoxNzczNTU5MjYyfQ.JZfGnaPSXmCanQfq3OWPRkYqqzy_rM9LLyLLiTLMFOo"
            // ]);

            // $response = curl_exec($ch);

            // if (curl_errno($ch)) {
            //     echo 'Error: ' . curl_error($ch);
            // }

            // curl_close($ch);

            if ($couponCode = $request->input('couponCode')) {
                Discount::getFacadeRoot()->afterOrderPlaced($couponCode, $request->input('customer_id') ? $request->input('customer_id') : $customer_id);
            }

            if($request->input('customer_id')) {
                $loggedInCustomer = Customer::where('id', $request->input('customer_id'))->first();
            } else {
                $loggedInCustomer = null;
            }

            $invoice = Invoice::query()->create([
                'reference_type' => 'Botble\Ecommerce\Models\Order',
                'reference_id' => $order->id,
                'customer_name' => $loggedInCustomer ? $loggedInCustomer->name : $request->input('billingAddress.first_name').' '.$request->input('billingAddress.last_name'),
                'customer_email' => $loggedInCustomer ? $loggedInCustomer->email : $request->input('billingAddress.email'),
                'customer_phone' => $loggedInCustomer ? $loggedInCustomer->phone : $request->input('billingAddress.mobile'),
                'customer_address' => $request->input('billingAddress.area').' '.$request->input('billingAddress.building'),
                'sub_total' => $request->input('totalPrice') ? : 0,
                'tax_amount' => ($request->input('totalPrice') / (1 + ($request->input('vatTax') / 100)) * ($request->input('vatTax') / 100)) + ($request->input('shippingPrice') / (1 + ($request->input('vatTax') / 100)) * ($request->input('vatTax') / 100)) + ($request->input('servicePrice') / (1 + ($request->input('vatTax') / 100)) * ($request->input('vatTax') / 100)),
                'shipping_amount' => $request->input('shippingPrice') / (1 + ($request->input('vatTax') / 100)),
                'shipping_amount_vat' => $request->input('shippingPrice') / (1 + ($request->input('vatTax') / 100)) * ($request->input('vatTax') / 100),
                'service_amount' => $request->input('servicePrice') / (1 + ($request->input('vatTax') / 100)),
                'service_amount_vat' => $request->input('servicePrice') / (1 + ($request->input('vatTax') / 100)) * ($request->input('vatTax') / 100),
                'vat' => $request->input('vatTax'),
                'discount_amount' => $request->input('discount_amount') ? : 0,
                'shipping_method' => $request->input('shipping_method') ? : ShippingMethodEnum::DEFAULT,
                'coupon_code' => $request->input('couponCode'),
                'discount_description' => $request->input('discount_description'),
                'amount' => $request->input('finalPrice'),
                'payment_id' => $order->payment_id,
                'status' => $request->input('payment_status'),
            ]);

            foreach ($request->input('products') as $product) {
                
                $quantity = $product['quantity'] ? $product['quantity'] : 1;

                $exisProduct = Product::where('id', $product['product_id'])->first();

                $exisProduct->discount = DiscountProduct::select('value', 'start_date', 'end_date')->where('product_id', $product['product_id'])->whereNull('code')->whereDate('start_date', '<=', now())->whereDate('end_date', '>=', now())->join('ec_discounts', 'ec_discounts.id', '=', 'ec_discount_products.discount_id', 'left')->first();

                $coupons = DiscountProduct::select('code', 'value', 'start_date', 'end_date')->where('product_id', $product['product_id'])->whereNotNull('code')->whereDate('start_date', '<=', now())->whereDate('end_date', '>=', now())->join('ec_discounts', 'ec_discounts.id', '=', 'ec_discount_products.discount_id', 'left')->get();

                // Store in a temporary property or a new array
                $couponData = [];
                foreach ($coupons as $coupon) {
                    $couponData[strtolower($coupon->code)] = [
                        'code' => strtolower($coupon->code),
                        'value' => $coupon->value,
                        'start_date' => $coupon->start_date,
                        'end_date' => $coupon->end_date,
                    ];
                }

                $exisProduct->coupon = $couponData;

                if(!is_null($exisProduct->discount)) {
                    $price = $exisProduct->price / (1 + ($request->input('vatTax') / 100));
                    $total_amount = $price * $quantity;
                    $discount_percent = $exisProduct->discount->value;
                    $discount_amount = ($total_amount / 100) * $discount_percent;
                    $net_amount = $total_amount - $discount_amount;
                    $tax_amount = ($net_amount / 100) * $request->input('vatTax');
                    $gross_amount = $net_amount + $tax_amount;
                    $options = array('name' => $exisProduct->name, 'image' => $exisProduct->image, 'attributes' => ' ', 'taxRate' => $exisProduct->percentage, 'options' => [], 'extras' => [], 'sku' => $exisProduct->sku, 'weight' => $exisProduct->weight, 'original_price' => $exisProduct->price, 'product_type' => $exisProduct->product_type);
                
                    $orderProduct = [
                        'invoice_id' => $invoice->id,
                        'reference_type' => 'Botble\Ecommerce\Models\Product',
                        'reference_id' => $exisProduct->id,
                        'name' => $exisProduct->name,
                        'description' => $exisProduct->description,
                        'image' => $exisProduct->image,
                        'qty' => $quantity,
                        'price' => $price,
                        'sub_total' => $total_amount,
                        'discount_percent' => $discount_percent,
                        'discount_amount' => $discount_amount,
                        'net_amount' => $net_amount,
                        'tax_amount' => $tax_amount,
                        'gross_amount' => $gross_amount,
                        'amount' => $gross_amount,
                        'options' => json_encode($options),
                    ];
                } elseif(!empty($product['coupon']) && !is_null($exisProduct->coupon) && !empty($exisProduct->coupon) && isset($exisProduct->coupon) && isset($exisProduct->coupon[strtolower($request->input('couponCode'))]) && $exisProduct->coupon[strtolower($request->input('couponCode'))]['code'] == strtolower($request->input('couponCode'))) {
                    $price = $exisProduct->price / (1 + ($request->input('vatTax') / 100));
                    $total_amount = $price * $quantity;
                    $discount_percent = $exisProduct->coupon[strtolower($request->input('couponCode'))]['value'];
                    $discount_amount = ($total_amount / 100) * $discount_percent;
                    $net_amount = $total_amount - $discount_amount;
                    $tax_amount = ($net_amount / 100) * $request->input('vatTax');
                    $gross_amount = $net_amount + $tax_amount;
                    $options = array('name' => $exisProduct->name, 'image' => $exisProduct->image, 'attributes' => ' ', 'taxRate' => $exisProduct->percentage, 'options' => [], 'extras' => [], 'sku' => $exisProduct->sku, 'weight' => $exisProduct->weight, 'original_price' => $exisProduct->price, 'product_type' => $exisProduct->product_type);
                
                    $orderProduct = [
                        'invoice_id' => $invoice->id,
                        'reference_type' => 'Botble\Ecommerce\Models\Product',
                        'reference_id' => $exisProduct->id,
                        'name' => $exisProduct->name,
                        'description' => $exisProduct->description,
                        'image' => $exisProduct->image,
                        'qty' => $quantity,
                        'price' => $price,
                        'sub_total' => $total_amount,
                        'discount_percent' => $discount_percent,
                        'discount_amount' => $discount_amount,
                        'net_amount' => $net_amount,
                        'tax_amount' => $tax_amount,
                        'gross_amount' => $gross_amount,
                        'amount' => $gross_amount,
                        'options' => json_encode($options),
                    ];
                } elseif(!is_null($exisProduct->sale_price)) {
                    $price = $exisProduct->price / (1 + ($request->input('vatTax') / 100));
                    $total_amount = $price * $quantity;
                    $discount_percent = $exisProduct->sale_price;
                    $discount_amount = ($total_amount / 100) * $discount_percent;
                    $net_amount = $total_amount - $discount_amount;
                    $tax_amount = ($net_amount / 100) * $request->input('vatTax');
                    $gross_amount = $net_amount + $tax_amount;
                    $options = array('name' => $exisProduct->name, 'image' => $exisProduct->image, 'attributes' => ' ', 'taxRate' => $exisProduct->percentage, 'options' => [], 'extras' => [], 'sku' => $exisProduct->sku, 'weight' => $exisProduct->weight, 'original_price' => $exisProduct->price, 'product_type' => $exisProduct->product_type);
                
                    $orderProduct = [
                         'invoice_id' => $invoice->id,
                        'reference_type' => 'Botble\Ecommerce\Models\Product',
                        'reference_id' => $exisProduct->id,
                        'name' => $exisProduct->name,
                        'description' => $exisProduct->description,
                        'image' => $exisProduct->image,
                        'qty' => $quantity,
                        'price' => $price,
                        'sub_total' => $total_amount,
                        'discount_percent' => $discount_percent,
                        'discount_amount' => $discount_amount,
                        'net_amount' => $net_amount,
                        'tax_amount' => $tax_amount,
                        'gross_amount' => $gross_amount,
                        'amount' => $gross_amount,
                        'options' => json_encode($options),
                    ];
                }
                // elseif(isset($product['is_gift']) && $product['is_gift'] == true) {
                //     $price = $exisProduct->price / (1 + ($request->input('vatTax') / 100));
                //     $total_amount = $price * $quantity;
                //     $discount_percent = 0;
                //     $discount_amount = 0.00;
                //     $net_amount = $total_amount;
                //     $tax_amount = ($net_amount / 100) * $request->input('vatTax');
                //     $gross_amount = $net_amount + $tax_amount;
                //     $options = array('name' => $exisProduct->name, 'image' => $exisProduct->image, 'attributes' => ' ', 'taxRate' => $exisProduct->percentage, 'options' => [], 'extras' => [], 'sku' => $exisProduct->sku, 'weight' => $exisProduct->weight, 'original_price' => $exisProduct->price, 'product_type' => $exisProduct->product_type);
                
                //     $orderProduct = [
                //         'invoice_id' => $invoice->id,
                //         'reference_type' => 'Botble\Ecommerce\Models\Product',
                //         'reference_id' => $exisProduct->id,
                //         'name' => $exisProduct->name,
                //         'description' => $exisProduct->description,
                //         'image' => $exisProduct->image,
                //         'qty' => $quantity,
                //         'price' => $price,
                //         'sub_total' => $total_amount,
                //         'discount_percent' => $discount_percent,
                //         'discount_amount' => $discount_amount,
                //         'net_amount' => $net_amount,
                //         'tax_amount' => $tax_amount,
                //         'gross_amount' => $gross_amount,
                //         'amount' => $gross_amount,
                //         'options' => json_encode($options)
                //     ];
                // }
                else {
                    $price = $exisProduct->price / (1 + ($request->input('vatTax') / 100));
                    $total_amount = $price * $quantity;
                    $discount_percent = 0;
                    $discount_amount = 0.00;
                    $net_amount = $total_amount - $discount_amount;
                    $tax_amount = ($net_amount / 100) * $request->input('vatTax');
                    $gross_amount = $net_amount + $tax_amount;
                    $options = array('name' => $exisProduct->name, 'image' => $exisProduct->image, 'attributes' => ' ', 'taxRate' => $exisProduct->percentage, 'options' => [], 'extras' => [], 'sku' => $exisProduct->sku, 'weight' => $exisProduct->weight, 'original_price' => $exisProduct->price, 'product_type' => $exisProduct->product_type);
                
                    $orderProduct = [
                        'invoice_id' => $invoice->id,
                        'reference_type' => 'Botble\Ecommerce\Models\Product',
                        'reference_id' => $exisProduct->id,
                        'name' => $exisProduct->name,
                        'description' => $exisProduct->description,
                        'image' => $exisProduct->image,
                        'qty' => $quantity,
                        'price' => $price,
                        'sub_total' => $total_amount,
                        'discount_percent' => $discount_percent,
                        'discount_amount' => $discount_amount,
                        'net_amount' => $net_amount,
                        'tax_amount' => $tax_amount,
                        'gross_amount' => $gross_amount,
                        'amount' => $gross_amount,
                        'options' => json_encode($options),
                    ];
                }

                InvoiceItem::query()->create($orderProduct);
            }

            if($request->input('payment_method') == 'payfort') {
                $resp = $this->payFortPayment($request, $data, $order);
                // if($resp['redirect_url']) {
                    return response()->json([
                        'message'          => 'Redirecting to Payfort...',
                        'order_id'         => $order->code,
                        'payment_method'   => $request->input('payment_method'),
                        'total'            => $order->amount,
                        'sub_total'        => $order->sub_total,
                        'shipping_amount'  => $order->shipping_amount,
                        'products'         => $prod,
                        'redirect_url'     => $resp->original['redirectUrl'],
                        'request_params' => $resp->original['requestParams']
                    ]);
                // }
            }

            if($request->input('payment_method') == 'tabby') {
                $resp = $this->tabbyPayment($request, $data, $order, $request->input('products'));
                // echo "<pre>";print_r($resp);
                if($resp['status'] == 'created' || $resp['status'] == 'CREATED') {
                    return response()->json([
                        'message'          => 'Redirecting to Tabby...',
                        'order_id'         => $order->code,
                        'payment_method'   => $request->input('payment_method'),
                        'total'            => $order->amount,
                        'sub_total'        => $order->sub_total,
                        'shipping_amount'  => $order->shipping_amount,
                        'products'         => $prod,
                        'redirect_url'     => $resp['configuration']['available_products']['installments'][0]['web_url'],
                        // 'request_params' => $resp->original['requestParams']
                    ]);
                } else {
                    return response()->json([
                       'message' => 'Sorry, Tabby is unable to approve this purchase. Please use an alternative payment method for your order.',
                        'error'  => 'Sorry, Tabby is unable to approve this purchase. Please use an alternative payment method for your order.'
                    ]);
                }
            }

            $request['payment_status'] = 'completed';
            $createPaymentForOrderService->execute(
                $order,
                $request->input('payment_method'),
                'completed',
                $customer_id
            );

            return response()->json([
                'message'          => 'Order created successfully',
                'order_id'         => $order->code,
                'payment_method'   => $request->input('payment_method'),
                'total'            => $order->amount,
                'sub_total'        => $order->sub_total,
                'shipping_amount'  => $order->shipping_amount,
                'id'                => $order->id,
                'customer_name'=>      $request->input('shippingAddress.first_name')?$request->input('shippingAddress.first_name').' '.$request->input('shippingAddress.last_name'):$request->input('billingAddress.first_name').' '.$request->input('billingAddress.last_name'),
                'products'         => $prod
            ]);
        }
    }

    public function payFortPayment(Request $request, $shippingData, $order) {
        $paymentStr = '';
        foreach ($request->input('products') as $product) {
            $quantity = $product['quantity'] ? $product['quantity'] : 1;
            $exisProduct = Product::select('name')->where('ec_products.id', $product['product_id'])->first();
            $paymentStr .= $exisProduct->name. ' - '.$quantity.'---';
        }

        // How to calculate request signature
        $shaString  = '';
        // array request
        $arrData = array(
            'command'            => 'PURCHASE',
            'access_code'        => 'WFM9NH5byvZhvY8saSiS',
            // 'access_code'        => 'qNkFjECwpNxo36jeMQPm',
            'merchant_identifier'=> 'c5563f2d',
            // 'merchant_identifier'=> 'tUPfHAHW',
            'merchant_reference' => explode('#', $order->code)[1],
            'amount'             => $request->input('finalPrice') * 100,
            'currency'           => 'SAR',
            'language'           => 'en',
            'order_description'  => $paymentStr,
            'return_url'         => 'http://localhost/ahmed-admin-ksa/public/api/payFortPaymentRedirect?order_number='.base64_encode($order->code),
            "customer_name"=> $request->input('billingAddress.first_name').' '.$request->input('billingAddress.last_name'),
            'customer_email'     => $request->input('billingAddress.email'),
            "phone_number"=> $request->input('billingAddress.mobile'),
            "billing_street"=> $request->input('billingAddress.area').' '.$request->input('billingAddress.building'),
            "billing_city"=> $request->input('billingAddress.province'),
            "billing_stateProvince"=> $request->input('billingAddress.province'),
            "billing_country"=> "SAU",
            "shipping_street"=> $shippingData['billing_street'],
            "shipping_city"=> $shippingData['billing_city'],
            "shipping_stateProvince"=> $shippingData['billing_stateProvince'],
            "shipping_country"=> "SAU",
        );
        // sort an array by key
        ksort($arrData);
        foreach ($arrData as $key => $value) {
            $shaString .= "$key=$value";
        }
        // make sure to fill your sha request pass phrase
        $shaString = "02zOYQShW56enOiLUkdHnx-&" . $shaString . "02zOYQShW56enOiLUkdHnx-&";
        // $shaString = "742vW6dVadHHAxMO45VESS*{". $shaString . '742vW6dVadHHAxMO45VESS*{';
        $signature = hash("sha256", $shaString);
        // your request signature
        // echo $signature;
        $requestParams = array(
            'command'            => 'PURCHASE',
            'access_code'        => 'WFM9NH5byvZhvY8saSiS',
            // 'access_code'        => 'qNkFjECwpNxo36jeMQPm',
            'merchant_identifier'=> 'c5563f2d',
            // 'merchant_identifier'=> 'tUPfHAHW',
            'merchant_reference' => explode('#', $order->code)[1],
            'amount'             => $request->input('finalPrice') * 100,
            'currency'           => 'SAR',
            'language'           => 'en',
            'order_description'  => $paymentStr,
            'return_url'         => 'http://localhost/ahmed-admin-ksa/public/api/payFortPaymentRedirect?order_number='.base64_encode($order->code),
            "customer_name"=> $request->input('billingAddress.first_name').' '.$request->input('billingAddress.last_name'),
            'customer_email'     => $request->input('billingAddress.email'),
            "phone_number"=> $request->input('billingAddress.mobile'),
            "billing_street"=> $request->input('billingAddress.area').' '.$request->input('billingAddress.building'),
            "billing_city"=> $request->input('billingAddress.province'),
            "billing_stateProvince"=> $request->input('billingAddress.province'),
            "billing_country"=> "SAU",
            "shipping_street"=> $shippingData['billing_street'],
            "shipping_city"=> $shippingData['billing_city'],
            "shipping_stateProvince"=> $shippingData['billing_stateProvince'],
            "shipping_country"=> "SAU",
            "signature"=> $signature
        );


        $redirectUrl = 'https://sbcheckout.payfort.com/FortAPI/paymentPage';
        // $redirectUrl = 'https://checkout.payfort.com/FortAPI/paymentPage';
        return response(['redirectUrl' => $redirectUrl, 'requestParams' => $requestParams]);
    }

    public function payFortPaymentRedirect(Request $request, CreatePaymentForOrderService $createPaymentForOrderService) {
        // echo "<pre>";print_r($request->all());
        // $request->query('email');die;
        // $customer = Customer::where('email', base64_decode($request->query('email')))->first();
        // $order = Order::where('user_id', $customer->id)->orderBy('id', 'desc')->first();
        $order = Order::where('code', base64_decode($request->query('order_number')))->orderBy('id', 'desc')->first();
        // echo "<pre>";print_r($order);
        $createPaymentForOrderService->execute(
            $order,
            'payfort',
            $request['response_message'],
            $order->user_id,
            $request->input('fort_id'),
            $request['response_message'],
        );

        header('Location: http://localhost:3000/'.$order->lang.'/shop-order-payment-complete?q='.base64_encode($order->code));exit();
    }

    public function tabbyPayment(Request $request, $shippingData, $order, $prods) {
        // echo "<pre>";print_r($shippingData);
        $requestParams = [
            "payment" => [
                "amount" => $request->input('finalPrice'),
                "currency" => "SAR",
                "buyer" => [
                    "phone" => '+966'.$request->input('billingAddress.mobile'),
                    "email" => $request->input('billingAddress.email'),
                    "name" => $request->input('billingAddress.first_name').' '.$request->input('billingAddress.last_name')
                ],
                "shipping_address" => [
                    "city" => $shippingData['billing_city'],
                    "address" => $shippingData['billing_address'],
                    "zip" => $shippingData['billing_zip']
                ],
                "order" => [
                    "tax_amount" => round((float)$order->tax_amount, 2), // Not Required
                    "shipping_amount" => round((float)$order->shipping_amount * 1.15, 2), // Not Required
                    "discount_amount" => number_format(($order->discount_amount), 2, '.', ''), // Not Required
                    "reference_id" => explode("#", $order->code)[1],
                    "items" => []
                ],
                "buyer_history" => [
                    "registered_since" => $shippingData['customer_created_at'],
                    "loyalty_level" => $shippingData['customer_order_count'],
                ],
                "order_history" => [],
            ],
            "lang" => "en",
            "merchant_code" => "assaaste",
            "merchant_urls" => [
                "success" => "http://localhost/ahmed-admin-ksa/public/api/tabbyPaymentRedirect?order_number=".base64_encode($order->code),
                "cancel" => "http://localhost/ahmed-admin-ksa/public/api/tabbyPaymentRedirect?order_number=".base64_encode($order->code),
                "failure" => "http://localhost/ahmed-admin-ksa/public/api/tabbyPaymentRedirect?order_number=".base64_encode($order->code)
            ]
        ];

        // Loop through your items and dynamically populate the 'items' array
        foreach ($prods as $item) {
            $requestParams['payment']['order']['items'][] = [
                "title" => $item['product_name'],  // Adjust to match your item object structure
                "quantity" => $item['quantity'], // Adjust to match your item object structure
                "unit_price" => $item['price'],
                // "discount_amount" => $item->discount_amount,
                "reference_id" => (string)$item['product_id'],
                "category" => $item['category_name']  // Adjust as needed
            ];
        }

        if(!empty($shippingData['customer_order_history'])) {
            foreach ($shippingData['customer_order_history'] as $it) {
                $requestParams['payment']['order_history'][] = [
                    "purchased_at" =>  Carbon::parse($it->created_at)->utc()->toIso8601String(),
                    "amount" => $it['amount'],
                    "status" => $it['status'],
                    "buyer" => [
                        "phone" => '+966'.$request->input('billingAddress.mobile'),
                        "email" => $request->input('billingAddress.email'),
                        "name" => $request->input('billingAddress.first_name').' '.$request->input('billingAddress.last_name')
                    ],
                    "shipping_address" => [
                        "city" => $shippingData['billing_city'],
                        "address" => $shippingData['billing_address'],
                        "zip" => $shippingData['billing_zip']
                    ],
                ];
            }
        } else {
            $requestParams['payment']['order_history'][] = [
                "purchased_at" => Carbon::now()->utc()->toIso8601ZuluString(),
                "amount" => $request->input('finalPrice'),
                "status" => "new",
                "buyer" => [
                    "phone" => '+966'.$request->input('billingAddress.mobile'),
                    "email" => $request->input('billingAddress.email'),
                    "name" => $request->input('billingAddress.first_name').' '.$request->input('billingAddress.last_name')
                ],
                "shipping_address" => [
                    "city" => $shippingData['billing_city'],
                    "address" => $shippingData['billing_address'],
                    "zip" => $shippingData['billing_zip']
                ],
            ];
        }
        
        // echo "<pre>";print_r($requestParams);die;
        // echo json_encode($requestParams);die;
        $PROFILE_ID = 48012;
        // $PROFILE_ID = 48353;
        $SERVER_KEY = 'pk_test_019228fd-8e52-3ecd-f813-bf11dc8e2118';
        // $SERVER_KEY = 'pk_019228fd-8e52-3ecd-f813-bf103e201ffe';
        $BASE_URL = 'https://api.tabby.ai/api/v2/checkout';

        $data['profile_id'] = $PROFILE_ID;
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $BASE_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($requestParams, true),
            CURLOPT_HTTPHEADER => array(
                'authorization: Bearer ' . $SERVER_KEY,
                'Content-Type:application/json'
            ),
        ));

        $response = json_decode(curl_exec($curl), true);
        curl_close($curl);
        // print_r($response);
        return $response;
    }

    public function tabbyPaymentRedirect(Request $request, CreatePaymentForOrderService $createPaymentForOrderService) {
        // echo "<pre>";print_r($request->all());die;
        // $request->query('email');die;
        $payment_id = $request->input('payment_id') ? $request->input('payment_id') : $request->query('payment_id');
        // $customer = Customer::where('email', base64_decode($request->query('email')))->first();
        // $order = Order::where('user_id', $customer->id)->orderBy('id', 'desc')->first();
        $order = Order::where('code', base64_decode($request->query('order_number')))->orderBy('id', 'desc')->first();
        // echo "<pre>";print_r($order);
        $BASE_URL = 'https://api.tabby.ai/api/v2/payments/';
        // $SERVER_KEY = 'sk_019228fd-8e52-3ecd-f813-bf1111408314';
        $SERVER_KEY = 'sk_test_019228fd-8e52-3ecd-f813-bf12445e44d4';

        // Initialize cURL session
        $ch = curl_init();

        // Set cURL options
        curl_setopt($ch, CURLOPT_URL, $BASE_URL.$payment_id);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return response instead of outputting it
        // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Disable SSL verification (useful for testing)
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'authorization: Bearer ' . $SERVER_KEY,
        ]);
        // Execute cURL session
        // $response = curl_exec($ch);

        // Check for errors
        // if (curl_errno($ch)) {
        //     echo 'cURL Error: ' . curl_error($ch);
        // }

        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);
        // echo "<pre>";print_r($response);die;

        if($response['status'] == 'AUTHORIZED' || $response['status'] == 'authorized') {

            // Initialize cURL session
            $c = curl_init();

            // Set cURL options
            curl_setopt_array($c, array(
                CURLOPT_URL => 'https://api.tabby.ai/api/v2/payments/'.$response["id"].'/captures',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode(["amount" => $response["amount"], true]),
                CURLOPT_HTTPHEADER => array(
                    'authorization: Bearer ' . $SERVER_KEY,
                    'Content-Type:application/json'
                ),
            ));
    
            $resp = json_decode(curl_exec($c), true);
            curl_close($c);
            // echo "<pre>";print_r($resp);die;
            
            $createPaymentForOrderService->execute(
                $order,
                'tabby',
                $resp['status'],
                $order->user_id,
                $request->input('payment_id'),
                (isset($resp['description']) && !empty($resp['description'])) ? $resp['description'] : $resp['status'],
            );
        } else {
            $createPaymentForOrderService->execute(
                $order,
                'tabby',
                $response['status'],
                $order->user_id,
                $request->input('payment_id'),
                (isset($response['description']) && !empty($response['description'])) ? $response['description'] : $response['status'],
            );
        }

        header('Location: http://localhost:3000/'.$order->lang.'/shop-order-payment-complete?q='.base64_encode($order->code));exit();
    }

    public function trackOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_number'      => 'required',
            'billing_email'      => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors());
        }

        $order = Order::select('ec_orders.id', 'ec_orders.code', 'ec_orders.status', 'ec_orders.amount', 'ec_orders.sub_total', 'ec_orders.shipping_amount', 'payments.payment_channel', 'ec_orders.created_at', 'ec_orders.service_amount', 'ec_orders.vat', 'ec_orders.tax_amount', 'payments.status AS payment_status')->join('ec_order_addresses', 'ec_order_addresses.order_id', 'ec_orders.id')->join('payments', 'payments.order_id', 'ec_orders.id')->where('ec_orders.code', $request->input('order_number'))->where('ec_order_addresses.email', $request->input('billing_email'))->first();

        if(!$order) {
            return response()->json(['message' => 'Order not found']);
        }

        $prod = OrderProduct::where('ec_order_product.order_id', $order->id)->get();

        return response()->json([
            'message'          => 'Tracking Details Fetched successfully',
            'order_id'         => $order->code,
            'payment_method'   => $order->payment_channel,
            'total'            => $order->amount,
            'sub_total'        => $order->sub_total,
            'shipping_amount'  => $order->shipping_amount,
            'status'           => $order->status,
            'created_at'       => $order->created_at,
            'service_amount'   => $order->service_amount,
            'vat_amount'       => $order->vat,
            'tax_amount'       => $order->tax_amount,
            'payment_status'   => $order->payment_status,
            'products'         => $prod
        ]);
    }

    public function orderDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_number'      => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors());
        }

        $order = Order::select('ec_orders.id', 'ec_orders.code', 'ec_orders.status', 'ec_orders.amount', 'ec_orders.sub_total', 'ec_orders.shipping_amount', 'payments.payment_channel', 'ec_orders.created_at', 'ec_orders.service_amount', 'ec_orders.vat', 'ec_orders.tax_amount', 'payments.status AS payment_status', 'payments.description AS payment_description', 'payments.payment_channel AS payment_channel','ec_order_addresses.name')->join('ec_order_addresses', 'ec_order_addresses.order_id', 'ec_orders.id', 'left')->join('payments', 'payments.order_id', 'ec_orders.id', 'left')->where('ec_orders.code', $request->input('order_number'))->first();

        if(!$order) {
            return response()->json(['message' => 'Order not found']);
        }

        $prod = OrderProduct::where('ec_order_product.order_id', $order->id)->get();

        return response()->json([
            'message'          => 'Details Fetched successfully',
            'order_id'         => $order->code,
            'payment_method'   => $order->payment_channel,
            'total'            => $order->amount,
            'sub_total'        => $order->sub_total,
            'shipping_amount'  => $order->shipping_amount,
            'status'           => $order->status,
            'created_at'       => $order->created_at,
            'service_amount'   => $order->service_amount,
            'id'                =>   $order->id,
            'customer_name'=> $order->name,
            'vat_amount'       => $order->vat,
            'tax_amount'       => $order->tax_amount,
            'payment_status'   => $order->payment_status,
            'payment_description' => $order->payment_description,
            'payment_channel' => $order->payment_channel,
            'products'         => $prod
        ]);
    }

    public function validateCoupon(Request $request) {
         $validator = Validator::make($request->all(), [
            'couponCode'      => 'required',
            'mobile_number' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors());
        }

        $coupon = DiscountModel::where('code', $request->input('couponCode'))->where('start_date', '<=', now())->where('end_date', '>=', now())->first();

        if(!$coupon) {
            return response()->json(['message' => 'Invalid Coupon Code']);
        }

        // $mobile_verification = MobileVerification::where('phone', $request->input('mobile_number'))->first();

        // if(!$mobile_verification) {
        //     return response()->json(['message' => 'Verify Mobile Number First']);
        // }

        $order_address = OrderAddress::where('phone', $request->input('mobile_number'))->first();

        if($order_address) {
            $order = Order::where('id', $order_address->order_id)->first();
            if($order) {
                $customer_discount = DB::table('ec_customer_used_coupons')->where('customer_id', $order->user_id)->where('discount_id', $coupon->id)->first();
                if($customer_discount) {
                    return response()->json(['message' => 'You Have Already Used this Coupon Code']);
                }
            }
        }

        return response()->json([
            'message'          => 'Details Fetched successfully',
            'coupon'            => $coupon
        ]);
    }
}
