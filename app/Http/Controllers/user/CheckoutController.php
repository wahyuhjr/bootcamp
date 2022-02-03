<?php

namespace App\Http\Controllers\user;

// use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Models\Checkout;
use App\Http\Requests\User\Checkout\Store;
use Illuminate\Http\Request;
use App\Models\Camp;
use App\Mail\Checkout\AfterCheckout;
use Auth;
use Mail;
use Str;
use Midtrans;

class CheckoutController extends Controller
{
    function __construct(){
        Midtrans\Config::$serverKey = env('MIDTRANS_SERVERKEY');
        Midtrans\Config::$isProduction = env('MIDTRANS_IS_PRODUCTION');
        Midtrans\Config::$isSanitized = env('MIDTRANS_IS_SANITIZED');
        Midtrans\Config::$isds = env('MIDTRANS_IS_3DS');
    }




    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Camp $camp, Request $request)
    {
        if ($camp->isRegistered) {
            $request->session()->flash('error', "You already registered on {$camp->title}camp.");
            return redirect(route('user.dashboard'));
        }
        return view('checkout.create', [
            'camp' => $camp
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Store $request, Camp $camp)
    {
        // return $request->all();
        //mapping request data
        $data = $request->all();
        $data['user_id'] = Auth::id();
        $data['camp_id'] = $camp->id;

        //update data
        $user = Auth::user();
        $user->email = $data['email'];
        $user->name = $data['name'];
        $user->occupation = $data['occupation'];
        $user->save();

        //create table checkout
        $checkout = Checkout::create($data);
        $this->getSnapRedirect($checkout);

        //sending mail
        Mail::to(Auth::user()->email)->send(new AfterCheckout($checkout));

        return redirect(route('checkout.success'));

        return $checkout;
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Checkout  $checkout
     * @return \Illuminate\Http\Response
     */
    public function show(Checkout $checkout)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Checkout  $checkout
     * @return \Illuminate\Http\Response
     */
    public function edit(Checkout $checkout)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Checkout  $checkout
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Checkout $checkout)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Checkout  $checkout
     * @return \Illuminate\Http\Response
     */
    public function destroy(Checkout $checkout)
    {
        //
    }
    public function success()
    {
        return view('checkout.success');
    }

    /**
     * Midtrans
     */
    public function getSnapRedirect(Checkout $checkout)
    {
        $orderId = $checkout->id . '-' . Str::random(5);
        $price = $checkout->Camp->price * 1000;
        $checkout->midtrans_booking_code = $orderId;

        $transaction_details = [
            'order_id' => $orderId,
            'gross_amount' => $price
        ];

        $item_details = [
            'id' => $orderId,
            'price' => $price,
            'quantity' => 1,
            'name' => "Payment for {$checkout->Camp->title} Camp"
        ];

        $userData = [
            'first_name' => $checkout->User->name,
            'last_name' => "",
            'address' => $checkout->User->address,
            'city' => "",
            'postal_code' => "",
            'phone' => $checkout->User->phone,
            'country_code' => "IDN"
        ];

        $customer_details [
            'first_name' => $checkout->User->name,
            'last_name' => "",
            'email' => $checkout->User->email,
            'billing_addres' => $userData,
            'shiping_address' => $userData
        ];

        $midtrans_params = [
            'transactiond_details' => $transaction_details,
            'customer_details' => $customer_details,
            'item_details' => $item_details
        ];

        try {
            //Get Snap Payment Page URL
            $paymentUrl = \Midtrans\Snap::createTransaction($params)->redirect_url;
            $checkout->$midtrans_url = $paymentUrl;
            $checkout->save();

            return $paymentUrl;
        } catch (Exception $e) {
            return false;
        }

    }
}
