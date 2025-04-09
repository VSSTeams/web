<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Order;
use GuzzleHttp\Client;
use App\Models\Product;
use App\Models\CartDetail;
use App\Models\OrderDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\CheckoutRequest;

class CheckoutController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CheckoutRequest $request)
    {
        if ($request->payment_method === 'COD') {
            return $this->handleCodPayment($request);
        } elseif ($request->payment_method === 'MoMo') {
            return $this->createMomoPayment($request);
        }

        return response()->json(['message' => 'Invalid payment method'], 400);
    }

    protected function handleCodPayment(CheckoutRequest $request)
    {
        try {
            DB::beginTransaction();

            // Create the order
            $order = new Order();
            $order->user_id = Auth::id();
            $order->email = $request->email;
            $order->name = $request->name;
            $order->phone = $request->phone;
            $order->address = $request->address;
            $order->payment_method = 'COD'; // Set payment method to COD
            $order->status = 0; // Set initial status (e.g., pending)
            $order->save();

            // Get the cart items from the request
            $cartItems = $request->input('cart_items');

            // Create the order details and decrease product stock
            foreach ($cartItems as $item) {
                $product = Product::findOrFail($item['product_id']);
                OrderDetail::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $product->price * $item['quantity'], // Use actual product price
                ]);
            }

            // Clear the user's cart
            CartDetail::whereIn('cart_id', Cart::where('user_id', Auth::id())->pluck('id'))->delete();
            Cart::where('user_id', Auth::id())->delete();

            DB::commit();

            return response()->json(['message' => 'Order placed successfully (COD)', 'order_id' => $order->id], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to place order (COD)', 'error' => $e->getMessage()], 500);
        }
    }

    public function createMomoPayment(CheckoutRequest $request)
    {
        $partnerCode = env('MOMO_PARTNER_CODE');
        $accessKey = env('MOMO_ACCESS_KEY');
        $secretKey = env('MOMO_SECRET_KEY');
        $endpoint = env('MOMO_ENDPOINT');
        $redirectUrl = env('MOMO_REDIRECT_URL');
        $ipnUrl = env('MOMO_IPN_URL');

        $user = Auth::user();
        $orderId = time() . $user->id; // Unique order ID for your system
        $orderInfo = "Thanh toán đơn hàng #" . $orderId;
        $amount = collect($request->input('cart_items'))->sum(function ($item) {
            return Product::find($item['product_id'])->price * $item['quantity'];
        });
        $requestId = time() . "";
        $requestType = "captureWallet";
        $extraData = json_encode(['user_id' => $user->id, 'order_id' => $orderId]);

        $rawHash = "accessKey=" . $accessKey . "&amount=" . $amount . "&extraData=" . $extraData . "&ipnUrl=" . $ipnUrl .
            "&orderId=" . $orderId . "&orderInfo=" . $orderInfo . "&partnerCode=" . $partnerCode . "&redirectUrl=" . $redirectUrl .
            "&requestId=" . $requestId . "&requestType=" . $requestType;

        $signature = hash_hmac("sha256", $rawHash, $secretKey);

        $data = [
            'partnerCode' => $partnerCode,
            'partnerName' => config('app.name'), // Use your app name
            'storeId' => "YourStoreID", // Replace with your store ID if applicable
            'requestId' => $requestId,
            'amount' => $amount,
            'orderId' => $orderId,
            'orderInfo' => $orderInfo,
            'redirectUrl' => $redirectUrl,
            'ipnUrl' => $ipnUrl,
            'lang' => 'vi',
            'extraData' => $extraData,
            'requestType' => $requestType,
            'signature' => $signature,
        ];

        $client = new Client();
        try {
            $response = $client->post($endpoint, [
                'headers' => ['Content-Type' => 'application/json'],
                'json' => $data,
                'verify' => false, // Only for testing
            ]);
            $body = json_decode($response->getBody(), true);
            if (isset($body['payUrl'])) {
                // Optionally, you can create a temporary order in your database with 'pending' status
                // and store the MoMo order ID if provided in the response.
                return response()->json(['payUrl' => $body['payUrl']]);
            } else {
                return response()->json(['message' => 'Failed to create MoMo payment link', 'error' => json_encode($body)], 500);
            }
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error connecting to MoMo', 'error' => $e->getMessage()], 500);
        }
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
