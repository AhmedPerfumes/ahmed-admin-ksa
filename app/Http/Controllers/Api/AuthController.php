<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Botble\Ecommerce\Models\Customer;
use Botble\Ecommerce\Models\MobileVerification;
use Illuminate\Support\Facades\Auth;
use Botble\Ecommerce\Models\Review;
use Botble\Ecommerce\Models\OrderAddress;
use Botble\Ecommerce\Models\Address;

class AuthController extends Controller
{
    /**
     * Register a new customer
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function signup(Request $request) {

        // $validator = Validator::make($request->all(), [
        //     'name'      => 'required|string|max:255',
        //     'email'     => 'required|string|max:255',
        //     'mobile'     => 'required|numeric',
        //     'password'  => 'required|string'
        //     ]);

        // if ($validator->fails()) {
        //     return response()->json($validator->errors());
        // }

        $customer = Customer::where('email', $request->email)->orWhere('phone', $request->mobile)->first();

        if ($customer) {
            return response()->json([
                'message'       => 'Duplicate Email Id Or Mobile Number',
            ]);
        }

        // $customer = Customer::create([
        //     'name'      => $request->name,
        //     'email'     => $request->email,
        //     'phone'     => $request->mobile,
        //     'password'  => Hash::make($request->password)
        // ]);

        // $token = $customer->createToken('auth_token')->plainTextToken;

        $otp = rand(1111, 9999);

        $ch = curl_init();

        $password = env("INBOXMEDIA_PASSWORD");

        curl_setopt($ch, CURLOPT_URL, "https://myinboxmedia.ae/api/mim/SendSMS?userid=MIM2500371&pwd=".$password."&mobile=966".ltrim($request->mobile, $request->mobile[0])."&sender=AHMDPRF&msg=".$otp."".urlencode(' is your OTP for Registration')."&msgtype=19");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);die;
        }
        \Log::info("signup SMS API Raw Response: " . $result);
        curl_close ($ch);

        // $customer->otp = $otp;
        // $customer->save();

        // return response()->json([
        //     'message'          => 'OTP Sent on Above Mobile Number'
        // ]);

        $Mobile_verification = MobileVerification::create([
            'otp'     => $otp,
            'phone'     => $request->mobile,
        ]);

        return response()->json([
            'message'          => 'OTP Sent on Above Mobile Number'
        ]);
    }

    /**
     * Verify OTP
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyOTP(Request $request) {

        if($request->flag == 'checkout') {
            $validator = Validator::make($request->all(), [
            'mobile'     => 'required|numeric',
            'otp'  => 'required|numeric'
          ]);

            if ($validator->fails()) {
                return response()->json($validator->errors());
            }
            $mobile_verification = MobileVerification::where('phone', $request->mobile)->where('otp', $request->otp)->orderBy('id', 'desc')->first();

            if (!$mobile_verification) {
                return response()->json([
                    'message'       => 'Invalid Mobile Number or OTP',
                ]);
            }

            $mobile_verification->otp = 0;
            $mobile_verification->save();

            $customer = Customer::where('email', $request->email)->orWhere('phone', $request->mobile)->first();

            if (!$customer) {
                $customer = Customer::create([
                    'name'      => 'Guest User',
                    'email'     => $request->email,
                    'phone'     => $request->mobile,
                    'password'  => Hash::make($request->password)
                ]);
            }

            $customer->tokens()->delete();

            $token = $customer->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message'       => 'OTP Verified Successfully',
                'access_token'  => $token,
            ]);
        } else {
            // $customer = Customer::select('id', 'name', 'email', 'phone')->where('phone', $request->mobile)->where('otp', $request->otp)->first();

            // if (!$customer) {
            //     return response()->json([
            //         'message'       => 'Invalid Mobile Number or OTP',
            //     ]);
            // }

            // $customer->otp = 0;
            // $customer->save();

            $validator = Validator::make($request->all(), [
                'mobile'     => 'required|numeric',
                'otp'  => 'required|numeric'
            ]);

            if ($validator->fails()) {
                return response()->json($validator->errors());
            }

            if($request->flag == 'fpassword') {
                $customer = Customer::select('id', 'name', 'email', 'phone')->where('phone', $request->mobile)->where('otp', $request->otp)->first();

                if (!$customer) {
                    return response()->json([
                        'message'       => 'Invalid Mobile Number or OTP',
                    ]);
                }

                $customer->otp = 0;
                $customer->save();

                return response()->json([
                    'message'       => 'OTP Verified Successfully',
                    'data'          => $customer,
                    // 'coupon'            => $coupon
                ]);
            }

            $mobile_verification = MobileVerification::where('phone', $request->mobile)->where('otp', $request->otp)->orderBy('id', 'desc')->first();

            if (!$mobile_verification) {
                return response()->json([
                    'message'       => 'Invalid Mobile Number or OTP',
                ]);
            }

            $mobile_verification->otp = 0;
            $mobile_verification->save();

            $validator = Validator::make($request->all(), [
                // 'customer_id'      => 'required',
                'name' => 'required',
                'email' => 'required|email|unique:ec_customers,email,',
                'mobile' => 'required|unique:ec_customers,phone,',
                'password' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json($validator->errors());
            }

            $customer = Customer::create([
                'name'      => $request->name,
                'email'     => $request->email,
                'phone'     => $request->mobile,
                'password'  => Hash::make($request->password)
            ]);

            $apiUrl = env('SMART_VIEW_COUPON_API_URL').'Coupon/Register';

            $postData = [
                'couponId' => "3FDF342E-52C6-4D73-AD84-DA2605E15DF8",
                'customerName'  => $customer->name,
                'email' => $customer->email,
                'mobileNo' => $customer->phone,
            ];

            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json',]);

            $apiResponse = curl_exec($ch);
            if ($apiResponse === false) {
                \Log::error('Coupon Register API Error', [
                    'error' => curl_error($ch),
                ]);
            } else {
                \Log::info('Coupon Register API Response', [
                    'success' => $apiResponse,
                ]);
            }
            curl_close($ch);

            $customer->tokens()->delete();

            $token = $customer->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message'       => $request->flag == 'fpassword' ? 'OTP Verified Successfully' : 'Customer Registered Successfully',
                'data'          => $customer,
                'access_token'  => $token,
                // 'token_type'    => 'Bearer'
            ]);
        }
    }

    /**
     * Sign In
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function signin(Request $request) {

        $validator = Validator::make($request->all(), [
            'mobile'     => 'required|numeric',
            'password'  => 'required|string'
          ]);

        if ($validator->fails()) {
            return response()->json($validator->errors());
        }

        $customer = Customer::select('id', 'name', 'email', 'password', 'phone')->where('phone', $request->mobile)->where('status', 'activated')->first();

        if (!$customer || !Hash::check($request->password, $customer->password)) {
            return response()->json([
                'message'       => 'Invalid Mobile Number or Password or Inactive Status',
            ]);
        }

        // $address = Address::where('customer_id', $customer->id)->get();

        // if(!$address->isEmpty()) {
        //     if ($address->count() == 1) {
        //         $original = $address->first()->replicate(); // clone the model
        //         $original->id = -1; // change ID
        //         $address->push($original); // add to collection
        //     }

        //     $customer->addresses = $address;
        // }

        $customer->tokens()->delete();

        $token = $customer->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message'       => 'Login Successfully',
            'data'          => $customer,
            'access_token'  => $token,
            // 'token_type'    => 'Bearer'
        ]);
    }

    /**
     * Sign Out
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function signout() {
        $customer = Auth::guard('api')->user();
        if (!$customer) {
            return response()->json(['message' => 'No Active Session'], 401);
        }
        $customer->tokens()->delete();
        return response()->json(['message' => 'Logged Out Successfully']);
    }

    public function getCustomer(Request $request)
    {
        $customer = Auth::guard('api')->user();

        if (!$customer) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return response()->json($customer);
    }

    public function sendOTP(Request $request) {

        $validator = Validator::make($request->all(), [
            'mobile'     => 'required|numeric',
            ]);

        if ($validator->fails()) {
            return response()->json($validator->errors());
        }        

        $raw_mobile = (string) $request->mobile;
        $clean_mobile = $raw_mobile;

        if (substr($raw_mobile, 0, 1) === '0') {
            $clean_mobile = substr($raw_mobile, 1);
        }

        $final_mobile = "966" . $clean_mobile;

        $otp = rand(1111, 9999);

        $ch = curl_init();

        $password = env("INBOXMEDIA_PASSWORD");
        $sms_params = [
            'userid' => 'MIM2500371',
            'pwd'    => $password,
            'mobile' => $final_mobile,
            'sender' => 'AHMDPRF',
            'msg'    => $otp . ' is your OTP for Registration',
            'msgtype'=> '19'
        ];
        $sms_url = "https://myinboxmedia.ae/api/mim/SendSMS?" . http_build_query($sms_params);

        curl_setopt($ch, CURLOPT_URL, $sms_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);die;
        }
        \Log::info("sendOTP SMS API Raw Response: " . $result);
        curl_close ($ch);

        if($request->flag == 'fpassword') {
            $customer = Customer::select('id', 'name', 'email', 'phone')->where('phone', $request->mobile)->first();

            if (!$customer) {
                return response()->json([
                    'message'       => 'Invalid Mobile Number',
                ]);
            }

            $customer->otp = $otp;
            $customer->save();
        } else {
            $mobile_verification = MobileVerification::where('phone', $request->mobile)->get();

            if ($mobile_verification) {
                foreach ($mobile_verification as $key => $value) {
                    MobileVerification::where('phone', $value->phone)->delete();
                }
            }

            $Mobile_verification = MobileVerification::create([
                'otp'     => $otp,
                'phone'     => $request->mobile,
            ]);

            $Mobile_verification->save();
        }

        return response()->json([
            'message'          => 'OTP Sent on Above Mobile Number'
        ]);
    }

    public function submitReview(Request $request)
    {
        try {
            $productId = is_numeric($request?->order_id) ? (int)$request->order_id : null;

            Review::create([
                'product_id'     => $productId ?? 0,
                'customer_name'  => $request?->customer_name ?? 'Guest',
                'star'           => $request->star ?? 5,
                'comment'        => $request->comment ?? '',
                'status'         => 'published',
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Review submitted successfully.',
            ]);
        } catch (\Throwable $e) {
            \Log::error('Error submitting review: ' . $e->getMessage());
            return response()->json([
                'status' => 'success',
                'message' => 'Review submitted successfully.',
            ]);
        }
    }

}
