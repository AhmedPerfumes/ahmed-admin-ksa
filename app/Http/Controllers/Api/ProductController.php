<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Botble\Ecommerce\Models\ProductCategory;
use Botble\Ecommerce\Models\Product;
use Botble\Slug\Models\Slug;
use Botble\Ecommerce\Models\OrderProduct;
use Botble\Ecommerce\Models\DiscountProduct;
use Botble\Ecommerce\Models\ProductFragranceNote;
use Botble\Ecommerce\Models\ProductFragranceMap;
use App\Models\Promotion;

class ProductController extends Controller
{
    public function getProducts(Request $request)
    {
        // $customer = Auth::guard('api')->user();

        // if (!$customer) {
        //     return response()->json(['message' => 'Unauthorized'], 401);
        // }

        $category = $request['category'];
        $subCategory = $request['subCategory'];
        $product = $request['product'];

        if (!isset($category) || empty($category)) {
            return response()->json([
                'message'       => 'Kindly Provide Category',
            ]);
        }

        if(!$product) {
            $categoryData = ProductCategory::select('id', 'parent_id')->where('status', 'published')->where('parent_id', 0)
            // ->where('name', $category)
            ->where(DB::raw("REGEXP_REPLACE(REPLACE(REPLACE(name, '&amp;', '&'), '&', ' '),'[^a-zA-Z0-9-]', '')"), '=', implode('', explode(' ', $category)))
            ->get()->first();

            if (isset($subCategory)) {      
                $subCategoryData = ProductCategory::select('id')->where('status', 'published')->where('parent_id', $categoryData->id)
                // ->where('name', $subCategory)
                ->where(DB::raw("REGEXP_REPLACE(REPLACE(REPLACE(name, '&amp;', '&'), '&', ' '),'[^a-zA-Z0-9-]', '')"), '=', implode('', explode(' ', $subCategory)))
                ->get()->first();
            }

            if (!isset($subCategory)) {
                $productCategory = ProductCategory::select('id', 'name', 'image', 'mobile_image','description','description_ar')->where('status', 'published')->where('parent_id', 0)->where('id', $categoryData->id)->get()->first();
            } else {
                $productCategory = ProductCategory::select('id', 'name', 'image', 'mobile_image','description','description_ar')->where('status', 'published')->where('parent_id', $categoryData->id)->where('id', $subCategoryData->id)->get()->first();
            }
            
            if (!isset($subCategory)) {
                if ($category == 'HAIR MIST') {
                    $productCategory->products = DB::table('ec_product_category_product')
                        ->select(DB::raw('CAST(ec_products.price AS DECIMAL(8,2)) as price'), 'ec_product_category_product.product_id', 'ec_products.name as product_name', 'ec_products.name_ar as product_name_ar', 'ec_products.image', 'ec_products.images', 'ec_product_collections.name as collection_name', 'ec_products.description', 'ec_products.quantity as product_qty', 'ec_product_labels.name as label_name', 'ec_product_labels.color as label_color', 'ec_products.sale_price')
                        ->join ('ec_product_categories', 'ec_product_category_product.category_id', '=', 'ec_product_categories.id', 'left')
                        ->join ('ec_products', 'ec_product_category_product.product_id', '=', 'ec_products.id', 'left')
                        ->join('ec_product_collection_products', 'ec_product_collection_products.product_id', '=', 'ec_products.id', 'left')
                        ->join('ec_product_collections', 'ec_product_collection_products.product_collection_id', '=', 'ec_product_collections.id', 'left')
                        ->join('ec_product_label_products', 'ec_product_label_products.product_id', '=', 'ec_products.id', 'left')
                        ->join('ec_product_labels', 'ec_product_label_products.product_label_id', '=', 'ec_product_labels.id', 'left')
                        ->where('ec_product_category_product.category_id', 6)
                        ->orderBy('ec_product_category_product.product_id', 'desc')
                        ->get();
                
                    foreach ($productCategory->products as $key => $val) {
                        $val->permalink = Slug::select('key')->where('reference_id', $val->product_id)->first();
                
                        $total_sales = OrderProduct::select(DB::raw('SUM(qty) as total_sales'))
                            ->join('ec_orders', 'ec_order_product.order_id', '=', 'ec_orders.id')
                            ->where('product_id', $val->product_id)
                            ->groupBy('product_id')
                            ->first();

                            $val->sales = $total_sales ? intval($total_sales->total_sales) : 0;

                            // $val->discount = DiscountProduct::select('value', 'start_date', 'end_date')
                            // ->where('product_id', $val->product_id)
                            // ->whereNull('code')
                            // ->whereDate('start_date', '<=', now())
                            // ->whereDate('end_date', '>=', now())
                            // ->join('ec_discounts', 'ec_discounts.id', '=', 'ec_discount_products.discount_id', 'left')
                            // ->first();

                            // $coupons = DiscountProduct::select('code', 'value', 'start_date', 'end_date')->where('product_id', $val->product_id)->whereNotNull('code') ->whereDate('start_date', '<=', now())->whereDate('end_date', '>=', now())->join('ec_discounts', 'ec_discounts.id', '=', 'ec_discount_products.discount_id', 'left')->get();
                            // $val->coupon = [];
                            // foreach ($coupons as $coupon) {
                            //     $val->coupon[strtolower($coupon->code)] = [
                            //         'code' => strtolower($coupon->code),
                            //         'value' => $coupon->value,
                            //         'start_date' => $coupon->start_date,
                            //         'end_date' => $coupon->end_date,
                            //     ];
                            // }
                             $val->discount = null;

                            $individualDiscount = Promotion::where('type', 'discount')
                                ->whereDate('start_date', '<=', now())
                                ->whereDate('end_date', '>=', now())
                                ->whereHas('discountRules', function ($query) {
                                    $query->where('apply_to', 'individual');
                                })
                                ->whereHas('discountRules.individualRules', function ($query) use ($val) {
                                    $query->where('product_id', $val->product_id);
                                })
                                ->with(['discountRules' => function ($query) {
                                    $query->where('apply_to', 'individual')
                                        ->select('id', 'promotion_id', 'apply_to');
                                }, 'discountRules.individualRules' => function ($query) use ($val) {
                                    $query->where('product_id', $val->product_id)
                                        ->select('discount_rule_id', 'product_id', 'value', 'discount_type', 'product_price', 'discount_amount', 'final_price');
                                }])
                                ->first();

                            if ($individualDiscount) {
                                $discountRule = $individualDiscount->discountRules->first();
                                $individualRule = $discountRule ? $discountRule->individualRules->first() : null;
                                if ($individualRule) {
                                    $val->discount = (object) [
                                        'value' => intval($individualRule->value),
                                        'apply_to' => $discountRule->apply_to,
                                        'discount_type' => $individualRule->discount_type,
                                        'product_price' => $individualRule->product_price,
                                        'discount_amount' => $individualRule->discount_amount,
                                        'final_price' => $individualRule->final_price,
                                        'start_date' => $individualDiscount->start_date->format('Y-m-d H:i:s'),
                                        'end_date' => $individualDiscount->end_date->format('Y-m-d H:i:s'),
                                    ];
                                }
                            } else {
                                // If no individual discount, try to fetch discount for group/all products
                                $groupDiscount = Promotion::where('type', 'discount')
                                    ->whereDate('start_date', '<=', now())
                                    ->whereDate('end_date', '>=', now())
                                    ->whereHas('discountRules', function ($query) {
                                        $query->where('apply_to', '!=', 'individual');
                                    })
                                    ->whereHas('discountRules.products', function ($query) use ($val) {
                                        $query->where('product_id', $val->product_id);
                                    })
                                    ->with(['discountRules' => function ($query) {
                                        $query->where('apply_to', '!=', 'individual')
                                            ->select('id', 'promotion_id', 'percentage', 'apply_to');
                                    }])
                                    ->first();

                                if ($groupDiscount) {
                                    $discountRule = $groupDiscount->discountRules->first();
                                    if ($discountRule) {
                                        $val->discount = (object) [
                                            'value' => intval($discountRule->percentage),
                                            'apply_to' => $discountRule->apply_to,
                                            'discount_type' => 'percent',
                                            'product_price' => null,
                                            'discount_amount' => null,
                                            'final_price' => null,
                                            'start_date' => $groupDiscount->start_date->format('Y-m-d H:i:s'),
                                            'end_date' => $groupDiscount->end_date->format('Y-m-d H:i:s'),
                                        ];
                                    }
                                }
                            }

                            // Fetch active coupons for the product
                            $coupons = Promotion::where('type', 'coupon')
                                ->whereDate('start_date', '<=', now())
                                ->whereDate('end_date', '>=', now())
                                ->whereHas('couponRules.products', function ($query) use ($val) {
                                    $query->where('product_id', $val->product_id);
                                })
                                ->with(['couponRules' => function ($query) use ($val) {
                                    $query->whereNotNull('coupon_code')
                                        ->select('id', 'promotion_id', 'coupon_code', 'percentage')
                                        ->with(['products' => function ($subQuery) use ($val) {
                                            $subQuery->where('product_id', $val->product_id)
                                                    ->select('id', 'coupon_rule_id', 'product_id');
                                        }]);
                                }])
                                ->get();

                            $val->coupon = [];
                            foreach ($coupons as $promotion) {
                                foreach ($promotion->couponRules as $couponRule) {
                                    if ($couponRule->coupon_code && $couponRule->products->isNotEmpty()) {
                                        $val->coupon[strtolower($couponRule->coupon_code)] = [
                                            'code' => strtolower($couponRule->coupon_code),
                                            'value' => intval($couponRule->percentage),
                                            'start_date' => $promotion->start_date->format('Y-m-d H:i:s'),
                                            'end_date' => $promotion->end_date->format('Y-m-d H:i:s'),
                                        ];
                                    }
                                }
                            }

                        }
                } elseif($category == 'EXTRAIT DE PARFUM') {
                    $productCategory->products = DB::table('ec_product_category_product')
                        ->select(DB::raw('CAST(ec_products.price AS DECIMAL(8,2)) as price'), 'ec_product_category_product.product_id', 'ec_products.name as product_name', 'ec_products.name_ar as product_name_ar', 'ec_products.image', 'ec_products.images', 'ec_product_collections.name as collection_name', 'ec_products.description', 'ec_products.quantity as product_qty', 'ec_product_labels.name as label_name', 'ec_product_labels.color as label_color', 'ec_products.sale_price')
                        ->join ('ec_product_categories', 'ec_product_category_product.category_id', '=', 'ec_product_categories.id', 'left')
                        ->join ('ec_products', 'ec_product_category_product.product_id', '=', 'ec_products.id', 'left')
                        ->join('ec_product_collection_products', 'ec_product_collection_products.product_id', '=', 'ec_products.id', 'left')
                        ->join('ec_product_collections', 'ec_product_collection_products.product_collection_id', '=', 'ec_product_collections.id', 'left')
                        ->join('ec_product_label_products', 'ec_product_label_products.product_id', '=', 'ec_products.id', 'left')
                        ->join('ec_product_labels', 'ec_product_label_products.product_label_id', '=', 'ec_product_labels.id', 'left')
                        ->where('ec_product_category_product.category_id', 22)
                        ->orderBy('ec_product_category_product.product_id', 'desc')
                        ->get();

                        foreach ($productCategory->products as $key => $val) {
                            $val->permalink = Slug::select('key')->where('reference_id', $val->product_id)->first();
                            $total_sales = OrderProduct::select(DB::raw('SUM(qty) as total_sales'))
                            ->join('ec_orders', 'ec_order_product.order_id', '=', 'ec_orders.id')
                            // ->where('ec_orders.status', 'completed') // Optional: filter by order status
                            ->where('product_id', $val->product_id)
                            ->groupBy('product_id')
                            // ->orderBy('total_sales', 'desc')
                            // ->limit(10) // Optional: limit to top 10
                            ->first();

                            $val->sales = $total_sales ? intval($total_sales->total_sales) : 0;

                            // $val->discount = DiscountProduct::select('value', 'start_date', 'end_date')->where('product_id', $val->product_id)->whereNull('code') ->whereDate('start_date', '<=', now())->whereDate('end_date', '>=', now())->join('ec_discounts', 'ec_discounts.id', '=', 'ec_discount_products.discount_id', 'left')->first();

                            // $coupons = DiscountProduct::select('code', 'value', 'start_date', 'end_date')->where('product_id', $val->product_id)->whereNotNull('code') ->whereDate('start_date', '<=', now())->whereDate('end_date', '>=', now())->join('ec_discounts', 'ec_discounts.id', '=', 'ec_discount_products.discount_id', 'left')->get();
                            // $val->coupon = [];
                            // foreach ($coupons as $coupon) {
                            //     $val->coupon[strtolower($coupon->code)] = [
                            //         'code' => strtolower($coupon->code),
                            //         'value' => $coupon->value,
                            //         'start_date' => $coupon->start_date,
                            //         'end_date' => $coupon->end_date,
                            //     ];
                            // }
                             $val->discount = null;

                            $individualDiscount = Promotion::where('type', 'discount')
                                ->whereDate('start_date', '<=', now())
                                ->whereDate('end_date', '>=', now())
                                ->whereHas('discountRules', function ($query) {
                                    $query->where('apply_to', 'individual');
                                })
                                ->whereHas('discountRules.individualRules', function ($query) use ($val) {
                                    $query->where('product_id', $val->product_id);
                                })
                                ->with(['discountRules' => function ($query) {
                                    $query->where('apply_to', 'individual')
                                        ->select('id', 'promotion_id', 'apply_to');
                                }, 'discountRules.individualRules' => function ($query) use ($val) {
                                    $query->where('product_id', $val->product_id)
                                        ->select('discount_rule_id', 'product_id', 'value', 'discount_type', 'product_price', 'discount_amount', 'final_price');
                                }])
                                ->first();

                            if ($individualDiscount) {
                                $discountRule = $individualDiscount->discountRules->first();
                                $individualRule = $discountRule ? $discountRule->individualRules->first() : null;
                                if ($individualRule) {
                                    $val->discount = (object) [
                                        'value' => intval($individualRule->value),
                                        'apply_to' => $discountRule->apply_to,
                                        'discount_type' => $individualRule->discount_type,
                                        'product_price' => $individualRule->product_price,
                                        'discount_amount' => $individualRule->discount_amount,
                                        'final_price' => $individualRule->final_price,
                                        'start_date' => $individualDiscount->start_date->format('Y-m-d H:i:s'),
                                        'end_date' => $individualDiscount->end_date->format('Y-m-d H:i:s'),
                                    ];
                                }
                            } else {
                                // If no individual discount, try to fetch discount for group/all products
                                $groupDiscount = Promotion::where('type', 'discount')
                                    ->whereDate('start_date', '<=', now())
                                    ->whereDate('end_date', '>=', now())
                                    ->whereHas('discountRules', function ($query) {
                                        $query->where('apply_to', '!=', 'individual');
                                    })
                                    ->whereHas('discountRules.products', function ($query) use ($val) {
                                        $query->where('product_id', $val->product_id);
                                    })
                                    ->with(['discountRules' => function ($query) {
                                        $query->where('apply_to', '!=', 'individual')
                                            ->select('id', 'promotion_id', 'percentage', 'apply_to');
                                    }])
                                    ->first();

                                if ($groupDiscount) {
                                    $discountRule = $groupDiscount->discountRules->first();
                                    if ($discountRule) {
                                        $val->discount = (object) [
                                            'value' => intval($discountRule->percentage),
                                            'apply_to' => $discountRule->apply_to,
                                            'discount_type' => 'percent',
                                            'product_price' => null,
                                            'discount_amount' => null,
                                            'final_price' => null,
                                            'start_date' => $groupDiscount->start_date->format('Y-m-d H:i:s'),
                                            'end_date' => $groupDiscount->end_date->format('Y-m-d H:i:s'),
                                        ];
                                    }
                                }
                            }

                            // Fetch active coupons for the product
                            $coupons = Promotion::where('type', 'coupon')
                                ->whereDate('start_date', '<=', now())
                                ->whereDate('end_date', '>=', now())
                                ->whereHas('couponRules.products', function ($query) use ($val) {
                                    $query->where('product_id', $val->product_id);
                                })
                                ->with(['couponRules' => function ($query) use ($val) {
                                    $query->whereNotNull('coupon_code')
                                        ->select('id', 'promotion_id', 'coupon_code', 'percentage')
                                        ->with(['products' => function ($subQuery) use ($val) {
                                            $subQuery->where('product_id', $val->product_id)
                                                    ->select('id', 'coupon_rule_id', 'product_id');
                                        }]);
                                }])
                                ->get();

                            $val->coupon = [];
                            foreach ($coupons as $promotion) {
                                foreach ($promotion->couponRules as $couponRule) {
                                    if ($couponRule->coupon_code && $couponRule->products->isNotEmpty()) {
                                        $val->coupon[strtolower($couponRule->coupon_code)] = [
                                            'code' => strtolower($couponRule->coupon_code),
                                            'value' => intval($couponRule->percentage),
                                            'start_date' => $promotion->start_date->format('Y-m-d H:i:s'),
                                            'end_date' => $promotion->end_date->format('Y-m-d H:i:s'),
                                        ];
                                    }
                                }
                            }
                        }
                } elseif($category == 'GIFT SETS') {
                    $productCategory->products = DB::table('ec_product_category_product')
                        ->select(DB::raw('CAST(ec_products.price AS DECIMAL(8,2)) as price'), 'ec_product_category_product.product_id', 'ec_products.name as product_name', 'ec_products.name_ar as product_name_ar', 'ec_products.image', 'ec_products.images', 'ec_product_collections.name as collection_name', 'ec_products.description', 'ec_products.quantity as product_qty', 'ec_product_labels.name as label_name', 'ec_product_labels.color as label_color', 'ec_products.sale_price')
                        ->join ('ec_product_categories', 'ec_product_category_product.category_id', '=', 'ec_product_categories.id', 'left')
                        ->join ('ec_products', 'ec_product_category_product.product_id', '=', 'ec_products.id', 'left')
                        ->join('ec_product_collection_products', 'ec_product_collection_products.product_id', '=', 'ec_products.id', 'left')
                        ->join('ec_product_collections', 'ec_product_collection_products.product_collection_id', '=', 'ec_product_collections.id', 'left')
                        ->join('ec_product_label_products', 'ec_product_label_products.product_id', '=', 'ec_products.id', 'left')
                        ->join('ec_product_labels', 'ec_product_label_products.product_label_id', '=', 'ec_product_labels.id', 'left')
                        ->where('ec_product_category_product.category_id', 4)
                        ->orderBy('ec_product_category_product.product_id', 'desc')
                        ->get();

                        foreach ($productCategory->products as $key => $val) {
                            $val->permalink = Slug::select('key')->where('reference_id', $val->product_id)->first();
                            $total_sales = OrderProduct::select(DB::raw('SUM(qty) as total_sales'))
                            ->join('ec_orders', 'ec_order_product.order_id', '=', 'ec_orders.id')
                            // ->where('ec_orders.status', 'completed') // Optional: filter by order status
                            ->where('product_id', $val->product_id)
                            ->groupBy('product_id')
                            // ->orderBy('total_sales', 'desc')
                            // ->limit(10) // Optional: limit to top 10
                            ->first();

                            $val->sales = $total_sales ? intval($total_sales->total_sales) : 0;

                            // $val->discount = DiscountProduct::select('value', 'start_date', 'end_date')->where('product_id', $val->product_id)->whereNull('code') ->whereDate('start_date', '<=', now())->whereDate('end_date', '>=', now())->join('ec_discounts', 'ec_discounts.id', '=', 'ec_discount_products.discount_id', 'left')->first();

                            // $coupons = DiscountProduct::select('code', 'value', 'start_date', 'end_date')->where('product_id', $val->product_id)->whereNotNull('code') ->whereDate('start_date', '<=', now())->whereDate('end_date', '>=', now())->join('ec_discounts', 'ec_discounts.id', '=', 'ec_discount_products.discount_id', 'left')->get();
                            // $val->coupon = [];
                            // foreach ($coupons as $coupon) {
                            //     $val->coupon[strtolower($coupon->code)] = [
                            //         'code' => strtolower($coupon->code),
                            //         'value' => $coupon->value,
                            //         'start_date' => $coupon->start_date,
                            //         'end_date' => $coupon->end_date,
                            //     ];
                            // }

                             $val->discount = null;

                            $individualDiscount = Promotion::where('type', 'discount')
                                ->whereDate('start_date', '<=', now())
                                ->whereDate('end_date', '>=', now())
                                ->whereHas('discountRules', function ($query) {
                                    $query->where('apply_to', 'individual');
                                })
                                ->whereHas('discountRules.individualRules', function ($query) use ($val) {
                                    $query->where('product_id', $val->product_id);
                                })
                                ->with(['discountRules' => function ($query) {
                                    $query->where('apply_to', 'individual')
                                        ->select('id', 'promotion_id', 'apply_to');
                                }, 'discountRules.individualRules' => function ($query) use ($val) {
                                    $query->where('product_id', $val->product_id)
                                        ->select('discount_rule_id', 'product_id', 'value', 'discount_type', 'product_price', 'discount_amount', 'final_price');
                                }])
                                ->first();

                            if ($individualDiscount) {
                                $discountRule = $individualDiscount->discountRules->first();
                                $individualRule = $discountRule ? $discountRule->individualRules->first() : null;
                                if ($individualRule) {
                                    $val->discount = (object) [
                                        'value' => intval($individualRule->value),
                                        'apply_to' => $discountRule->apply_to,
                                        'discount_type' => $individualRule->discount_type,
                                        'product_price' => $individualRule->product_price,
                                        'discount_amount' => $individualRule->discount_amount,
                                        'final_price' => $individualRule->final_price,
                                        'start_date' => $individualDiscount->start_date->format('Y-m-d H:i:s'),
                                        'end_date' => $individualDiscount->end_date->format('Y-m-d H:i:s'),
                                    ];
                                }
                            } else {
                                // If no individual discount, try to fetch discount for group/all products
                                $groupDiscount = Promotion::where('type', 'discount')
                                    ->whereDate('start_date', '<=', now())
                                    ->whereDate('end_date', '>=', now())
                                    ->whereHas('discountRules', function ($query) {
                                        $query->where('apply_to', '!=', 'individual');
                                    })
                                    ->whereHas('discountRules.products', function ($query) use ($val) {
                                        $query->where('product_id', $val->product_id);
                                    })
                                    ->with(['discountRules' => function ($query) {
                                        $query->where('apply_to', '!=', 'individual')
                                            ->select('id', 'promotion_id', 'percentage', 'apply_to');
                                    }])
                                    ->first();

                                if ($groupDiscount) {
                                    $discountRule = $groupDiscount->discountRules->first();
                                    if ($discountRule) {
                                        $val->discount = (object) [
                                            'value' => intval($discountRule->percentage),
                                            'apply_to' => $discountRule->apply_to,
                                            'discount_type' => 'percent',
                                            'product_price' => null,
                                            'discount_amount' => null,
                                            'final_price' => null,
                                            'start_date' => $groupDiscount->start_date->format('Y-m-d H:i:s'),
                                            'end_date' => $groupDiscount->end_date->format('Y-m-d H:i:s'),
                                        ];
                                    }
                                }
                            }

                            // Fetch active coupons for the product
                            $coupons = Promotion::where('type', 'coupon')
                                ->whereDate('start_date', '<=', now())
                                ->whereDate('end_date', '>=', now())
                                ->whereHas('couponRules.products', function ($query) use ($val) {
                                    $query->where('product_id', $val->product_id);
                                })
                                ->with(['couponRules' => function ($query) use ($val) {
                                    $query->whereNotNull('coupon_code')
                                        ->select('id', 'promotion_id', 'coupon_code', 'percentage')
                                        ->with(['products' => function ($subQuery) use ($val) {
                                            $subQuery->where('product_id', $val->product_id)
                                                    ->select('id', 'coupon_rule_id', 'product_id');
                                        }]);
                                }])
                                ->get();

                            $val->coupon = [];
                            foreach ($coupons as $promotion) {
                                foreach ($promotion->couponRules as $couponRule) {
                                    if ($couponRule->coupon_code && $couponRule->products->isNotEmpty()) {
                                        $val->coupon[strtolower($couponRule->coupon_code)] = [
                                            'code' => strtolower($couponRule->coupon_code),
                                            'value' => intval($couponRule->percentage),
                                            'start_date' => $promotion->start_date->format('Y-m-d H:i:s'),
                                            'end_date' => $promotion->end_date->format('Y-m-d H:i:s'),
                                        ];
                                    }
                                }
                            }
                        }
                }
                elseif($category == 'ONLINE EXCLUSIVE') {
                    $productCategory->products = DB::table('ec_product_category_product')
                        ->select(DB::raw('CAST(ec_products.price AS DECIMAL(8,2)) as price'), 'ec_product_category_product.product_id', 'ec_products.name as product_name', 'ec_products.name_ar as product_name_ar', 'ec_products.image', 'ec_products.images', 'ec_product_collections.name as collection_name', 'ec_products.description', 'ec_products.quantity as product_qty', 'ec_product_labels.name as label_name', 'ec_product_labels.color as label_color', 'ec_products.sale_price')
                        ->join ('ec_product_categories', 'ec_product_category_product.category_id', '=', 'ec_product_categories.id', 'left')
                        ->join ('ec_products', 'ec_product_category_product.product_id', '=', 'ec_products.id', 'left')
                        ->join('ec_product_collection_products', 'ec_product_collection_products.product_id', '=', 'ec_products.id', 'left')
                        ->join('ec_product_collections', 'ec_product_collection_products.product_collection_id', '=', 'ec_product_collections.id', 'left')
                        ->join('ec_product_label_products', 'ec_product_label_products.product_id', '=', 'ec_products.id', 'left')
                        ->join('ec_product_labels', 'ec_product_label_products.product_label_id', '=', 'ec_product_labels.id', 'left')
                        ->where('ec_product_category_product.category_id', 19)
                        ->orderBy('ec_product_category_product.product_id', 'desc')
                        ->get();

                        foreach ($productCategory->products as $key => $val) {
                            $val->permalink = Slug::select('key')->where('reference_id', $val->product_id)->first();
                            $total_sales = OrderProduct::select(DB::raw('SUM(qty) as total_sales'))
                            ->join('ec_orders', 'ec_order_product.order_id', '=', 'ec_orders.id')
                            // ->where('ec_orders.status', 'completed') // Optional: filter by order status
                            ->where('product_id', $val->product_id)
                            ->groupBy('product_id')
                            // ->orderBy('total_sales', 'desc')
                            // ->limit(10) // Optional: limit to top 10
                            ->first();

                            $val->sales = $total_sales ? intval($total_sales->total_sales) : 0;

                            // $val->discount = DiscountProduct::select('value', 'start_date', 'end_date')->where('product_id', $val->product_id)->whereNull('code') ->whereDate('start_date', '<=', now())->whereDate('end_date', '>=', now())->join('ec_discounts', 'ec_discounts.id', '=', 'ec_discount_products.discount_id', 'left')->first();

                            // $coupons = DiscountProduct::select('code', 'value', 'start_date', 'end_date')->where('product_id', $val->product_id)->whereNotNull('code') ->whereDate('start_date', '<=', now())->whereDate('end_date', '>=', now())->join('ec_discounts', 'ec_discounts.id', '=', 'ec_discount_products.discount_id', 'left')->get();
                            // $val->coupon = [];
                            // foreach ($coupons as $coupon) {
                            //     $val->coupon[strtolower($coupon->code)] = [
                            //         'code' => strtolower($coupon->code),
                            //         'value' => $coupon->value,
                            //         'start_date' => $coupon->start_date,
                            //         'end_date' => $coupon->end_date,
                            //     ];
                            // }
                             $val->discount = null;

                            $individualDiscount = Promotion::where('type', 'discount')
                                ->whereDate('start_date', '<=', now())
                                ->whereDate('end_date', '>=', now())
                                ->whereHas('discountRules', function ($query) {
                                    $query->where('apply_to', 'individual');
                                })
                                ->whereHas('discountRules.individualRules', function ($query) use ($val) {
                                    $query->where('product_id', $val->product_id);
                                })
                                ->with(['discountRules' => function ($query) {
                                    $query->where('apply_to', 'individual')
                                        ->select('id', 'promotion_id', 'apply_to');
                                }, 'discountRules.individualRules' => function ($query) use ($val) {
                                    $query->where('product_id', $val->product_id)
                                        ->select('discount_rule_id', 'product_id', 'value', 'discount_type', 'product_price', 'discount_amount', 'final_price');
                                }])
                                ->first();

                            if ($individualDiscount) {
                                $discountRule = $individualDiscount->discountRules->first();
                                $individualRule = $discountRule ? $discountRule->individualRules->first() : null;
                                if ($individualRule) {
                                    $val->discount = (object) [
                                        'value' => intval($individualRule->value),
                                        'apply_to' => $discountRule->apply_to,
                                        'discount_type' => $individualRule->discount_type,
                                        'product_price' => $individualRule->product_price,
                                        'discount_amount' => $individualRule->discount_amount,
                                        'final_price' => $individualRule->final_price,
                                        'start_date' => $individualDiscount->start_date->format('Y-m-d H:i:s'),
                                        'end_date' => $individualDiscount->end_date->format('Y-m-d H:i:s'),
                                    ];
                                }
                            } else {
                                // If no individual discount, try to fetch discount for group/all products
                                $groupDiscount = Promotion::where('type', 'discount')
                                    ->whereDate('start_date', '<=', now())
                                    ->whereDate('end_date', '>=', now())
                                    ->whereHas('discountRules', function ($query) {
                                        $query->where('apply_to', '!=', 'individual');
                                    })
                                    ->whereHas('discountRules.products', function ($query) use ($val) {
                                        $query->where('product_id', $val->product_id);
                                    })
                                    ->with(['discountRules' => function ($query) {
                                        $query->where('apply_to', '!=', 'individual')
                                            ->select('id', 'promotion_id', 'percentage', 'apply_to');
                                    }])
                                    ->first();

                                if ($groupDiscount) {
                                    $discountRule = $groupDiscount->discountRules->first();
                                    if ($discountRule) {
                                        $val->discount = (object) [
                                            'value' => intval($discountRule->percentage),
                                            'apply_to' => $discountRule->apply_to,
                                            'discount_type' => 'percent',
                                            'product_price' => null,
                                            'discount_amount' => null,
                                            'final_price' => null,
                                            'start_date' => $groupDiscount->start_date->format('Y-m-d H:i:s'),
                                            'end_date' => $groupDiscount->end_date->format('Y-m-d H:i:s'),
                                        ];
                                    }
                                }
                            }

                            // Fetch active coupons for the product
                            $coupons = Promotion::where('type', 'coupon')
                                ->whereDate('start_date', '<=', now())
                                ->whereDate('end_date', '>=', now())
                                ->whereHas('couponRules.products', function ($query) use ($val) {
                                    $query->where('product_id', $val->product_id);
                                })
                                ->with(['couponRules' => function ($query) use ($val) {
                                    $query->whereNotNull('coupon_code')
                                        ->select('id', 'promotion_id', 'coupon_code', 'percentage')
                                        ->with(['products' => function ($subQuery) use ($val) {
                                            $subQuery->where('product_id', $val->product_id)
                                                    ->select('id', 'coupon_rule_id', 'product_id');
                                        }]);
                                }])
                                ->get();

                            $val->coupon = [];
                            foreach ($coupons as $promotion) {
                                foreach ($promotion->couponRules as $couponRule) {
                                    if ($couponRule->coupon_code && $couponRule->products->isNotEmpty()) {
                                        $val->coupon[strtolower($couponRule->coupon_code)] = [
                                            'code' => strtolower($couponRule->coupon_code),
                                            'value' => intval($couponRule->percentage),
                                            'start_date' => $promotion->start_date->format('Y-m-d H:i:s'),
                                            'end_date' => $promotion->end_date->format('Y-m-d H:i:s'),
                                        ];
                                    }
                                }
                            }
                        }
                }
                else {
                    $productCategory->productSubCategories = ProductCategory::select('id', 'name', 'image', 'mobile_image', 'video')->where('parent_id', $productCategory->id)->where('status', 'published')->get();
                    foreach ($productCategory->productSubCategories as $key => $val) {
                        $val->products = DB::table('ec_product_category_product')
                        ->select(DB::raw('CAST(ec_products.price AS DECIMAL(8,2)) as price'), 'ec_product_category_product.product_id', 'ec_products.name as product_name', 'ec_products.name_ar as product_name_ar', 'ec_products.image', 'ec_products.images', 'ec_product_collections.name as collection_name', 'ec_products.description', 'ec_products.quantity as product_qty', 'ec_product_labels.name as label_name', 'ec_product_labels.color as label_color', 'ec_products.sale_price')
                        ->join ('ec_product_categories', 'ec_product_category_product.category_id', '=', 'ec_product_categories.id', 'left')
                        ->join ('ec_products', 'ec_product_category_product.product_id', '=', 'ec_products.id', 'left')
                        ->join('ec_product_collection_products', 'ec_product_collection_products.product_id', '=', 'ec_products.id', 'left')
                        ->join('ec_product_collections', 'ec_product_collection_products.product_collection_id', '=', 'ec_product_collections.id', 'left')
                        ->join('ec_product_label_products', 'ec_product_label_products.product_id', '=', 'ec_products.id', 'left')
                        ->join('ec_product_labels', 'ec_product_label_products.product_label_id', '=', 'ec_product_labels.id', 'left')
                        ->where('ec_product_category_product.category_id', $val->id)
                        ->orderBy('ec_product_category_product.product_id', 'desc')
                        ->get();

                        foreach ($val->products as $k => $v) {
                            $v->permalink = Slug::select('key')->where('reference_id', $v->product_id)->first();
                            $total_sales = OrderProduct::select(DB::raw('SUM(qty) as total_sales'))
                            ->join('ec_orders', 'ec_order_product.order_id', '=', 'ec_orders.id')
                            // ->where('ec_orders.status', 'completed') // Optional: filter by order status
                            ->where('product_id', $v->product_id)
                            ->groupBy('product_id')
                            // ->orderBy('total_sales', 'desc')
                            // ->limit(10) // Optional: limit to top 10
                            ->first();

                            $v->sales = $total_sales ? intval($total_sales->total_sales) : 0;

                            // $v->discount = DiscountProduct::select('value', 'start_date', 'end_date')->where('product_id', $v->product_id)->whereNull('code') ->whereDate('start_date', '<=', now())->whereDate('end_date', '>=', now())->join('ec_discounts', 'ec_discounts.id', '=', 'ec_discount_products.discount_id', 'left')->first();

                            // $coupons = DiscountProduct::select('code', 'value', 'start_date', 'end_date')->where('product_id', $v->product_id)->whereNotNull('code') ->whereDate('start_date', '<=', now())->whereDate('end_date', '>=', now())->join('ec_discounts', 'ec_discounts.id', '=', 'ec_discount_products.discount_id', 'left')->get();
                            // $v->coupon = [];
                            // foreach ($coupons as $coupon) {
                            //     $v->coupon[strtolower($coupon->code)] = [
                            //         'code' => strtolower($coupon->code),
                            //         'value' => $coupon->value,
                            //         'start_date' => $coupon->start_date,
                            //         'end_date' => $coupon->end_date,
                            //     ];
                            // }
                            $v->discount = null;

                            $individualDiscount = Promotion::where('type', 'discount')
                                ->whereDate('start_date', '<=', now())
                                ->whereDate('end_date', '>=', now())
                                ->whereHas('discountRules', function ($query) {
                                    $query->where('apply_to', 'individual');
                                })
                                ->whereHas('discountRules.individualRules', function ($query) use ($v) {
                                    $query->where('product_id', $v->product_id);
                                })
                                ->with(['discountRules' => function ($query) {
                                    $query->where('apply_to', 'individual')
                                        ->select('id', 'promotion_id', 'apply_to');
                                }, 'discountRules.individualRules' => function ($query) use ($v) {
                                    $query->where('product_id', $v->product_id)
                                        ->select('discount_rule_id', 'product_id', 'value', 'discount_type', 'product_price', 'discount_amount', 'final_price');
                                }])
                                ->first();

                            if ($individualDiscount) {
                                $discountRule = $individualDiscount->discountRules->first();
                                $individualRule = $discountRule ? $discountRule->individualRules->first() : null;
                                if ($individualRule) {
                                    $v->discount = (object) [
                                        'value' => intval($individualRule->value),
                                        'apply_to' => $discountRule->apply_to,
                                        'discount_type' => $individualRule->discount_type,
                                        'product_price' => $individualRule->product_price,
                                        'discount_amount' => $individualRule->discount_amount,
                                        'final_price' => $individualRule->final_price,
                                        'start_date' => $individualDiscount->start_date->format('Y-m-d H:i:s'),
                                        'end_date' => $individualDiscount->end_date->format('Y-m-d H:i:s'),
                                    ];
                                }
                            } else {
                                // If no individual discount, try to fetch discount for group/all products
                                $groupDiscount = Promotion::where('type', 'discount')
                                    ->whereDate('start_date', '<=', now())
                                    ->whereDate('end_date', '>=', now())
                                    ->whereHas('discountRules', function ($query) {
                                        $query->where('apply_to', '!=', 'individual');
                                    })
                                    ->whereHas('discountRules.products', function ($query) use ($v) {
                                        $query->where('product_id', $v->product_id);
                                    })
                                    ->with(['discountRules' => function ($query) {
                                        $query->where('apply_to', '!=', 'individual')
                                            ->select('id', 'promotion_id', 'percentage', 'apply_to');
                                    }])
                                    ->first();

                                if ($groupDiscount) {
                                    $discountRule = $groupDiscount->discountRules->first();
                                    if ($discountRule) {
                                        $v->discount = (object) [
                                            'value' => intval($discountRule->percentage),
                                            'apply_to' => $discountRule->apply_to,
                                            'discount_type' => 'percent',
                                            'product_price' => null,
                                            'discount_amount' => null,
                                            'final_price' => null,
                                            'start_date' => $groupDiscount->start_date->format('Y-m-d H:i:s'),
                                            'end_date' => $groupDiscount->end_date->format('Y-m-d H:i:s'),
                                        ];
                                    }
                                }
                            }

                            // $coupons = DiscountProduct::select('code', 'value', 'start_date', 'end_date')->where('product_id', $v->product_id)->whereNotNull('code')->whereDate('start_date', '<=', now())->whereDate('end_date', '>=', now())->join('ec_discounts', 'ec_discounts.id', '=', 'ec_discount_products.discount_id', 'left')->get();
                            // $v->coupon = [];
                            // foreach ($coupons as $coupon) {
                            //     $v->coupon[strtolower($coupon->code)] = [
                            //         'code' => strtolower($coupon->code),
                            //         'value' => $coupon->value,
                            //         'start_date' => $coupon->start_date,
                            //         'end_date' => $coupon->end_date,
                            //     ];
                            // }

                            // Fetch active coupons for the product
                            $coupons = Promotion::where('type', 'coupon')
                                ->whereDate('start_date', '<=', now())
                                ->whereDate('end_date', '>=', now())
                                ->whereHas('couponRules.products', function ($query) use ($v) {
                                    $query->where('product_id', $v->product_id);
                                })
                                ->with(['couponRules' => function ($query) use ($v) {
                                    $query->whereNotNull('coupon_code')
                                        ->select('id', 'promotion_id', 'coupon_code', 'percentage')
                                        ->with(['products' => function ($subQuery) use ($v) {
                                            $subQuery->where('product_id', $v->product_id)
                                                    ->select('id', 'coupon_rule_id', 'product_id');
                                        }]);
                                }])
                                ->get();

                            $v->coupon = [];
                            foreach ($coupons as $promotion) {
                                foreach ($promotion->couponRules as $couponRule) {
                                    if ($couponRule->coupon_code && $couponRule->products->isNotEmpty()) {
                                        $v->coupon[strtolower($couponRule->coupon_code)] = [
                                            'code' => strtolower($couponRule->coupon_code),
                                            'value' => intval($couponRule->percentage),
                                            'start_date' => $promotion->start_date->format('Y-m-d H:i:s'),
                                            'end_date' => $promotion->end_date->format('Y-m-d H:i:s'),
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                $productCategory->products = DB::table('ec_product_category_product')
                ->select(DB::raw('CAST(ec_products.price AS DECIMAL(8,2)) as price'), 'ec_product_category_product.product_id', 'ec_products.name as product_name', 'ec_products.name_ar as product_name_ar', 'ec_products.image', 'ec_products.images', 'ec_product_collections.name as collection_name', 'ec_products.description', 'ec_products.quantity as product_qty', 'ec_product_labels.name as label_name', 'ec_product_labels.color as label_color', 'ec_products.sale_price')
                ->join ('ec_product_categories', 'ec_product_category_product.category_id', '=', 'ec_product_categories.id', 'left')
                ->join ('ec_products', 'ec_product_category_product.product_id', '=', 'ec_products.id', 'left')
                ->join('ec_product_collection_products', 'ec_product_collection_products.product_id', '=', 'ec_products.id', 'left')
                ->join('ec_product_collections', 'ec_product_collection_products.product_collection_id', '=', 'ec_product_collections.id', 'left')
                ->join('ec_product_label_products', 'ec_product_label_products.product_id', '=', 'ec_products.id', 'left')
                ->join('ec_product_labels', 'ec_product_label_products.product_label_id', '=', 'ec_product_labels.id', 'left')
                ->where('ec_product_category_product.category_id', $subCategoryData->id)
                ->orderBy('ec_product_category_product.product_id', 'desc')
                ->get();
                
                foreach ($productCategory->products as $key => $val) {
                    $val->permalink = Slug::select('key')->where('reference_id', $val->product_id)->first();
                    $total_sales = OrderProduct::select(DB::raw('SUM(qty) as total_sales'))
                    ->join('ec_orders', 'ec_order_product.order_id', '=', 'ec_orders.id')
                    // ->where('ec_orders.status', 'completed') // Optional: filter by order status
                    ->where('product_id', $val->product_id)
                    ->groupBy('product_id')
                    // ->orderBy('total_sales', 'desc')
                    // ->limit(10) // Optional: limit to top 10
                    ->first();

                    $val->sales = $total_sales ? intval($total_sales->total_sales) : 0;

                    // $val->discount = DiscountProduct::select('value', 'start_date', 'end_date')->where('product_id', $val->product_id)->whereNull('code') ->whereDate('start_date', '<=', now())->whereDate('end_date', '>=', now())->join('ec_discounts', 'ec_discounts.id', '=', 'ec_discount_products.discount_id', 'left')->first();

                    // $coupons = DiscountProduct::select('code', 'value', 'start_date', 'end_date')->where('product_id', $val->product_id)->whereNotNull('code') ->whereDate('start_date', '<=', now())->whereDate('end_date', '>=', now())->join('ec_discounts', 'ec_discounts.id', '=', 'ec_discount_products.discount_id', 'left')->get();
                    // $val->coupon = [];
                    // foreach ($coupons as $coupon) {
                    //     $val->coupon[strtolower($coupon->code)] = [
                    //         'code' => strtolower($coupon->code),
                    //         'value' => $coupon->value,
                    //         'start_date' => $coupon->start_date,
                    //         'end_date' => $coupon->end_date,
                    //     ];
                    // }
                     $val->discount = null;

                            $individualDiscount = Promotion::where('type', 'discount')
                                ->whereDate('start_date', '<=', now())
                                ->whereDate('end_date', '>=', now())
                                ->whereHas('discountRules', function ($query) {
                                    $query->where('apply_to', 'individual');
                                })
                                ->whereHas('discountRules.individualRules', function ($query) use ($val) {
                                    $query->where('product_id', $val->product_id);
                                })
                                ->with(['discountRules' => function ($query) {
                                    $query->where('apply_to', 'individual')
                                        ->select('id', 'promotion_id', 'apply_to');
                                }, 'discountRules.individualRules' => function ($query) use ($val) {
                                    $query->where('product_id', $val->product_id)
                                        ->select('discount_rule_id', 'product_id', 'value', 'discount_type', 'product_price', 'discount_amount', 'final_price');
                                }])
                                ->first();

                            if ($individualDiscount) {
                                $discountRule = $individualDiscount->discountRules->first();
                                $individualRule = $discountRule ? $discountRule->individualRules->first() : null;
                                if ($individualRule) {
                                    $val->discount = (object) [
                                        'value' => intval($individualRule->value),
                                        'apply_to' => $discountRule->apply_to,
                                        'discount_type' => $individualRule->discount_type,
                                        'product_price' => $individualRule->product_price,
                                        'discount_amount' => $individualRule->discount_amount,
                                        'final_price' => $individualRule->final_price,
                                        'start_date' => $individualDiscount->start_date->format('Y-m-d H:i:s'),
                                        'end_date' => $individualDiscount->end_date->format('Y-m-d H:i:s'),
                                    ];
                                }
                            } else {
                                // If no individual discount, try to fetch discount for group/all products
                                $groupDiscount = Promotion::where('type', 'discount')
                                    ->whereDate('start_date', '<=', now())
                                    ->whereDate('end_date', '>=', now())
                                    ->whereHas('discountRules', function ($query) {
                                        $query->where('apply_to', '!=', 'individual');
                                    })
                                    ->whereHas('discountRules.products', function ($query) use ($val) {
                                        $query->where('product_id', $val->product_id);
                                    })
                                    ->with(['discountRules' => function ($query) {
                                        $query->where('apply_to', '!=', 'individual')
                                            ->select('id', 'promotion_id', 'percentage', 'apply_to');
                                    }])
                                    ->first();

                                if ($groupDiscount) {
                                    $discountRule = $groupDiscount->discountRules->first();
                                    if ($discountRule) {
                                        $val->discount = (object) [
                                            'value' => intval($discountRule->percentage),
                                            'apply_to' => $discountRule->apply_to,
                                            'discount_type' => 'percent',
                                            'product_price' => null,
                                            'discount_amount' => null,
                                            'final_price' => null,
                                            'start_date' => $groupDiscount->start_date->format('Y-m-d H:i:s'),
                                            'end_date' => $groupDiscount->end_date->format('Y-m-d H:i:s'),
                                        ];
                                    }
                                }
                            }

                            // Fetch active coupons for the product
                            $coupons = Promotion::where('type', 'coupon')
                                ->whereDate('start_date', '<=', now())
                                ->whereDate('end_date', '>=', now())
                                ->whereHas('couponRules.products', function ($query) use ($val) {
                                    $query->where('product_id', $val->product_id);
                                })
                                ->with(['couponRules' => function ($query) use ($val) {
                                    $query->whereNotNull('coupon_code')
                                        ->select('id', 'promotion_id', 'coupon_code', 'percentage')
                                        ->with(['products' => function ($subQuery) use ($val) {
                                            $subQuery->where('product_id', $val->product_id)
                                                    ->select('id', 'coupon_rule_id', 'product_id');
                                        }]);
                                }])
                                ->get();

                            $val->coupon = [];
                            foreach ($coupons as $promotion) {
                                foreach ($promotion->couponRules as $couponRule) {
                                    if ($couponRule->coupon_code && $couponRule->products->isNotEmpty()) {
                                        $val->coupon[strtolower($couponRule->coupon_code)] = [
                                            'code' => strtolower($couponRule->coupon_code),
                                            'value' => intval($couponRule->percentage),
                                            'start_date' => $promotion->start_date->format('Y-m-d H:i:s'),
                                            'end_date' => $promotion->end_date->format('Y-m-d H:i:s'),
                                        ];
                                    }
                                }
                            }
                }
            }
            return response()->json($productCategory);
        } else {
            // $prod = DB::table('ec_product_category_product')
            // ->select('ec_product_category_product.product_id', 'ec_products.name as product_name', 'ec_products.price', 'ec_products.image', 'ec_products.images', 'ec_product_collections.name as collection_name', 'ec_products.description', 'ec_products.quantity as product_qty', 'ec_product_labels.name as label_name', 'ec_product_labels.color as label_color')
            // ->join ('ec_products', 'ec_product_category_product.product_id', '=', 'ec_products.id', 'left')
            // ->join('ec_product_collection_products', 'ec_product_collection_products.product_id', '=', 'ec_products.id', 'left')
            // ->join('ec_product_collections', 'ec_product_collection_products.product_collection_id', '=', 'ec_product_collections.id', 'left')
            // ->join('ec_product_label_products', 'ec_product_label_products.product_id', '=', 'ec_products.id', 'left')
            // ->join('ec_product_labels', 'ec_product_label_products.product_label_id', '=', 'ec_product_labels.id', 'left')
            // ->where('ec_products.name', $product)
            // ->where('ec_products.status', 'published')
            // ->first();
            // $prod = $cleanedData = DB::table(DB::raw("(SELECT TRIM( REGEXP_REPLACE( REGEXP_REPLACE( REGEXP_REPLACE(ec.name, 'amp;', ''), '&' , ' ' , '[^a-zA-Z0-9 -]', ' '), '\\s+', ' ' ) ) AS cleaned_column, ec_products.id, ec_products.name, ec_products.price, ec_products.image, ec_products.images, ec_products.description, ec_products.quantity, ec_products.status FROM ec_products) AS cleaned_data"))
            //     ->join ('ec_product_category_product', 'ec_product_category_product.product_id', '=', 'cleaned_data.id', 'left')
            //     ->join('ec_product_collection_products', 'ec_product_collection_products.product_id', '=', 'cleaned_data.id', 'left')
            //     ->join('ec_product_collections', 'ec_product_collection_products.product_collection_id', '=', 'ec_product_collections.id', 'left')
            //     ->join('ec_product_label_products', 'ec_product_label_products.product_id', '=', 'cleaned_data.id', 'left')
            //     ->join('ec_product_labels', 'ec_product_label_products.product_label_id', '=', 'ec_product_labels.id', 'left')
            //     ->select('ec_product_category_product.product_id', 'cleaned_data.name as product_name', 'cleaned_data.price', 'cleaned_data.image', 'cleaned_data.images', 'ec_product_collections.name as collection_name', 'cleaned_data.description', 'cleaned_data.quantity as product_qty', 'ec_product_labels.name as label_name', 'ec_product_labels.color as label_color')
            //     ->where('cleaned_column', $product)
            //     ->where('cleaned_data.status', 'published')
            //     // ->groupBy('cleaned_data.id')
            //     ->first();
            // , 'ec_products.content as content'
            // , 'ec_products.fragrance_notes as fragrance_notes'
            $prod =  DB::table('ec_products')
                ->join ('ec_product_category_product', 'ec_product_category_product.product_id', '=', 'ec_products.id', 'left')
                ->join('ec_product_collection_products', 'ec_product_collection_products.product_id', '=', 'ec_products.id', 'left')
                ->join('ec_product_collections', 'ec_product_collection_products.product_collection_id', '=', 'ec_product_collections.id', 'left')
                ->join('ec_product_label_products', 'ec_product_label_products.product_id', '=', 'ec_products.id', 'left')
                ->join('ec_product_labels', 'ec_product_label_products.product_label_id', '=', 'ec_product_labels.id', 'left')
                ->join ('ec_product_categories', 'ec_product_categories.id', '=', 'ec_product_category_product.category_id', 'left')
                ->join('product_fragrance_map', 'ec_products.id', '=', 'product_fragrance_map.product_id', 'left')
                ->join('product_fragrance_notes', 'product_fragrance_map.fragrance_note_id', '=', 'product_fragrance_notes.id', 'left')
                // ->select(DB::raw("REGEXP_REPLACE(REPLACE(REPLACE(ec_products.name, ' &amp; ', '&'), '&', ' '),'[^a-zA-Z0-9-]', '')"))
                ->select(DB::raw('CAST(ec_products.price AS DECIMAL(8,2)) as price'), 'ec_product_category_product.product_id', 'ec_products.name as product_name', 'ec_products.name_ar as product_name_ar', 'ec_products.image', 'ec_products.images', 'ec_product_collections.name as collection_name', 'ec_products.description', 'ec_products.description_ar', 'ec_products.content_ar', 'ec_products.seo_content', 'ec_products.seo_content_ar','ec_products.quantity as product_qty', 'ec_products.video_media as video', 'ec_product_labels.name as label_name', 'ec_product_labels.color as label_color', 'ec_products.sale_price', 'ec_products.sku', 'ec_products.sillage', 'ec_products.longevity', 'ec_products.how_to_use', 'ec_products.occasion', 'ec_products.item_profile', 'ec_products.item_classification', 'ec_products.ingredients', 'ec_products.olfactory_family', 'ec_products.fragrance_type', 'ec_products.fragrance_category', 'ec_products.dispenser_type', 'ec_products.additional_details', 'ec_products.story','ec_products.itemCategory_1', 'ec_products.itemCategory_2', 'ec_products.itemCategory_3', 'ec_products.itemCategory_4', 'ec_products.itemCategory_5', 'ec_products.is_collection', 'ec_products.product_family', 'product_fragrance_notes.itemFamily', 'product_fragrance_notes.top_note', 'product_fragrance_notes.top_note_ar', 'product_fragrance_notes.top_note_image', 'product_fragrance_notes.top_note_description', 'product_fragrance_notes.top_note_description_ar', 'product_fragrance_notes.heart_note', 'product_fragrance_notes.heart_note_ar', 'product_fragrance_notes.heart_note_image', 'product_fragrance_notes.heart_note_description', 'product_fragrance_notes.heart_note_description_ar', 'product_fragrance_notes.base_note', 'product_fragrance_notes.base_note_ar', 'product_fragrance_notes.base_note_image', 'product_fragrance_notes.base_note_description', 'product_fragrance_notes.base_note_description_ar')
                ->where('ec_products.status', 'published')
                ->where(DB::raw("REGEXP_REPLACE(REPLACE(REPLACE(ec_products.name, '&amp;', '&'), '&', ' '),'[^a-zA-Z0-9]', '')"), '=', implode('', explode(' ', $product)))
                ->where('ec_product_categories.name', $category)
                ->orderBy('ec_products.id', 'desc')
                ->first();

                if ($prod && $prod->is_collection) {
                    $collectionItems = DB::table('ec_collection_items')->where('collection_product_id', $prod->product_id)->orderBy('sort_order', 'asc')->get();
                    
                    $childProductIds = $collectionItems->pluck('child_product_id')->filter()->unique()->all();

                    $childProductsData = [];
                    if (!empty($childProductIds)) {
                        $childProductsData = DB::table('ec_products')->whereIn('id', $childProductIds)->select('id', 'name', 'name_ar', 'price', 'image', 'images' )->get()->keyBy('id');
                    }

                    // Step 4: Combine and Format the data.
                    $prod->collection_items = $collectionItems->map(function ($item) use ($childProductsData) {
                        if ($item->child_product_id && isset($childProductsData[$item->child_product_id])) {
                            $fullProductData = $childProductsData[$item->child_product_id];
                            
                            // Merge the collection pivot data (sort_order, etc) with the actual product data
                            return (object) array_merge((array)$item, (array)$fullProductData);
                        }

                        return $item;
                    });

                } elseif ($prod) {
                    $prod->collection_items = [];
                }

                // print_r($prod);die();
                $dynamicDescriptionKey = preg_replace('/[^a-zA-Z0-9\s]/', '', $prod->product_name).' Description';
                $wordsToRemove = ['&', ' &', '& ', ' & ', 'amp', ' amp', 'amp ', ' amp ', ';', ' ;', '; ', ' ; '];
                $cleanDescriptionString = preg_replace('/\s+/', ' ', str_ireplace($wordsToRemove, '', $dynamicDescriptionKey));
                $prod->$cleanDescriptionString = $cleanDescriptionString;

                $dynamicContentKey = preg_replace('/[^a-zA-Z0-9\s]/', '', $prod->product_name).' Content';
                $cleanContentString = preg_replace('/\s+/', ' ', str_ireplace($wordsToRemove, '', $dynamicContentKey));
                $prod->$cleanContentString = $cleanContentString;

                $dynamicNotesKey = preg_replace('/[^a-zA-Z0-9\s]/', '', $prod->product_name).' Notes';
                $cleanNotesString = preg_replace('/\s+/', ' ', str_ireplace($wordsToRemove, '', $dynamicNotesKey));
                $prod->$cleanNotesString = $cleanNotesString;

                $prod->related_prods = DB::table('ec_product_category_product')
                ->select(DB::raw('CAST(ec_products.price AS DECIMAL(8,2)) as price'), 'ec_product_category_product.product_id', 'ec_products.name as product_name', 'ec_product_categories.name as category_name', 'ec_products.image', 'ec_products.images', 'ec_product_collections.name as collection_name', 'ec_products.description', 'ec_products.quantity as product_qty', 'ec_product_labels.name as label_name', 'ec_product_labels.color as label_color', 'ec_products.sale_price')
                ->join ('ec_product_categories', 'ec_product_category_product.category_id', '=', 'ec_product_categories.id', 'left')
                ->join ('ec_products', 'ec_product_category_product.product_id', '=', 'ec_products.id', 'left')
                ->join ('ec_product_related_relations', 'ec_product_related_relations.to_product_id', '=', 'ec_products.id', 'left')
                ->join('ec_product_collection_products', 'ec_product_collection_products.product_id', '=', 'ec_products.id', 'left')
                ->join('ec_product_collections', 'ec_product_collection_products.product_collection_id', '=', 'ec_product_collections.id', 'left')
                ->join('ec_product_label_products', 'ec_product_label_products.product_id', '=', 'ec_products.id', 'left')
                ->join('ec_product_labels', 'ec_product_label_products.product_label_id', '=', 'ec_product_labels.id', 'left')
                ->where('ec_product_categories.status', 'published')
                ->where('ec_product_collections.name', NULL)
                ->where('ec_product_categories.parent_id', 0)
                ->where('ec_product_related_relations.from_product_id', $prod->product_id)
                // ->paginate($limit);
                ->get();

                // $prod->discount = DiscountProduct::select('value', 'start_date', 'end_date')->where('product_id', $prod->product_id)->whereNull('code') ->whereDate('start_date', '<=', now())->whereDate('end_date', '>=', now())->join('ec_discounts', 'ec_discounts.id', '=', 'ec_discount_products.discount_id', 'left')->first();

                // $coupons = DiscountProduct::select('code', 'value', 'start_date', 'end_date')->where('product_id', $prod->product_id)->whereNotNull('code') ->whereDate('start_date', '<=', now())->whereDate('end_date', '>=', now())->join('ec_discounts', 'ec_discounts.id', '=', 'ec_discount_products.discount_id', 'left')->get();
                // $prod->coupon = [];
                // foreach ($coupons as $coupon) {
                //     $prod->coupon[strtolower($coupon->code)] = [
                //         'code' => strtolower($coupon->code),
                //         'value' => $coupon->value,
                //         'start_date' => $coupon->start_date,
                //         'end_date' => $coupon->end_date,
                //     ];
                // }
                $prod->discount = null;

                            $individualDiscount = Promotion::where('type', 'discount')
                    ->whereDate('start_date', '<=', now())
                    ->whereDate('end_date', '>=', now())
                    ->whereHas('discountRules', function ($query) {
                        $query->where('apply_to', 'individual');
                    })
                    ->whereHas('discountRules.individualRules', function ($query) use ($prod) {
                        $query->where('product_id', $prod->product_id);
                    })
                    ->with(['discountRules' => function ($query) {
                        $query->where('apply_to', 'individual')
                            ->select('id', 'promotion_id', 'apply_to');
                    }, 'discountRules.individualRules' => function ($query) use ($prod) {
                        $query->where('product_id', $prod->product_id)
                            ->select('discount_rule_id', 'product_id', 'value', 'discount_type', 'product_price', 'discount_amount', 'final_price');
                    }])
                    ->first();

                             if ($individualDiscount) {
                    $discountRule = $individualDiscount->discountRules->first();
                    $individualRule = $discountRule ? $discountRule->individualRules->first() : null;
                    if ($individualRule) {
                        $prod->discount = (object) [
                            'value' => intval($individualRule->value),
                            'apply_to' => $discountRule->apply_to,
                            'discount_type' => $individualRule->discount_type,
                            'product_price' => $individualRule->product_price,
                            'discount_amount' => $individualRule->discount_amount,
                            'final_price' => $individualRule->final_price,
                            'start_date' => $individualDiscount->start_date->format('Y-m-d H:i:s'),
                            'end_date' => $individualDiscount->end_date->format('Y-m-d H:i:s'),
                        ];
                    }
                } 
                else {
                    // If no individual discount, try to fetch discount for group/all products
                    $groupDiscount = Promotion::where('type', 'discount')
                        ->whereDate('start_date', '<=', now())
                        ->whereDate('end_date', '>=', now())
                        ->whereHas('discountRules', function ($query) {
                            $query->where('apply_to', '!=', 'individual');
                        })
                        ->whereHas('discountRules.products', function ($query) use ($prod) {
                            $query->where('product_id', $prod->product_id);
                        })
                        ->with(['discountRules' => function ($query) {
                            $query->where('apply_to', '!=', 'individual')
                                ->select('id', 'promotion_id', 'percentage', 'apply_to');
                        }])
                        ->first();

                    if ($groupDiscount) {
                        $discountRule = $groupDiscount->discountRules->first();
                        if ($discountRule) {
                            $prod->discount = (object) [
                                'value' => intval($discountRule->percentage),
                                'apply_to' => $discountRule->apply_to,
                                'discount_type' => 'percent',
                                'product_price' => null,
                                'discount_amount' => null,
                                'final_price' => null,
                                'start_date' => $groupDiscount->start_date->format('Y-m-d H:i:s'),
                                'end_date' => $groupDiscount->end_date->format('Y-m-d H:i:s'),
                            ];
                        }
                    }
                }

                            // Fetch active coupons for the product
                           $coupons = Promotion::where('type', 'coupon')
                    ->whereDate('start_date', '<=', now())
                    ->whereDate('end_date', '>=', now())
                    ->whereHas('couponRules.products', function ($query) use ($prod) {
                        $query->where('product_id', $prod->product_id);
                    })
                    ->with(['couponRules' => function ($query) use ($prod) {
                        $query->whereNotNull('coupon_code')
                            ->select('id', 'promotion_id', 'coupon_code', 'percentage')
                            ->with(['products' => function ($subQuery) use ($prod) {
                                $subQuery->where('product_id', $prod->product_id)
                                        ->select('id', 'coupon_rule_id', 'product_id');
                            }]);
                    }])
                    ->get();

                $prod->coupon = [];
                foreach ($coupons as $promotion) {
                    foreach ($promotion->couponRules as $couponRule) {
                        if ($couponRule->coupon_code && $couponRule->products->isNotEmpty()) {
                            $prod->coupon[strtolower($couponRule->coupon_code)] = [
                                'code' => strtolower($couponRule->coupon_code),
                                'value' => intval($couponRule->percentage),
                                'start_date' => $promotion->start_date->format('Y-m-d H:i:s'),
                                'end_date' => $promotion->end_date->format('Y-m-d H:i:s'),
                            ];
                        }
                    }
                }

                foreach ($prod->related_prods as $key => $val) {
                    $val->subcategory = DB::table('ec_product_categories')
                    ->select('name as subcategory_name')
                    ->join ('ec_product_category_product', 'ec_product_category_product.category_id', '=', 'ec_product_categories.id', 'left')
                    ->where('product_id', $val->product_id)
                    ->where('ec_product_categories.parent_id', '!=', 0)
                    ->first();

                    // $val->discount = DiscountProduct::select('value', 'start_date', 'end_date')->where('product_id', $val->product_id)->whereNull('code')->whereDate('start_date', '<=', now())->whereDate('end_date', '>=', now())->join('ec_discounts', 'ec_discounts.id', '=', 'ec_discount_products.discount_id', 'left')->first();

                    // $coupons = DiscountProduct::select('code', 'value', 'start_date', 'end_date')->where('product_id', $val->product_id)->whereNotNull('code')->whereDate('start_date', '<=', now())->whereDate('end_date', '>=', now())->join('ec_discounts', 'ec_discounts.id', '=', 'ec_discount_products.discount_id', 'left')->get();
                    // $val->coupon = [];
                    // foreach ($coupons as $coupon) {
                    //     $val->coupon[strtolower($coupon->code)] = [
                    //         'code' => strtolower($coupon->code),
                    //         'value' => $coupon->value,
                    //         'start_date' => $coupon->start_date,
                    //         'end_date' => $coupon->end_date,
                    //     ];
                    // }
                     $val->discount = null;

                            $individualDiscount = Promotion::where('type', 'discount')
                                ->whereDate('start_date', '<=', now())
                                ->whereDate('end_date', '>=', now())
                                ->whereHas('discountRules', function ($query) {
                                    $query->where('apply_to', 'individual');
                                })
                                ->whereHas('discountRules.individualRules', function ($query) use ($val) {
                                    $query->where('product_id', $val->product_id);
                                })
                                ->with(['discountRules' => function ($query) {
                                    $query->where('apply_to', 'individual')
                                        ->select('id', 'promotion_id', 'apply_to');
                                }, 'discountRules.individualRules' => function ($query) use ($val) {
                                    $query->where('product_id', $val->product_id)
                                        ->select('discount_rule_id', 'product_id', 'value', 'discount_type', 'product_price', 'discount_amount', 'final_price');
                                }])
                                ->first();

                            if ($individualDiscount) {
                                $discountRule = $individualDiscount->discountRules->first();
                                $individualRule = $discountRule ? $discountRule->individualRules->first() : null;
                                if ($individualRule) {
                                    $val->discount = (object) [
                                        'value' => intval($individualRule->value),
                                        'apply_to' => $discountRule->apply_to,
                                        'discount_type' => $individualRule->discount_type,
                                        'product_price' => $individualRule->product_price,
                                        'discount_amount' => $individualRule->discount_amount,
                                        'final_price' => $individualRule->final_price,
                                        'start_date' => $individualDiscount->start_date->format('Y-m-d H:i:s'),
                                        'end_date' => $individualDiscount->end_date->format('Y-m-d H:i:s'),
                                    ];
                                }
                            } else {
                                // If no individual discount, try to fetch discount for group/all products
                                $groupDiscount = Promotion::where('type', 'discount')
                                    ->whereDate('start_date', '<=', now())
                                    ->whereDate('end_date', '>=', now())
                                    ->whereHas('discountRules', function ($query) {
                                        $query->where('apply_to', '!=', 'individual');
                                    })
                                    ->whereHas('discountRules.products', function ($query) use ($val) {
                                        $query->where('product_id', $val->product_id);
                                    })
                                    ->with(['discountRules' => function ($query) {
                                        $query->where('apply_to', '!=', 'individual')
                                            ->select('id', 'promotion_id', 'percentage', 'apply_to');
                                    }])
                                    ->first();

                                if ($groupDiscount) {
                                    $discountRule = $groupDiscount->discountRules->first();
                                    if ($discountRule) {
                                        $val->discount = (object) [
                                            'value' => intval($discountRule->percentage),
                                            'apply_to' => $discountRule->apply_to,
                                            'discount_type' => 'percent',
                                            'product_price' => null,
                                            'discount_amount' => null,
                                            'final_price' => null,
                                            'start_date' => $groupDiscount->start_date->format('Y-m-d H:i:s'),
                                            'end_date' => $groupDiscount->end_date->format('Y-m-d H:i:s'),
                                        ];
                                    }
                                }
                            }

                            // Fetch active coupons for the product
                            $coupons = Promotion::where('type', 'coupon')
                                ->whereDate('start_date', '<=', now())
                                ->whereDate('end_date', '>=', now())
                                ->whereHas('couponRules.products', function ($query) use ($val) {
                                    $query->where('product_id', $val->product_id);
                                })
                                ->with(['couponRules' => function ($query) use ($val) {
                                    $query->whereNotNull('coupon_code')
                                        ->select('id', 'promotion_id', 'coupon_code', 'percentage')
                                        ->with(['products' => function ($subQuery) use ($val) {
                                            $subQuery->where('product_id', $val->product_id)
                                                    ->select('id', 'coupon_rule_id', 'product_id');
                                        }]);
                                }])
                                ->get();

                            $val->coupon = [];
                            foreach ($coupons as $promotion) {
                                foreach ($promotion->couponRules as $couponRule) {
                                    if ($couponRule->coupon_code && $couponRule->products->isNotEmpty()) {
                                        $val->coupon[strtolower($couponRule->coupon_code)] = [
                                            'code' => strtolower($couponRule->coupon_code),
                                            'value' => intval($couponRule->percentage),
                                            'start_date' => $promotion->start_date->format('Y-m-d H:i:s'),
                                            'end_date' => $promotion->end_date->format('Y-m-d H:i:s'),
                                        ];
                                    }
                                }
                            }
                }

                if (isset($prod->product_family) && !empty($prod->product_family)) {
                    $currentProductFamily = $prod->product_family;
                    $productId = $prod->product_id;

                    $results = DB::table('ec_products')
                        ->select(
                            'ec_products.id as product_id',
                            DB::raw('MAX(ec_products.name) as product_name'),
                            DB::raw('MAX(ec_products.image) as image'),
                            DB::raw('MAX(ec_products.images) as images'),
                            DB::raw('MAX(ec_products.description) as description'),
                            DB::raw('MAX(ec_products.quantity) as product_qty'),
                            DB::raw('CAST(MAX(ec_products.price) AS DECIMAL(10,2)) as price'),
                            DB::raw('CAST(MAX(ec_products.sale_price) AS DECIMAL(10,2)) as sale_price'),
                            DB::raw('GROUP_CONCAT(DISTINCT ec_product_collections.name) as collection_name'),
                            DB::raw('GROUP_CONCAT(DISTINCT main_cat.name) as category_name'),
                            DB::raw('GROUP_CONCAT(DISTINCT sub_cat.name) as subcategory_name'),
                            DB::raw("CONCAT('[', GROUP_CONCAT(DISTINCT JSON_OBJECT('name', ec_product_labels.name, 'color', ec_product_labels.color)), ']') as labels")
                        )
                        ->leftJoin('ec_product_category_product as pivot_main', 'pivot_main.product_id', '=', 'ec_products.id')
                        ->leftJoin('ec_product_categories as main_cat', function ($join) {
                            $join->on('pivot_main.category_id', '=', 'main_cat.id')
                                ->where('main_cat.parent_id', 0);
                        })
                        ->leftJoin('ec_product_category_product as pivot_sub', 'pivot_sub.product_id', '=', 'ec_products.id')
                        ->leftJoin('ec_product_categories as sub_cat', function ($join) {
                            $join->on('pivot_sub.category_id', '=', 'sub_cat.id')
                                ->where('sub_cat.parent_id', '!=', 0);
                        })
                        ->leftJoin('ec_product_collection_products', 'ec_product_collection_products.product_id', '=', 'ec_products.id')
                        ->leftJoin('ec_product_collections', 'ec_product_collection_products.product_collection_id', '=', 'ec_product_collections.id')
                        ->leftJoin('ec_product_label_products', 'ec_product_label_products.product_id', '=', 'ec_products.id')
                        ->leftJoin('ec_product_labels', 'ec_product_label_products.product_label_id', '=', 'ec_product_labels.id')
                        
                        ->where('ec_products.product_family', $currentProductFamily)

                        ->where('ec_products.id', '!=', $productId)
                        ->groupBy('ec_products.id')
                        ->get();

                    $prod->item_family = $results->map(function ($item) {
                        $item->subcategory = $item->subcategory_name ? [
                            'subcategory_name' => $item->subcategory_name,
                        ] : null;
                        unset($item->subcategory_name);
                        $item->labels = json_decode($item->labels);
                        $item->images = json_decode($item->images, true) ?? [];
                        return $item;
                    });

                } else {
                    // If the main product has no family, return an empty array for consistency.
                    $prod->item_family = [];
                }
            return response()->json($prod);
        }
    }


    public function getAllProducts(Request $request)
    {
        $limit = (int)$request['limit'];
        $page = (int)$request['page'];
        $search = implode('', explode(' ', $request['search']));

        if($search == '') {
            // echo "if";
            $prod = DB::table('ec_product_category_product')
                ->select(DB::raw('CAST(ec_products.price AS DECIMAL(8,2)) as price'), 'ec_product_categories.id as category_id', 'ec_product_category_product.product_id', 'ec_products.name as product_name', 'ec_product_categories.name as category_name', 'ec_products.image', 'ec_products.images', 'ec_product_collections.name as collection_name', 'ec_products.description', 'ec_products.quantity as product_qty', 'ec_product_labels.name as label_name', 'ec_product_labels.color as label_color', 'ec_products.sale_price')
                ->join ('ec_product_categories', 'ec_product_category_product.category_id', '=', 'ec_product_categories.id', 'left')
                ->join ('ec_products', 'ec_product_category_product.product_id', '=', 'ec_products.id', 'left')
                ->join('ec_product_collection_products', 'ec_product_collection_products.product_id', '=', 'ec_products.id', 'left')
                ->join('ec_product_collections', 'ec_product_collection_products.product_collection_id', '=', 'ec_product_collections.id', 'left')
                ->join('ec_product_label_products', 'ec_product_label_products.product_id', '=', 'ec_products.id', 'left')
                ->join('ec_product_labels', 'ec_product_label_products.product_label_id', '=', 'ec_product_labels.id', 'left')
                ->where('ec_product_categories.status', 'published')
                ->where('ec_product_collections.name', NULL)
                ->where('ec_product_categories.parent_id', 0)
                // ->orderBy('ec_product_category_product.product_id', 'desc')
                ->paginate($limit);
                // ->get();

            foreach ($prod as $key => $val) {
                $val->subcategory = DB::table('ec_product_categories')
                ->select('name as subcategory_name')
                ->join ('ec_product_category_product', 'ec_product_category_product.category_id', '=', 'ec_product_categories.id', 'left')
                ->where('product_id', $val->product_id)
                ->where('ec_product_categories.parent_id', '!=', 0)
                ->first();

                $total_sales = OrderProduct::select(DB::raw('SUM(qty) as total_sales'))
                ->join('ec_orders', 'ec_order_product.order_id', '=', 'ec_orders.id')
                // ->where('ec_orders.status', 'completed') // Optional: filter by order status
                ->where('product_id', $val->product_id)
                ->groupBy('product_id')
                // ->orderBy('total_sales', 'desc')
                // ->limit(10) // Optional: limit to top 10
                ->first();

                $val->sales = $total_sales ? intval($total_sales->total_sales) : 0;

                // $val->discount = DiscountProduct::select('value', 'start_date', 'end_date')->where('product_id', $val->product_id)->whereNull('code')->whereDate('start_date', '<=', now())->whereDate('end_date', '>=', now())->join('ec_discounts', 'ec_discounts.id', '=', 'ec_discount_products.discount_id', 'left')->first();

                // $coupons = DiscountProduct::select('code', 'value', 'start_date', 'end_date')->where('product_id', $val->product_id)->whereNotNull('code')->whereDate('start_date', '<=', now())->whereDate('end_date', '>=', now())->join('ec_discounts', 'ec_discounts.id', '=', 'ec_discount_products.discount_id', 'left')->get();
                // $val->coupon = [];
                // foreach ($coupons as $coupon) {
                //     $val->coupon[strtolower($coupon->code)] = [
                //         'code' => strtolower($coupon->code),
                //         'value' => $coupon->value,
                //         'start_date' => $coupon->start_date,
                //         'end_date' => $coupon->end_date,
                //     ];
                // }
                 $val->discount = null;

                            $individualDiscount = Promotion::where('type', 'discount')
                                ->whereDate('start_date', '<=', now())
                                ->whereDate('end_date', '>=', now())
                                ->whereHas('discountRules', function ($query) {
                                    $query->where('apply_to', 'individual');
                                })
                                ->whereHas('discountRules.individualRules', function ($query) use ($val) {
                                    $query->where('product_id', $val->product_id);
                                })
                                ->with(['discountRules' => function ($query) {
                                    $query->where('apply_to', 'individual')
                                        ->select('id', 'promotion_id', 'apply_to');
                                }, 'discountRules.individualRules' => function ($query) use ($val) {
                                    $query->where('product_id', $val->product_id)
                                        ->select('discount_rule_id', 'product_id', 'value', 'discount_type', 'product_price', 'discount_amount', 'final_price');
                                }])
                                ->first();

                            if ($individualDiscount) {
                                $discountRule = $individualDiscount->discountRules->first();
                                $individualRule = $discountRule ? $discountRule->individualRules->first() : null;
                                if ($individualRule) {
                                    $val->discount = (object) [
                                        'value' => intval($individualRule->value),
                                        'apply_to' => $discountRule->apply_to,
                                        'discount_type' => $individualRule->discount_type,
                                        'product_price' => $individualRule->product_price,
                                        'discount_amount' => $individualRule->discount_amount,
                                        'final_price' => $individualRule->final_price,
                                        'start_date' => $individualDiscount->start_date->format('Y-m-d H:i:s'),
                                        'end_date' => $individualDiscount->end_date->format('Y-m-d H:i:s'),
                                    ];
                                }
                            } else {
                                // If no individual discount, try to fetch discount for group/all products
                                $groupDiscount = Promotion::where('type', 'discount')
                                    ->whereDate('start_date', '<=', now())
                                    ->whereDate('end_date', '>=', now())
                                    ->whereHas('discountRules', function ($query) {
                                        $query->where('apply_to', '!=', 'individual');
                                    })
                                    ->whereHas('discountRules.products', function ($query) use ($val) {
                                        $query->where('product_id', $val->product_id);
                                    })
                                    ->with(['discountRules' => function ($query) {
                                        $query->where('apply_to', '!=', 'individual')
                                            ->select('id', 'promotion_id', 'percentage', 'apply_to');
                                    }])
                                    ->first();

                                if ($groupDiscount) {
                                    $discountRule = $groupDiscount->discountRules->first();
                                    if ($discountRule) {
                                        $val->discount = (object) [
                                            'value' => intval($discountRule->percentage),
                                            'apply_to' => $discountRule->apply_to,
                                            'discount_type' => 'percent',
                                            'product_price' => null,
                                            'discount_amount' => null,
                                            'final_price' => null,
                                            'start_date' => $groupDiscount->start_date->format('Y-m-d H:i:s'),
                                            'end_date' => $groupDiscount->end_date->format('Y-m-d H:i:s'),
                                        ];
                                    }
                                }
                            }

                            // Fetch active coupons for the product
                            $coupons = Promotion::where('type', 'coupon')
                                ->whereDate('start_date', '<=', now())
                                ->whereDate('end_date', '>=', now())
                                ->whereHas('couponRules.products', function ($query) use ($val) {
                                    $query->where('product_id', $val->product_id);
                                })
                                ->with(['couponRules' => function ($query) use ($val) {
                                    $query->whereNotNull('coupon_code')
                                        ->select('id', 'promotion_id', 'coupon_code', 'percentage')
                                        ->with(['products' => function ($subQuery) use ($val) {
                                            $subQuery->where('product_id', $val->product_id)
                                                    ->select('id', 'coupon_rule_id', 'product_id');
                                        }]);
                                }])
                                ->get();

                            $val->coupon = [];
                            foreach ($coupons as $promotion) {
                                foreach ($promotion->couponRules as $couponRule) {
                                    if ($couponRule->coupon_code && $couponRule->products->isNotEmpty()) {
                                        $val->coupon[strtolower($couponRule->coupon_code)] = [
                                            'code' => strtolower($couponRule->coupon_code),
                                            'value' => intval($couponRule->percentage),
                                            'start_date' => $promotion->start_date->format('Y-m-d H:i:s'),
                                            'end_date' => $promotion->end_date->format('Y-m-d H:i:s'),
                                        ];
                                    }
                                }
                            }
            }
        } else {
            // echo "else";
            $prod = DB::table('ec_product_category_product')
                ->select(DB::raw('CAST(ec_products.price AS DECIMAL(8,2)) as price'), 'ec_product_categories.id as category_id', 'ec_product_category_product.product_id', 'ec_products.name as product_name', 'ec_product_categories.name as category_name', 'ec_products.image', 'ec_products.images', 'ec_product_collections.name as collection_name', 'ec_products.description', 'ec_products.quantity as product_qty', 'ec_product_labels.name as label_name', 'ec_product_labels.color as label_color', 'ec_products.sale_price')
                ->join ('ec_product_categories', 'ec_product_category_product.category_id', '=', 'ec_product_categories.id', 'left')
                ->join ('ec_products', 'ec_product_category_product.product_id', '=', 'ec_products.id', 'left')
                ->join('ec_product_collection_products', 'ec_product_collection_products.product_id', '=', 'ec_products.id', 'left')
                ->join('ec_product_collections', 'ec_product_collection_products.product_collection_id', '=', 'ec_product_collections.id', 'left')
                ->join('ec_product_label_products', 'ec_product_label_products.product_id', '=', 'ec_products.id', 'left')
                ->join('ec_product_labels', 'ec_product_label_products.product_label_id', '=', 'ec_product_labels.id', 'left')
                ->where('ec_product_categories.status', 'published')
                ->where('ec_product_collections.name', NULL)
                ->where('ec_product_categories.parent_id', 0)
                // ->where('ec_products.name', 'LIKE', '%'.$search.'%')
                ->where(DB::raw("REGEXP_REPLACE(REPLACE(REPLACE(ec_products.name, '&amp;', '&'), '&', ' '),'[^a-zA-Z0-9-]', '')"), 'LIKE', '%'.$search.'%')
                ->paginate($limit);
            // ->get();

            foreach ($prod as $key => $val) {
                $val->subcategory = DB::table('ec_product_categories')
                ->select('name as subcategory_name')
                ->join ('ec_product_category_product', 'ec_product_category_product.category_id', '=', 'ec_product_categories.id', 'left')
                ->where('product_id', $val->product_id)
                ->where('ec_product_categories.parent_id', '!=', 0)
                ->first();

                $total_sales = OrderProduct::select(DB::raw('SUM(qty) as total_sales'))
                ->join('ec_orders', 'ec_order_product.order_id', '=', 'ec_orders.id')
                // ->where('ec_orders.status', 'completed') // Optional: filter by order status
                ->where('product_id', $val->product_id)
                ->groupBy('product_id')
                // ->orderBy('total_sales', 'desc')
                // ->limit(10) // Optional: limit to top 10
                ->first();

                $val->sales = $total_sales ? intval($total_sales->total_sales) : 0;

                // $val->discount = DiscountProduct::select('value', 'start_date', 'end_date')->where('product_id', $val->product_id)->whereNull('code')->whereDate('start_date', '<=', now())->whereDate('end_date', '>=', now())->join('ec_discounts', 'ec_discounts.id', '=', 'ec_discount_products.discount_id', 'left')->first();

                // $coupons = DiscountProduct::select('code', 'value', 'start_date', 'end_date')->where('product_id', $val->product_id)->whereNotNull('code')->whereDate('start_date', '<=', now())->whereDate('end_date', '>=', now())->join('ec_discounts', 'ec_discounts.id', '=', 'ec_discount_products.discount_id', 'left')->get();
                // $val->coupon = [];
                // foreach ($coupons as $coupon) {
                //     $val->coupon[strtolower($coupon->code)] = [
                //         'code' => strtolower($coupon->code),
                //         'value' => $coupon->value,
                //         'start_date' => $coupon->start_date,
                //         'end_date' => $coupon->end_date,
                //     ];
                // }
                 $val->discount = null;

                            $individualDiscount = Promotion::where('type', 'discount')
                                ->whereDate('start_date', '<=', now())
                                ->whereDate('end_date', '>=', now())
                                ->whereHas('discountRules', function ($query) {
                                    $query->where('apply_to', 'individual');
                                })
                                ->whereHas('discountRules.individualRules', function ($query) use ($val) {
                                    $query->where('product_id', $val->product_id);
                                })
                                ->with(['discountRules' => function ($query) {
                                    $query->where('apply_to', 'individual')
                                        ->select('id', 'promotion_id', 'apply_to');
                                }, 'discountRules.individualRules' => function ($query) use ($val) {
                                    $query->where('product_id', $val->product_id)
                                        ->select('discount_rule_id', 'product_id', 'value', 'discount_type', 'product_price', 'discount_amount', 'final_price');
                                }])
                                ->first();

                            if ($individualDiscount) {
                                $discountRule = $individualDiscount->discountRules->first();
                                $individualRule = $discountRule ? $discountRule->individualRules->first() : null;
                                if ($individualRule) {
                                    $val->discount = (object) [
                                        'value' => intval($individualRule->value),
                                        'apply_to' => $discountRule->apply_to,
                                        'discount_type' => $individualRule->discount_type,
                                        'product_price' => $individualRule->product_price,
                                        'discount_amount' => $individualRule->discount_amount,
                                        'final_price' => $individualRule->final_price,
                                        'start_date' => $individualDiscount->start_date->format('Y-m-d H:i:s'),
                                        'end_date' => $individualDiscount->end_date->format('Y-m-d H:i:s'),
                                    ];
                                }
                            } else {
                                // If no individual discount, try to fetch discount for group/all products
                                $groupDiscount = Promotion::where('type', 'discount')
                                    ->whereDate('start_date', '<=', now())
                                    ->whereDate('end_date', '>=', now())
                                    ->whereHas('discountRules', function ($query) {
                                        $query->where('apply_to', '!=', 'individual');
                                    })
                                    ->whereHas('discountRules.products', function ($query) use ($val) {
                                        $query->where('product_id', $val->product_id);
                                    })
                                    ->with(['discountRules' => function ($query) {
                                        $query->where('apply_to', '!=', 'individual')
                                            ->select('id', 'promotion_id', 'percentage', 'apply_to');
                                    }])
                                    ->first();

                                if ($groupDiscount) {
                                    $discountRule = $groupDiscount->discountRules->first();
                                    if ($discountRule) {
                                        $val->discount = (object) [
                                            'value' => intval($discountRule->percentage),
                                            'apply_to' => $discountRule->apply_to,
                                            'discount_type' => 'percent',
                                            'product_price' => null,
                                            'discount_amount' => null,
                                            'final_price' => null,
                                            'start_date' => $groupDiscount->start_date->format('Y-m-d H:i:s'),
                                            'end_date' => $groupDiscount->end_date->format('Y-m-d H:i:s'),
                                        ];
                                    }
                                }
                            }

                            // Fetch active coupons for the product
                            $coupons = Promotion::where('type', 'coupon')
                                ->whereDate('start_date', '<=', now())
                                ->whereDate('end_date', '>=', now())
                                ->whereHas('couponRules.products', function ($query) use ($val) {
                                    $query->where('product_id', $val->product_id);
                                })
                                ->with(['couponRules' => function ($query) use ($val) {
                                    $query->whereNotNull('coupon_code')
                                        ->select('id', 'promotion_id', 'coupon_code', 'percentage')
                                        ->with(['products' => function ($subQuery) use ($val) {
                                            $subQuery->where('product_id', $val->product_id)
                                                    ->select('id', 'coupon_rule_id', 'product_id');
                                        }]);
                                }])
                                ->get();

                            $val->coupon = [];
                            foreach ($coupons as $promotion) {
                                foreach ($promotion->couponRules as $couponRule) {
                                    if ($couponRule->coupon_code && $couponRule->products->isNotEmpty()) {
                                        $val->coupon[strtolower($couponRule->coupon_code)] = [
                                            'code' => strtolower($couponRule->coupon_code),
                                            'value' => intval($couponRule->percentage),
                                            'start_date' => $promotion->start_date->format('Y-m-d H:i:s'),
                                            'end_date' => $promotion->end_date->format('Y-m-d H:i:s'),
                                        ];
                                    }
                                }
                            }
            }
        }

        return response()->json($prod);
    }

    public function getExportProducts(Request $request)
    {
        $category_id = $request['category_id'];
        if (!isset($category_id) || empty($category_id)) {
            return response()->json([
                'message'       => 'Kindly Provide Category',
            ]);
        }
        $products = DB::table('ec_product_category_product')
            ->select(DB::raw('CAST(ec_products.price AS DECIMAL(8,2)) as price'), 'ec_product_category_product.product_id', 'ec_products.name as product_name', 'ec_products.image', 'ec_products.images', 'ec_product_collections.name as collection_name', 'ec_products.description', 'ec_products.quantity as product_qty', 'ec_product_labels.name as label_name', 'ec_product_labels.color as label_color', 'ec_products.sale_price')
            ->join ('ec_product_categories', 'ec_product_category_product.category_id', '=', 'ec_product_categories.id', 'left')
            ->join ('ec_products', 'ec_product_category_product.product_id', '=', 'ec_products.id', 'left')
            ->join('ec_product_collection_products', 'ec_product_collection_products.product_id', '=', 'ec_products.id', 'left')
            ->join('ec_product_collections', 'ec_product_collection_products.product_collection_id', '=', 'ec_product_collections.id', 'left')
            ->join('ec_product_label_products', 'ec_product_label_products.product_id', '=', 'ec_products.id', 'left')
            ->join('ec_product_labels', 'ec_product_label_products.product_label_id', '=', 'ec_product_labels.id', 'left')
            ->where('ec_product_category_product.category_id', $category_id)
            ->where('ec_product_collections.name', NULL)
            ->orderBy('ec_product_category_product.product_id', 'desc')
            ->get();

            foreach ($products as $key => $val) {
                // $val->discount = DiscountProduct::select('value', 'start_date', 'end_date')->where('product_id', $val->product_id)->whereNull('code')->whereDate('start_date', '<=', now())->whereDate('end_date', '>=', now())->join('ec_discounts', 'ec_discounts.id', '=', 'ec_discount_products.discount_id', 'left');

                // $coupons = DiscountProduct::select('code', 'value', 'start_date', 'end_date')->where('product_id', $val->product_id)->whereNotNull('code')->whereDate('start_date', '<=', now())->whereDate('end_date', '>=', now())->join('ec_discounts', 'ec_discounts.id', '=', 'ec_discount_products.discount_id', 'left')->get();
                // $val->coupon = [];
                // foreach ($coupons as $coupon) {
                //     $val->coupon[strtolower($coupon->code)] = [
                //         'code' => strtolower($coupon->code),
                //         'value' => $coupon->value,
                //         'start_date' => $coupon->start_date,
                //         'end_date' => $coupon->end_date,
                //     ];
                // }
                 $val->discount = null;

                            $individualDiscount = Promotion::where('type', 'discount')
                                ->whereDate('start_date', '<=', now())
                                ->whereDate('end_date', '>=', now())
                                ->whereHas('discountRules', function ($query) {
                                    $query->where('apply_to', 'individual');
                                })
                                ->whereHas('discountRules.individualRules', function ($query) use ($val) {
                                    $query->where('product_id', $val->product_id);
                                })
                                ->with(['discountRules' => function ($query) {
                                    $query->where('apply_to', 'individual')
                                        ->select('id', 'promotion_id', 'apply_to');
                                }, 'discountRules.individualRules' => function ($query) use ($val) {
                                    $query->where('product_id', $val->product_id)
                                        ->select('discount_rule_id', 'product_id', 'value', 'discount_type', 'product_price', 'discount_amount', 'final_price');
                                }])
                                ->first();

                            if ($individualDiscount) {
                                $discountRule = $individualDiscount->discountRules->first();
                                $individualRule = $discountRule ? $discountRule->individualRules->first() : null;
                                if ($individualRule) {
                                    $val->discount = (object) [
                                        'value' => intval($individualRule->value),
                                        'apply_to' => $discountRule->apply_to,
                                        'discount_type' => $individualRule->discount_type,
                                        'product_price' => $individualRule->product_price,
                                        'discount_amount' => $individualRule->discount_amount,
                                        'final_price' => $individualRule->final_price,
                                        'start_date' => $individualDiscount->start_date->format('Y-m-d H:i:s'),
                                        'end_date' => $individualDiscount->end_date->format('Y-m-d H:i:s'),
                                    ];
                                }
                            } else {
                                // If no individual discount, try to fetch discount for group/all products
                                $groupDiscount = Promotion::where('type', 'discount')
                                    ->whereDate('start_date', '<=', now())
                                    ->whereDate('end_date', '>=', now())
                                    ->whereHas('discountRules', function ($query) {
                                        $query->where('apply_to', '!=', 'individual');
                                    })
                                    ->whereHas('discountRules.products', function ($query) use ($val) {
                                        $query->where('product_id', $val->product_id);
                                    })
                                    ->with(['discountRules' => function ($query) {
                                        $query->where('apply_to', '!=', 'individual')
                                            ->select('id', 'promotion_id', 'percentage', 'apply_to');
                                    }])
                                    ->first();

                                if ($groupDiscount) {
                                    $discountRule = $groupDiscount->discountRules->first();
                                    if ($discountRule) {
                                        $val->discount = (object) [
                                            'value' => intval($discountRule->percentage),
                                            'apply_to' => $discountRule->apply_to,
                                            'discount_type' => 'percent',
                                            'product_price' => null,
                                            'discount_amount' => null,
                                            'final_price' => null,
                                            'start_date' => $groupDiscount->start_date->format('Y-m-d H:i:s'),
                                            'end_date' => $groupDiscount->end_date->format('Y-m-d H:i:s'),
                                        ];
                                    }
                                }
                            }

                            // Fetch active coupons for the product
                            $coupons = Promotion::where('type', 'coupon')
                                ->whereDate('start_date', '<=', now())
                                ->whereDate('end_date', '>=', now())
                                ->whereHas('couponRules.products', function ($query) use ($val) {
                                    $query->where('product_id', $val->product_id);
                                })
                                ->with(['couponRules' => function ($query) use ($val) {
                                    $query->whereNotNull('coupon_code')
                                        ->select('id', 'promotion_id', 'coupon_code', 'percentage')
                                        ->with(['products' => function ($subQuery) use ($val) {
                                            $subQuery->where('product_id', $val->product_id)
                                                    ->select('id', 'coupon_rule_id', 'product_id');
                                        }]);
                                }])
                                ->get();

                            $val->coupon = [];
                            foreach ($coupons as $promotion) {
                                foreach ($promotion->couponRules as $couponRule) {
                                    if ($couponRule->coupon_code && $couponRule->products->isNotEmpty()) {
                                        $val->coupon[strtolower($couponRule->coupon_code)] = [
                                            'code' => strtolower($couponRule->coupon_code),
                                            'value' => intval($couponRule->percentage),
                                            'start_date' => $promotion->start_date->format('Y-m-d H:i:s'),
                                            'end_date' => $promotion->end_date->format('Y-m-d H:i:s'),
                                        ];
                                    }
                                }
                            }
            }
        return response()->json($products);
    }

    public function getProductSEO(Request $request)
    {
        $category = $request['category'];
        $subCategory = $request['subCategory'];
        $product = $request['product'];

        if (!isset($category) || empty($category)) {
            return response()->json([
                'message'       => 'Kindly Provide Category',
            ]);
        }

        $prod =  DB::table('ec_products')
            // ->join ('ec_product_category_product', 'ec_product_category_product.product_id', '=', 'ec_products.id', 'left')
            // ->join('ec_product_collection_products', 'ec_product_collection_products.product_id', '=', 'ec_products.id', 'left')
            // ->join('ec_product_collections', 'ec_product_collection_products.product_collection_id', '=', 'ec_product_collections.id', 'left')
            // ->join('ec_product_label_products', 'ec_product_label_products.product_id', '=', 'ec_products.id', 'left')
            // ->join('ec_product_labels', 'ec_product_label_products.product_label_id', '=', 'ec_product_labels.id', 'left')
            // ->join ('ec_product_categories', 'ec_product_categories.id', '=', 'ec_product_category_product.category_id', 'left')
            ->join ('meta_boxes', 'meta_boxes.reference_id', '=', 'ec_products.id', 'left')
            // ->select(DB::raw("REGEXP_REPLACE(REPLACE(REPLACE(ec_products.name, ' &amp; ', '&'), '&', ' '),'[^a-zA-Z0-9-]', '')"))
            ->select('meta_value')
            ->where('ec_products.status', 'published')
            ->where(DB::raw("REGEXP_REPLACE(REPLACE(REPLACE(ec_products.name, '&amp;', '&'), '&', ' '),'[^a-zA-Z0-9]', '')"), '=', implode('', explode(' ', $product)))
            // ->where('ec_product_categories.name', $category)
            ->where('meta_key', 'seo_meta')
            // ->orderBy('ec_products.id', 'desc')
            ->where('reference_type', 'Botble\Ecommerce\Models\Product')
            ->first();
            // print_r($prod);die();
        return response()->json($prod);
    }
     public function getSearchSuggestions(Request $request)
    {
        // 1. Prepare the request for the internal call
        $request->merge([
            'limit' => 6, 
            'page' => 1,
            'search' => $request->input('keyword') 
        ]);

        // 2. Call the internal method
        $response = $this->getAllProducts($request);
        
        // 3. Get the underlying data from the JsonResponse
        $originalData = $response->getData();
        
        // Laravel's paginate() puts results in a 'data' property
        $items = isset($originalData->data) ? $originalData->data : [];

        // 4. Format for the frontend
        $formatted = collect($items)->map(function($item) {
            // 1. Cleaner helper
            $cleaner = function($str) {
                $str = str_replace('&amp;', '&', $str); // Convert &amp; to & for URL cleaning
                $str = preg_replace('/[^\w\s-]/', '', $str);
                $str = preg_replace('/\s+/', ' ', $str);
                return trim($str);
            };

            // 2. IMAGE FALLBACK LOGIC
            $displayImage = $item->image;
            if (empty($displayImage) && !empty($item->images)) {
                // If 'image' is null, check the JSON 'images' gallery
                $gallery = is_string($item->images) ? json_decode($item->images, true) : $item->images;
                if (is_array($gallery) && count($gallery) > 0) {
                    $displayImage = $gallery[0]; 
                }
            }

            // 3. CATEGORY & SUBCAT LOGIC
            $categorySlug = strtolower(str_replace(' ', '-', $cleaner($item->category_name ?? 'shop')));
            
            // Safely check for subcategory
            $subcatName = null;
            if (isset($item->subcategory) && is_object($item->subcategory)) {
                $subcatName = $item->subcategory->subcategory_name ?? null;
            }

            if (!empty($subcatName)) {
                $subcatSlug = strtolower(str_replace(' ', '-', $cleaner($subcatName)));
            } else {
                // Your specific fallback logic
                $fallbacks = ['gift-sets', 'hair-mist', 'extrait-de-parfum'];
                $subcatSlug = in_array($categorySlug, $fallbacks) ? $categorySlug : "online-exclusive";
            }

            $productSlug = strtolower(str_replace(' ', '-', $cleaner($item->product_name)));

            return [
                'name'     => html_entity_decode($item->product_name), // Fixes &amp; for the UI
                'image'    => $displayImage, 
                'price'    => $item->price,
                'url_path' => "/shop/{$categorySlug}/{$subcatSlug}/{$productSlug}"
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $formatted
        ]);
    }

    public function getProductsLiveStatus(Request $request) {
        $productIds = $request->input('product_ids');

        if (empty($productIds)) {
            $content = $request->getContent(); // Get the raw string body
            $json = json_decode($content, true); // Turn JSON string into Array
            $productIds = $json['product_ids'] ?? null;
        }

        if (empty($productIds) || !is_array($productIds)) {
            return response()->json([], 200);
        }

        // $currency = Currency::select('decimals')->where('is_default', 1)->first();
        // $decimals = $currency->decimals ?? 3;

        $products = DB::table('ec_products')
        ->select('id as product_id', 'quantity as product_qty', DB::raw('CAST(price AS DECIMAL(8,2)) as price'), 'sale_price', 'maximum_order_quantity')
        ->whereIn('id', $productIds)
        ->where('status', 'published')
        ->get();

        foreach ($products as $val) {
            
            // Replicating your controller's logic, but adding 'type_option' 
            // so the frontend knows if it is 'percentage' or 'amount'.
           $val->discount = null;

            // A. Check Individual Discount
            $individualDiscount = Promotion::where('type', 'discount')
                ->whereDate('start_date', '<=', now())
                ->whereDate('end_date', '>=', now())
                ->whereHas('discountRules', function ($query) {
                    $query->where('apply_to', 'individual');
                })
                ->whereHas('discountRules.individualRules', function ($query) use ($val) {
                    $query->where('product_id', $val->product_id);
                })
                ->with(['discountRules.individualRules' => function ($query) use ($val) {
                    $query->where('product_id', $val->product_id)
                        ->select('discount_rule_id', 'product_id', 'value', 'discount_type', 'final_price', 'product_price');
                }])
                ->first();

            if ($individualDiscount) {
                $rule = $individualDiscount->discountRules->first()->individualRules->first();
                if ($rule) {
                    $val->discount = (object) [
                        'value' => intval($rule->value),
                        'discount_type' => $rule->discount_type,
                        'final_price' => $rule->final_price,
                        // 'product_price' => $rule->product_price, // Optional if needed by frontend
                        'start_date' => $individualDiscount->start_date->format('Y-m-d H:i:s'),
                        'end_date' => $individualDiscount->end_date->format('Y-m-d H:i:s'),
                    ];
                }
            }
            else {
                $groupDiscount = Promotion::where('type', 'discount')
                    ->whereDate('start_date', '<=', now())
                    ->whereDate('end_date', '>=', now())
                    ->whereHas('discountRules', function ($query) {
                        $query->where('apply_to', '!=', 'individual');
                    })
                    ->whereHas('discountRules.products', function ($query) use ($val) {
                        $query->where('product_id', $val->product_id);
                    })
                    ->with(['discountRules' => function ($query) {
                        $query->select('id', 'promotion_id', 'percentage', 'apply_to');
                    }])
                    ->first();

                if ($groupDiscount) {
                    $rule = $groupDiscount->discountRules->first();
                    if ($rule) {
                        $val->discount = (object) [
                            'value' => intval($rule->percentage),
                            'discount_type' => 'percent',
                            'start_date' => $groupDiscount->start_date->format('Y-m-d H:i:s'),
                            'end_date' => $groupDiscount->end_date->format('Y-m-d H:i:s'),
                        ];
                    }
                }
            }
        }

        return response()->json($products);
    }
     public function bogoProducts(Request $request)
    {
        try {
            // Fetch active promotions with type 'buy_x_get_y' and join with rules and products
            $promotions = DB::table('promotions')
                ->select(
                    'promotions.id as promotion_id',
                    'promotions.name',
                    'buy_x_get_y_rules.id as rule_id',
                    'buy_x_get_y_rules.buy_quantity',
                    'buy_x_get_y_rules.get_quantity',
                    'buy_x_get_y_products.id as product_rule_id',
                    'buy_x_get_y_products.product_id',
                    'buy_x_get_y_products.type as product_type',
                    'ec_products.name as product_name',
                    'ec_products.price as product_price',
                    'ec_products.image as product_image'
                )
                ->where('promotions.type', 'buy_x_get_y')
                ->where('promotions.start_date', '<=', now())
                ->where('promotions.end_date', '>=', now())
                ->leftJoin('buy_x_get_y_rules', 'promotions.id', '=', 'buy_x_get_y_rules.promotion_id')
                ->leftJoin('buy_x_get_y_products', 'buy_x_get_y_rules.id', '=', 'buy_x_get_y_products.rule_id')
                ->leftJoin('ec_products', 'buy_x_get_y_products.product_id', '=', 'ec_products.id')
                ->get();

            // Handle empty promotions
            if ($promotions->isEmpty()) {
                // \Log::info('No active BOGO promotions found.');
                return response()->json(['bogoProducts' => []], 200);
            }

            // Group promotions by promotion_id and rule_id
            $groupedPromotions = $promotions->groupBy('promotion_id')->map(function ($promoGroup) {
                $firstPromo = $promoGroup->first();
                // Skip if no valid promotion data
                if (!$firstPromo || !isset($firstPromo->name)) {
                    // \Log::warning('Skipping promotion with missing data', ['promoGroup' => $promoGroup]);
                    return [];
                }

                $rules = $promoGroup->groupBy('rule_id')->map(function ($ruleGroup) use ($firstPromo) {
                    $firstRule = $ruleGroup->first();
                    // Skip if no valid rule data
                    if (!$firstRule || !isset($firstRule->rule_id)) {
                        // \Log::warning('Skipping rule with missing data', ['ruleGroup' => $ruleGroup]);
                        return null;
                    }

                    $buyProducts = $ruleGroup->filter(function ($row) {
                        return $row->product_type === 'buy';
                    })->map(function ($row) {
                        return [
                            'product_id' => $row->product_id,
                            'product_name' => $row->product_name ?? 'Unknown',
                            'price' => $row->product_price ?? 0,
                            'image' => $row->product_image ?? '',
                        ];
                    })->values()->toArray();

                    $freeProducts = $ruleGroup->filter(function ($row) {
                        return $row->product_type === 'free';
                    })->map(function ($row) {
                        return [
                            'product_id' => $row->product_id,
                            'product_name' => $row->product_name ?? 'Unknown',
                            'price' => 0,
                            'image' => $row->product_image ?? '',
                            'is_gift' => true,
                            'discount' => null,
                            'coupon' => [],
                            'type' => 'bogo',
                        ];
                    })->values()->toArray();

                    // Handle empty free_products: Copy all buy_products
                    // if (empty($freeProducts) && !empty($buyProducts)) {
                    //     $freeProducts = collect($buyProducts)->map(function ($product) {
                    //         return [
                    //             'product_id' => $product['product_id'],
                    //             'product_name' => $product['product_name'],
                    //             'price' => 0,
                    //             'image' => $product['image'],
                    //             'is_gift' => true,
                    //             'discount' => null,
                    //             'coupon' => [],
                    //         ];
                    //     })->values()->toArray();
                    // }

                    return [
                        'id' => $firstRule->rule_id,
                        'name' => $firstPromo->name,
                        'buy_quantity' => $firstRule->buy_quantity ?? 1,
                        'get_quantity' => $firstRule->get_quantity ?? 1,
                        'buy_products' => $buyProducts,
                        'free_products' => $freeProducts,
                        'selection_rule' => $this->determineSelectionRule($firstRule, $firstPromo->name, !empty($freeProducts)),
                        'campaign' => $firstPromo->name
                            ? str_replace(' ', '_', strtolower($firstPromo->name)) . '_2025_campaign'
                            : 'default_campaign_' . $firstRule->rule_id,
                    ];
                })->filter()->values()->toArray();

                return $rules;
            })->flatten(1)->toArray();

            return response()->json([
                'bogoProducts' => $groupedPromotions,
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error fetching BOGO products: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'error' => 'Failed to fetch BOGO products',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
     public function freeGiftProducts(Request $request)
    {
        $thresholds = DB::table('foc_rules')->where('type', 'foc')->where('start_date', '<=', now())->where('end_date', '>=', now())->join('promotions', 'promotions.id', '=', 'foc_rules.promotion_id')->select('name', 'foc_rules.id', 'min_threshold AS min', 'max_threshold As max')->orderBy('min', 'asc')->get();

        if($thresholds->isEmpty()) {
            return response()->json(['thresholds' => []])->header('Cache-Control', 'public, max-age=0, s-maxage=0')->setEtag(md5(json_encode(['thresholds' => []])));  // Cache 1 Day in the browser, 2 Days at Cloudflare
        }
        foreach ($thresholds as $threshold) {
            $giftData = [];
            $gifts = DB::table('foc_products')->where('foc_rule_id', $threshold->id)->join('ec_products', 'ec_products.id', '=', 'foc_products.product_id')->select('foc_products.product_id', 'ec_products.name', 'ec_products.price', 'ec_products.images')->get();
            foreach ($gifts as $gift) {
                $decodedOnce = is_string($gift->images) ? json_decode($gift->images, true) : $gift->images;

                if (is_string($decodedOnce)) {
                    $images = json_decode($decodedOnce, true);
                } elseif (is_array($decodedOnce)) {
                    $images = $decodedOnce;
                } else {
                    $images = [];
                }

                $firstImage = $images[0] ?? null;
                
                $giftData[] = [
                    'product_id' => $gift->product_id,
                    'product_name' => $gift->name,
                    'price' => 0,
                    'image' => $firstImage,
                    'is_gift' => true,
                    'discount' => null,
                    'coupon' => [],
                    'campaign' => strtolower(str_replace(' ', '_', $threshold->name)).'_'.now()->year.'_campaign',
                    'type' => 'foc',
                ];
            }
            $threshold->gifts = $giftData;
        }

        $response = response()->json(['thresholds' => $thresholds])->header('Cache-Control', 'public, max-age=0, s-maxage=0')->setEtag(md5(json_encode(['thresholds' => $thresholds])));  // Cache 1 Day in the browser, 2 Days at Cloudflare

        if ($response->isNotModified(request())) {
            return $response;
        }

        return $response;
    }
    private function determineSelectionRule($rule, $promotionName, $hasFreeProducts)
    {
        $buyQty = $rule->buy_quantity ?? 1;
        $getQty = $rule->get_quantity ?? 1;

        if ($buyQty == 1 && $getQty == 1) {
            return 'same_product';
        } elseif ($buyQty == 2 && $getQty == 2) {
            return 'least_expensive';
        } elseif ($buyQty == 3 && $getQty == 2) {
            return $hasFreeProducts ? 'customer_select' : 'least_expensive';
        } elseif ($buyQty > 1 && $getQty == 1) {
            return 'auto_add';
        }

        return 'least_expensive';
    }

    public function specialOffers(Request $request)
    {
        try {
            $now = \Carbon\Carbon::now();

            // Fetch active promotions that have not been deleted and are currently active
            $activePromotions = Promotion::where('isDeleted', false)
                ->where('start_date', '<=', $now)
                ->where('end_date', '>=', $now)
                ->with([
                    'discountRules.individualRules',
                    'discountRules.products',
                    'buyXGetYRules.products',
                    'focRules.products',
                ])
                ->orderBy('start_date', 'desc')
                ->get();

            $discountPromotions = [];
            $buyXGetYPromotions = [];

            foreach ($activePromotions as $promotion) {
                if ($promotion->type === 'discount') {
                    $productIds = [];
                    $individualDiscountMap = [];
                    $groupPercentage = null;

                    if ($promotion->discountRules) {
                        foreach ($promotion->discountRules as $rule) {
                            if ($rule->apply_to === 'individual') {
                                if ($rule->individualRules) {
                                    foreach ($rule->individualRules as $ind) {
                                        $productIds[] = $ind->product_id;
                                        $individualDiscountMap[$ind->product_id] = $ind;
                                    }
                                }
                            } elseif ($rule->apply_to === 'group') {
                                $groupPercentage = $rule->percentage;
                                if ($rule->products) {
                                    foreach ($rule->products as $p) {
                                        $productIds[] = $p->product_id;
                                    }
                                }
                            } elseif ($rule->apply_to === 'all') {
                                $groupPercentage = $rule->percentage;
                                $allIds = DB::table('ec_products')->where('status', 'published')->pluck('id')->toArray();
                                $productIds = array_merge($productIds, $allIds);
                            }
                        }
                    }

                    $productIds = array_values(array_unique(array_filter($productIds)));
                    if (empty($productIds)) {
                        continue;
                    }

                    $products = $this->fetchProductsWithDetails($productIds);

                    $promoProducts = [];
                    foreach ($products as $val) {
                        $discountObj = null;
                        if (isset($individualDiscountMap[$val->product_id])) {
                            $ind = $individualDiscountMap[$val->product_id];
                            $discountObj = (object) [
                                'value' => (int) $ind->value,
                                'discount_type' => $ind->discount_type,
                                'final_price' => $ind->final_price ? (float) $ind->final_price : null,
                                'product_price' => $ind->product_price ? (float) $ind->product_price : (float) $val->price,
                                'discount_amount' => $ind->discount_amount ? (float) $ind->discount_amount : null,
                                'start_date' => $promotion->start_date ? $promotion->start_date->format('Y-m-d H:i:s') : null,
                                'end_date' => $promotion->end_date ? $promotion->end_date->format('Y-m-d H:i:s') : null,
                            ];
                        } elseif ($groupPercentage !== null) {
                            $pct = (float) $groupPercentage;
                            $basePrice = (float) $val->price;
                            $finalPrice = round($basePrice - ($basePrice * $pct / 100), 2);
                            $discountAmount = round($basePrice * $pct / 100, 2);
                            $discountObj = (object) [
                                'value' => (int) $pct,
                                'discount_type' => 'percent',
                                'final_price' => $finalPrice,
                                'product_price' => $basePrice,
                                'discount_amount' => $discountAmount,
                                'start_date' => $promotion->start_date ? $promotion->start_date->format('Y-m-d H:i:s') : null,
                                'end_date' => $promotion->end_date ? $promotion->end_date->format('Y-m-d H:i:s') : null,
                            ];
                        }

                        $val->discount = $discountObj;
                        $promoProducts[] = $val;
                    }

                    if (!empty($promoProducts)) {
                        $discountPromotions[] = [
                            'id' => $promotion->id,
                            'name' => $promotion->name,
                            'type' => 'discount',
                            'description' => $promotion->description,
                            'start_date' => $promotion->start_date ? $promotion->start_date->format('Y-m-d H:i:s') : null,
                            'end_date' => $promotion->end_date ? $promotion->end_date->format('Y-m-d H:i:s') : null,
                            'products' => $promoProducts,
                        ];
                    }
                } elseif ($promotion->type === 'buy_x_get_y' || $promotion->type === 'bogo') {
                    $buyQty = 1;
                    $getQty = 1;
                    $buyProductIds = [];
                    $freeProductIds = [];

                    if ($promotion->buyXGetYRules) {
                        foreach ($promotion->buyXGetYRules as $rule) {
                            $buyQty = $rule->buy_quantity ?? 1;
                            $getQty = $rule->get_quantity ?? 1;
                            if ($rule->products) {
                                foreach ($rule->products as $p) {
                                    if ($p->type === 'free') {
                                        $freeProductIds[] = $p->product_id;
                                    } else {
                                        $buyProductIds[] = $p->product_id;
                                    }
                                }
                            }
                        }
                    }

                    $allBogoProductIds = array_values(array_unique(array_filter(array_merge($buyProductIds, $freeProductIds))));
                    if (empty($allBogoProductIds)) {
                        continue;
                    }

                    $bogoProducts = $this->fetchProductsWithDetails($allBogoProductIds);

                    // Add bogo badge and type info
                    foreach ($bogoProducts as $bp) {
                        $bp->is_bogo = true;
                        $bp->bogo_deal = "Buy {$buyQty} Get {$getQty} Free";
                        $bp->bogo_deal_ar = "اشتري {$buyQty} واحصل على {$getQty} مجاناً";
                        $bp->discount = null;
                    }

                    $badgeText = ($buyQty == 1 && $getQty == 1) ? "BUY 1 GET 1 FREE" : "BUY {$buyQty} GET {$getQty} FREE";
                    $badgeTextAr = ($buyQty == 1 && $getQty == 1) ? "اشتري 1 واحصل على 1 مجاناً" : "اشتري {$buyQty} واحصل على {$getQty} مجاناً";

                    $buyXGetYPromotions[] = [
                        'id' => $promotion->id,
                        'name' => $promotion->name,
                        'type' => 'buy_x_get_y',
                        'description' => $promotion->description,
                        'buy_quantity' => $buyQty,
                        'get_quantity' => $getQty,
                        'badge_text' => $badgeText,
                        'badge_text_ar' => $badgeTextAr,
                        'start_date' => $promotion->start_date ? $promotion->start_date->format('Y-m-d H:i:s') : null,
                        'end_date' => $promotion->end_date ? $promotion->end_date->format('Y-m-d H:i:s') : null,
                        'products' => $bogoProducts,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'discounts' => $discountPromotions,
                'buy_x_get_y' => $buyXGetYPromotions,
                'promotions' => array_merge($discountPromotions, $buyXGetYPromotions),
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error fetching Special Offers: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch special offers',
                'message' => $e->getMessage(),
                'discounts' => [],
                'buy_x_get_y' => [],
                'promotions' => [],
            ], 500);
        }
    }

    private function fetchProductsWithDetails(array $productIds)
    {
        $products = DB::table('ec_product_category_product')
            ->select(
                DB::raw('CAST(ec_products.price AS DECIMAL(8,2)) as price'),
                'ec_products.id as product_id',
                'ec_products.name as product_name',
                'ec_products.name_ar as product_name_ar',
                'ec_product_categories.id as category_id',
                'ec_product_categories.name as category_name',
                'ec_products.image',
                'ec_products.images',
                'ec_product_collections.name as collection_name',
                'ec_products.description',
                'ec_products.quantity as product_qty',
                'ec_product_labels.name as label_name',
                'ec_product_labels.color as label_color',
                'ec_products.sale_price'
            )
            ->join('ec_products', 'ec_product_category_product.product_id', '=', 'ec_products.id')
            ->leftJoin('ec_product_categories', 'ec_product_category_product.category_id', '=', 'ec_product_categories.id')
            ->leftJoin('ec_product_collection_products', 'ec_product_collection_products.product_id', '=', 'ec_products.id')
            ->leftJoin('ec_product_collections', 'ec_product_collection_products.product_collection_id', '=', 'ec_product_collections.id')
            ->leftJoin('ec_product_label_products', 'ec_product_label_products.product_id', '=', 'ec_products.id')
            ->leftJoin('ec_product_labels', 'ec_product_label_products.product_label_id', '=', 'ec_product_labels.id')
            ->whereIn('ec_products.id', $productIds)
            ->where('ec_products.status', 'published')
            ->where('ec_product_categories.status', 'published')
            ->where('ec_product_categories.parent_id', 0)
            ->get();

        $foundProductIds = $products->pluck('product_id')->toArray();
        $missingProductIds = array_diff($productIds, $foundProductIds);

        if (!empty($missingProductIds)) {
            $missingProducts = DB::table('ec_products')
                ->select(
                    DB::raw('CAST(ec_products.price AS DECIMAL(8,2)) as price'),
                    'ec_products.id as product_id',
                    'ec_products.name as product_name',
                    'ec_products.name_ar as product_name_ar',
                    'ec_products.image',
                    'ec_products.images',
                    'ec_products.description',
                    'ec_products.quantity as product_qty',
                    'ec_products.sale_price'
                )
                ->whereIn('ec_products.id', $missingProductIds)
                ->where('ec_products.status', 'published')
                ->get();

            foreach ($missingProducts as $mp) {
                $cat = DB::table('ec_product_categories')
                    ->select('ec_product_categories.id as category_id', 'ec_product_categories.name as category_name')
                    ->join('ec_product_category_product', 'ec_product_category_product.category_id', '=', 'ec_product_categories.id')
                    ->where('ec_product_category_product.product_id', $mp->product_id)
                    ->where('ec_product_categories.parent_id', 0)
                    ->first();

                $mp->category_id = $cat ? $cat->category_id : null;
                $mp->category_name = $cat ? $cat->category_name : 'Perfumes';
                $mp->collection_name = null;
                $mp->label_name = null;
                $mp->label_color = null;

                $products->push($mp);
            }
        }

        $products = $products->unique('product_id')->values();

        foreach ($products as $val) {
            $val->subcategory = DB::table('ec_product_categories')
                ->select('name as subcategory_name')
                ->join('ec_product_category_product', 'ec_product_category_product.category_id', '=', 'ec_product_categories.id')
                ->where('product_id', $val->product_id)
                ->where('ec_product_categories.parent_id', '!=', 0)
                ->first();
        }

        return $products;
    }

    public function getBestSelling(Request $request)
    {
        try {
            $now = \Carbon\Carbon::now();

            // 1. Get all published parent categories
            $categories = ProductCategory::where('parent_id', 0)
                ->where('status', 'published')
                ->get(['id', 'name']);

            // 2. Aggregate sales map
            $salesMap = DB::table('ec_order_product')
                ->select('product_id', DB::raw('SUM(qty) as total_sales'))
                ->groupBy('product_id')
                ->pluck('total_sales', 'product_id')
                ->toArray();

            // 3. Active discount promotions map
            $activeDiscounts = Promotion::where('isDeleted', false)
                ->where('type', 'discount')
                ->where('start_date', '<=', $now)
                ->where('end_date', '>=', $now)
                ->with(['discountRules.individualRules', 'discountRules.products'])
                ->get();

            $individualDiscountMap = [];
            $groupDiscountRules = [];
            foreach ($activeDiscounts as $promo) {
                if ($promo->discountRules) {
                    foreach ($promo->discountRules as $rule) {
                        if ($rule->apply_to === 'individual') {
                            if ($rule->individualRules) {
                                foreach ($rule->individualRules as $ind) {
                                    $individualDiscountMap[$ind->product_id] = [
                                        'rule' => $ind,
                                        'promo' => $promo,
                                    ];
                                }
                            }
                        } elseif ($rule->apply_to === 'group') {
                            if ($rule->products) {
                                foreach ($rule->products as $gp) {
                                    $groupDiscountRules[$gp->product_id] = [
                                        'percentage' => $rule->percentage,
                                        'promo' => $promo,
                                    ];
                                }
                            }
                        }
                    }
                }
            }

            $result = [];

            foreach ($categories as $category) {
                $products = DB::table('ec_product_category_product')
                    ->select(
                        DB::raw('CAST(ec_products.price AS DECIMAL(8,2)) as price'),
                        'ec_products.id as product_id',
                        'ec_products.name as product_name',
                        'ec_products.name_ar as product_name_ar',
                        'ec_product_categories.id as category_id',
                        'ec_product_categories.name as category_name',
                        'ec_products.image',
                        'ec_products.images',
                        'ec_product_collections.name as collection_name',
                        'ec_products.description',
                        'ec_products.quantity as product_qty',
                        'ec_product_labels.name as label_name',
                        'ec_product_labels.color as label_color',
                        'ec_products.sale_price'
                    )
                    ->join('ec_products', 'ec_product_category_product.product_id', '=', 'ec_products.id')
                    ->join('ec_product_categories', 'ec_product_category_product.category_id', '=', 'ec_product_categories.id')
                    ->leftJoin('ec_product_collection_products', 'ec_product_collection_products.product_id', '=', 'ec_products.id')
                    ->leftJoin('ec_product_collections', 'ec_product_collection_products.product_collection_id', '=', 'ec_product_collections.id')
                    ->leftJoin('ec_product_label_products', 'ec_product_label_products.product_id', '=', 'ec_products.id')
                    ->leftJoin('ec_product_labels', 'ec_product_label_products.product_label_id', '=', 'ec_product_labels.id')
                    ->where('ec_product_category_product.category_id', $category->id)
                    ->where('ec_products.status', 'published')
                    ->get()
                    ->unique('product_id')
                    ->values();

                $categoryProducts = [];

                foreach ($products as $val) {
                    $val->sales = isset($salesMap[$val->product_id]) ? intval($salesMap[$val->product_id]) : 0;

                    $val->subcategory = DB::table('ec_product_categories')
                        ->select('name as subcategory_name')
                        ->join('ec_product_category_product', 'ec_product_category_product.category_id', '=', 'ec_product_categories.id')
                        ->where('product_id', $val->product_id)
                        ->where('ec_product_categories.parent_id', '!=', 0)
                        ->first();

                    // Attach discount if exists
                    $discountObj = null;
                    if (isset($individualDiscountMap[$val->product_id])) {
                        $ind = $individualDiscountMap[$val->product_id]['rule'];
                        $promo = $individualDiscountMap[$val->product_id]['promo'];
                        $discountObj = (object) [
                            'value' => (int) $ind->value,
                            'discount_type' => $ind->discount_type,
                            'final_price' => $ind->final_price ? (float) $ind->final_price : null,
                            'product_price' => $ind->product_price ? (float) $ind->product_price : (float) $val->price,
                            'discount_amount' => $ind->discount_amount ? (float) $ind->discount_amount : null,
                            'start_date' => $promo->start_date ? $promo->start_date->format('Y-m-d H:i:s') : null,
                            'end_date' => $promo->end_date ? $promo->end_date->format('Y-m-d H:i:s') : null,
                        ];
                    } elseif (isset($groupDiscountRules[$val->product_id])) {
                        $pct = (float) $groupDiscountRules[$val->product_id]['percentage'];
                        $promo = $groupDiscountRules[$val->product_id]['promo'];
                        $basePrice = (float) $val->price;
                        $finalPrice = round($basePrice - ($basePrice * $pct / 100), 2);
                        $discountAmount = round($basePrice * $pct / 100, 2);
                        $discountObj = (object) [
                            'value' => (int) $pct,
                            'discount_type' => 'percent',
                            'final_price' => $finalPrice,
                            'product_price' => $basePrice,
                            'discount_amount' => $discountAmount,
                            'start_date' => $promo->start_date ? $promo->start_date->format('Y-m-d H:i:s') : null,
                            'end_date' => $promo->end_date ? $promo->end_date->format('Y-m-d H:i:s') : null,
                        ];
                    }

                    $val->discount = $discountObj;
                    $categoryProducts[] = $val;
                }

                // Sort category products by sales descending
                usort($categoryProducts, function ($a, $b) {
                    if ($b->sales === $a->sales) {
                        return $b->product_id <=> $a->product_id;
                    }
                    return $b->sales <=> $a->sales;
                });

                $result[$category->name] = $categoryProducts;
            }

            return response()->json($result, 200);
        } catch (\Exception $e) {
            \Log::error('Error fetching best selling products: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([], 500);
        }
    }
}
