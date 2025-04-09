<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Cart;
use App\Models\CartDetail;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MomoController extends Controller
{
    public function handleCallback(Request $request)
    {
        if ($request->resultCode == 0) {
            $extraData = json_decode($request->extraData, true);
            $userId = $extraData['user_id'] ?? null;
            $momoOrderId = $extraData['order_id'] ?? null;

            if ($userId && $momoOrderId) {
                try {
                    DB::beginTransaction();

                    $user = \App\Models\User::find($userId);

                    $order = Order::create([
                        'user_id' => $userId,
                        'order_id_momo' => $momoOrderId,
                        'email' => $user ? $user->email : $request->email ?? '',
                        'name' => $user ? $user->name : $request->orderInfo ?? '',
                        'address' => $user ? $user->address : '',
                        'phone' => $user ? $user->phone : '',
                        'status' => 1, // Đã thanh toán
                        'payment_method' => 'MoMo',
                    ]);

                    $cartDetails = CartDetail::join('carts', 'cart_detail.cart_id', '=', 'carts.id')
                        ->where('carts.user_id', $userId)
                        ->get(['cart_detail.product_id', 'cart_detail.quantity']);

                    foreach ($cartDetails as $item) {
                        $product = Product::findOrFail($item->product_id);
                        OrderDetail::create([
                            'order_id' => $order->id,
                            'product_id' => $item->product_id,
                            'quantity' => $item->quantity,
                            'price' => $product->price * $item->quantity,
                        ]);
                        $product->decrement('ton_kho', $item->quantity);
                    }

                    CartDetail::whereIn('cart_id', Cart::where('user_id', $userId)->pluck('id'))->delete();
                    Cart::where('user_id', $userId)->delete();

                    DB::commit();

                    // Redirect về trang thành công trên frontend
                    return redirect()->away(env('MOMO_REDIRECT_URL') . '?status=success&order_id=' . $order->id);
                } catch (\Exception $e) {
                    DB::rollBack();
                    // Redirect về trang thất bại trên frontend
                    return redirect()->away(env('MOMO_REDIRECT_URL') . '?status=failure&error=' . urlencode($e->getMessage()));
                }
            } else {
                return response()->json(['success' => false, 'message' => 'Thiếu thông tin user_id hoặc order_id từ MoMo'], 400);
            }
        } else {
            // Redirect về trang thất bại trên frontend
            return redirect()->away(env('MOMO_REDIRECT_URL') . '?status=failure&message=' . urlencode($request->message));
        }
    }

    public function handleIpn(Request $request)
    {
        // Xử lý IPN (Instant Payment Notification) từ MoMo
        // Xác thực dữ liệu, cập nhật trạng thái đơn hàng dựa trên các thông tin từ $request
        // Trả về response 200 OK cho MoMo
        // Ví dụ (cần triển khai logic xác thực và cập nhật thực tế):
        if ($request->resultCode == 0) {
            $momoOrderId = $request->orderId;
            $transactionId = $request->transId;
            $amount = $request->amount;
            $order = Order::where('order_id_momo', $momoOrderId)->first();

            if ($order && $order->status != 1) {
                $order->update(['status' => 1]); // Cập nhật trạng thái đã thanh toán
                return response('OK', 200);
            } else {
                return response('OK', 200); // Đơn hàng không tồn tại hoặc đã được xử lý
            }
        } else {
            // Xử lý giao dịch thất bại
            return response('OK', 200);
        }
    }
}
