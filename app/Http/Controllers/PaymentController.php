<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Payment;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Razorpay\Api\Api;
use Barryvdh\Debugbar\Facades\Debugbar;
use App\Services\MailchimpService;

class PaymentController extends Controller
{
    protected $razorpay;

    public function __construct()
    {
        $this->razorpay = new Api(env('RAZORPAY_KEY_ID'), env('RAZORPAY_KEY_SECRET'));
    }

    /**
     * Create Razorpay Order + User (if not exists)
     */
    public function createOrder(Request $request)
    {
        $request->validate([
            'email'   => 'required|email',
            'phone'   => 'required',
            'amount'  => 'required|numeric',
        ]);

        // Check if user exists
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            $pass = Str::random(12);
            Debugbar::error("email: ".$request->email."; Password: " . $pass);
            $user = User::create([
                'name'     => $request->email,
                'email'    => $request->email,
                'phone'    => $request->phone,
                'password' => bcrypt($pass), // random password
            ]);
        }

        try {
            // Create Razorpay Order
            $order = $this->razorpay->order->create([
                'receipt'         => uniqid(),
                'amount'          => $request->amount * 100,
                'currency'        => 'USD',
                'payment_capture' => 1
            ]);

            // Save payment entry
            Payment::create([
                'user_id'   => $user->id,
                'order_id'  => $order['id'],
                'amount'    => $request->amount,
                'status'    => 'created',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'failed',
                'error'  => 'Razorpay error: ' . $e->getMessage(),
                //'RAZORPAY_KEY' => env('RAZORPAY_KEY_ID'),
                //'RAZORPAY_SECRET' => env('RAZORPAY_KEY_SECRET')
            ], 500);
        }

        return response()->json([
            'order_id'  => $order['id'],
            'amount'    => $request->amount,
            'currency'  => 'INR',
            'user_id'   => $user->id,
        ]);
    }

    /**
     * Verify payment after success
     */
    public function verifyPayment(Request $request)
    {
        $request->validate([
            'razorpay_payment_id' => 'required',
            'razorpay_order_id'   => 'required',
            'razorpay_signature'  => 'required',
        ]);

        try {
            $attributes = [
                'razorpay_order_id'   => $request->razorpay_order_id,
                'razorpay_payment_id' => $request->razorpay_payment_id,
                'razorpay_signature'  => $request->razorpay_signature,
            ];

            $this->razorpay->utility->verifyPaymentSignature($attributes);

            $payment = Payment::where('order_id', $request->razorpay_order_id)->first();
            $payment->update([
                'payment_id' => $request->razorpay_payment_id,
                'status'     => 'paid'
            ]);

            //get the user
            $user = User::find($payment->user_id);
            $this->sendEmailAndRegister($user);

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'failed',
                'error'  => $e->getMessage()
            ], 400);
        }
    }

    private function sendEmailAndRegister($user){
        //create new password as the previous password cannot be retrieved for sending
        $pass = '';
        try {
            $pass = Str::random(12);
            $user->password = bcrypt($pass);
            $user->save();
        } catch (\Exception $ex) {
            Debugbar::error('Error updating user password: ' . $ex->getMessage());
        }
        // Send Welcome Email using Gmail SMTP
        try {
            $subject = 'Purchase Confirmed – Start Your Living Fulfilled Journey Today!';
            //$body = "<p>Hi {$user->name},</p><p>Your account has been created successfully!</p><p>Password: {$pass}</p>";
            $body = '<!doctype html>
                <html
                lang="en"
                xmlns="http://www.w3.org/1999/xhtml"
                xmlns:v="urn:schemas-microsoft-com:vml"
                xmlns:o="urn:schemas-microsoft-com:office:office"
                >
                <head>
                    <meta charset="UTF-8" />
                    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
                    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
                    <title>Your Living Fulfilled Journey Begins Here</title>
                    <!--[if mso]>
                        <noscript>
                            <xml>
                            <o:OfficeDocumentSettings>
                                <o:PixelsPerInch>96</o:PixelsPerInch>
                            </o:OfficeDocumentSettings>
                            </xml>
                        </noscript>
                    <![endif]-->
                    <style type="text/css">
                        /* Reset */
                        body,
                        table,
                        td,
                        a {
                            -webkit-text-size-adjust: 100%;
                            -ms-text-size-adjust: 100%;
                        }
                        table,
                        td {
                            mso-table-lspace: 0pt;
                            mso-table-rspace: 0pt;
                        }
                        img {
                            -ms-interpolation-mode: bicubic;
                            border: 0;
                            height: auto;
                            line-height: 100%;
                            outline: none;
                            text-decoration: none;
                        }
                        body {
                            margin: 0;
                            padding: 0;
                            width: 100% !important;
                            height: 100% !important;
                            background-color: #eeeeee;
                        }
                        a[x-apple-data-detectors] {
                            color: inherit !important;
                            text-decoration: none !important;
                            font-size: inherit !important;
                            font-family: inherit !important;
                            font-weight: inherit !important;
                            line-height: inherit !important;
                        }
                        @media only screen and (max-width: 620px) {
                            .email-container {
                            width: 100% !important;
                            max-width: 100% !important;
                            }
                            .fluid {
                            max-width: 100% !important;
                            height: auto !important;
                            }
                            .stack-column {
                            display: block !important;
                            width: 100% !important;
                            max-width: 100% !important;
                            }
                            .stack-column-center {
                            text-align: center !important;
                            }
                            .center-on-narrow {
                            text-align: center !important;
                            display: block !important;
                            margin-left: auto !important;
                            margin-right: auto !important;
                            float: none !important;
                            }
                            table.center-on-narrow {
                            display: inline-block !important;
                            }
                            .padding-mobile {
                            padding-left: 20px !important;
                            padding-right: 20px !important;
                            }
                        }
                    </style>
                </head>
                <body
                    style="
                        margin: 0;
                        padding: 0;
                        background-color: #eeeeee;
                        font-family: -apple-system, BlinkMacSystemFont, &quot;Segoe UI&quot;,
                            Roboto, &quot;Helvetica Neue&quot;, Arial, sans-serif;
                    "
                >
                    <!-- Preheader (hidden text for email preview) -->
                    <div
                        style="
                            display: none;
                            font-size: 1px;
                            line-height: 1px;
                            max-height: 0px;
                            max-width: 0px;
                            opacity: 0;
                            overflow: hidden;
                            mso-hide: all;
                        "
                    >
                        Welcome to the Living Fulfilled Life Planning &amp; Self Development
                        Program. Your login credentials and program resources are inside.
                    </div>
                    <!-- Full Width Background -->
                    <table
                        role="presentation"
                        cellspacing="0"
                        cellpadding="0"
                        border="0"
                        width="100%"
                        style="background-color: #eeeeee"
                    >
                        <tr>
                            <td align="center" valign="top" style="padding: 30px 10px">
                            <!-- Email Container -->
                            <table
                                role="presentation"
                                cellspacing="0"
                                cellpadding="0"
                                border="0"
                                width="580"
                                class="email-container"
                                style="max-width: 580px; width: 100%"
                            >
                                <!-- ========== HEADER WITH LOGO ========== -->
                                <tr>
                                    <td
                                        style="
                                        background-color: #ffffff;
                                        border-radius: 8px 8px 0 0;
                                        padding: 35px 40px 20px 40px;
                                        text-align: center;
                                        "
                                    >
                                        <img
                                        src="https://livinfulfilled.com/wp-content/uploads/2025/09/logo-1.png"
                                        alt="Living Fulfilled"
                                        width="180"
                                        style="
                                            display: inline-block;
                                            max-width: 180px;
                                            height: auto;
                                        "
                                        />
                                    </td>
                                </tr>
                                <!-- ========== HERO HEADING ========== -->
                                <tr>
                                    <td
                                        style="
                                        background-color: #ffffff;
                                        padding: 5px 40px 25px 40px;
                                        text-align: center;
                                        "
                                    >
                                        <h1
                                        style="
                                            margin: 0;
                                            font-family: Georgia, &quot;Times New Roman&quot;,
                                                Times, serif;
                                            font-size: 26px;
                                            font-weight: 700;
                                            color: #2d3748;
                                            line-height: 1.35;
                                        "
                                        >
                                        Your Living Fulfilled Journey Begins Here
                                        </h1>
                                    </td>
                                </tr>
                                <!-- ========== MARKETING IMAGE ========== -->
                                <tr>
                                    <td
                                        style="
                                        background-color: #ffffff;
                                        padding: 0 40px 30px 40px;
                                        text-align: center;
                                        "
                                    >
                                        <img
                                        src="https://livinfulfilled.com/wp-content/uploads/2026/02/Marketing-Image-png-scaled.png"
                                        alt="The Living Fulfilled Life Planning & Self Development Guide"
                                        width="400"
                                        style="
                                            display: block;
                                            margin: 0 auto;
                                            max-width: 400px;
                                            width: 100%;
                                            height: auto;
                                            border-radius: 6px;
                                        "
                                        class="fluid"
                                        />
                                    </td>
                                </tr>
                                <!-- ========== DIVIDER ========== -->
                                <tr>
                                    <td style="background-color: #ffffff; padding: 0 40px">
                                        <hr
                                        style="
                                            border: none;
                                            border-top: 1px solid #e2e8f0;
                                            margin: 0;
                                        "
                                        />
                                    </td>
                                </tr>
                                <!-- ========== GREETING & WELCOME ========== -->
                                <tr>
                                    <td
                                        style="
                                        background-color: #ffffff;
                                        padding: 0 40px 15px 40px;
                                        "
                                    >
                                        <p
                                        style="
                                            margin: 0 0 18px 0;
                                            font-size: 15px;
                                            color: #2d3748;
                                            line-height: 1.6;
                                        "
                                        >
                                        <strong>Dear '.$user->name.',</strong>
                                        </p>
                                        <p
                                        style="
                                            margin: 0;
                                            font-size: 15px;
                                            color: #4a5568;
                                            line-height: 1.7;
                                        "
                                        >
                                        Welcome. By choosing the
                                        <strong style="color: #2d3748"
                                            >Living Fulfilled Life Planning &amp; Self
                                            Development Program</strong
                                        >, you\'ve taken an important and courageous step
                                        toward designing your life with clarity, intention,
                                        and purpose.
                                        </p>
                                    </td>
                                </tr>
                                <!-- ========== IMPORTANT CALLOUT BOX ========== -->
                                <tr>
                                    <td
                                        style="
                                        background-color: #ffffff;
                                        padding: 10px 40px 25px 40px;
                                        "
                                    >
                                        <table
                                        role="presentation"
                                        cellspacing="0"
                                        cellpadding="0"
                                        border="0"
                                        width="100%"
                                        >
                                        <tr>
                                            <td
                                                style="
                                                    background-color: #fffbeb;
                                                    border-left: 4px solid #f6ad55;
                                                    padding: 16px 20px;
                                                    border-radius: 0 6px 6px 0;
                                                "
                                            >
                                                <p
                                                    style="
                                                    margin: 0;
                                                    font-size: 14px;
                                                    color: #744210;
                                                    line-height: 1.6;
                                                    "
                                                >
                                                    <strong style="color: #744210"
                                                    >Important:</strong
                                                    >
                                                    Please save this email somewhere safe. You
                                                    may want to return to it more than once as
                                                    you move through the program.
                                                </p>
                                            </td>
                                        </tr>
                                        </table>
                                    </td>
                                </tr>
                                <!-- ========== YOUR LOGIN CREDENTIALS ========== -->
                                <tr>
                                    <td
                                        style="
                                        background-color: #ffffff;
                                        padding: 5px 40px 5px 40px;
                                        "
                                    >
                                        <h2
                                        style="
                                            margin: 0 0 18px 0;
                                            font-family: Georgia, &quot;Times New Roman&quot;,
                                                Times, serif;
                                            font-size: 20px;
                                            font-weight: 700;
                                            color: #2d3748;
                                        "
                                        >
                                        Your Login Credentials
                                        </h2>
                                    </td>
                                </tr>
                                <tr>
                                    <td
                                        style="
                                        background-color: #ffffff;
                                        padding: 0 40px 20px 40px;
                                        "
                                    >
                                        <table
                                        role="presentation"
                                        cellspacing="0"
                                        cellpadding="0"
                                        border="0"
                                        width="100%"
                                        >
                                        <tr>
                                            <td
                                                style="
                                                    background-color: #f7fafc;
                                                    border: 1px solid #e2e8f0;
                                                    border-radius: 8px;
                                                    padding: 22px 24px;
                                                "
                                            >
                                                <p
                                                    style="
                                                    margin: 0 0 4px 0;
                                                    font-size: 11px;
                                                    font-weight: 700;
                                                    color: #a0aec0;
                                                    text-transform: uppercase;
                                                    letter-spacing: 1px;
                                                    "
                                                >
                                                    EMAIL ADDRESS
                                                </p>
                                                <p
                                                    style="
                                                    margin: 0 0 18px 0;
                                                    font-size: 15px;
                                                    color: #2d3748;
                                                    font-family: &quot;Courier New&quot;,
                                                        Courier, monospace;
                                                    font-weight: 700;
                                                    "
                                                >
                                                    '.$user->email.'
                                                </p>
                                                <p
                                                    style="
                                                    margin: 0 0 4px 0;
                                                    font-size: 11px;
                                                    font-weight: 700;
                                                    color: #a0aec0;
                                                    text-transform: uppercase;
                                                    letter-spacing: 1px;
                                                    "
                                                >
                                                    PASSWORD
                                                </p>
                                                <p
                                                    style="
                                                    margin: 0;
                                                    font-size: 15px;
                                                    color: #2d3748;
                                                    font-family: &quot;Courier New&quot;,
                                                        Courier, monospace;
                                                    font-weight: 700;
                                                    "
                                                >
                                                    '.$pass.'
                                                </p>
                                            </td>
                                        </tr>
                                        </table>
                                    </td>
                                </tr>
                                <!-- ========== ACCESS YOUR GUIDE BUTTON ========== -->
                                <tr>
                                    <td
                                        style="
                                        background-color: #ffffff;
                                        padding: 5px 40px 30px 40px;
                                        "
                                    >
                                        <table
                                        role="presentation"
                                        cellspacing="0"
                                        cellpadding="0"
                                        border="0"
                                        width="100%"
                                        >
                                        <tr>
                                            <td align="center">
                                                <a
                                                    href="https://livinfulfilled.com/login"
                                                    target="_blank"
                                                    style="
                                                    display: block;
                                                    background: linear-gradient(
                                                        135deg,
                                                        #48bb78,
                                                        #38a169
                                                    );
                                                    color: #ffffff;
                                                    font-size: 16px;
                                                    font-weight: 700;
                                                    text-decoration: none;
                                                    padding: 16px 30px;
                                                    border-radius: 8px;
                                                    text-align: center;
                                                    mso-padding-alt: 0;
                                                    "
                                                >
                                                    <!--[if mso]>
                                                    <i
                                                        style="
                                                            letter-spacing: 30px;
                                                            mso-font-width: -100%;
                                                            mso-text-raise: 30pt;
                                                        "
                                                        >&nbsp;</i
                                                    >
                                                    <![endif]-->
                                                    <span style="mso-text-raise: 15pt"
                                                    >Access Your Guide</span
                                                    >
                                                    <!--[if mso]>
                                                    <i
                                                        style="
                                                            letter-spacing: 30px;
                                                            mso-font-width: -100%;
                                                        "
                                                        >&nbsp;</i
                                                    >
                                                    <![endif]-->
                                                </a>
                                            </td>
                                        </tr>
                                        </table>
                                    </td>
                                </tr>
                                <!-- ========== HOW TO ACCESS YOUR GUIDE ========== -->
                                <tr>
                                    <td
                                        style="
                                        background-color: #ffffff;
                                        padding: 0 40px 5px 40px;
                                        "
                                    >
                                        <h2
                                        style="
                                            margin: 0 0 20px 0;
                                            font-family: Georgia, &quot;Times New Roman&quot;,
                                                Times, serif;
                                            font-size: 20px;
                                            font-weight: 700;
                                            color: #2d3748;
                                        "
                                        >
                                        How to Access Your Guide
                                        </h2>
                                    </td>
                                </tr>
                                <!-- Step 1 -->
                                <tr>
                                    <td
                                        style="
                                        background-color: #ffffff;
                                        padding: 0 40px 14px 40px;
                                        "
                                    >
                                        <table
                                        role="presentation"
                                        cellspacing="0"
                                        cellpadding="0"
                                        border="0"
                                        width="100%"
                                        >
                                        <tr>
                                            <td
                                                width="36"
                                                valign="top"
                                                style="padding-right: 14px"
                                            >
                                                <table
                                                    role="presentation"
                                                    cellspacing="0"
                                                    cellpadding="0"
                                                    border="0"
                                                >
                                                    <tr>
                                                    <td
                                                        style="
                                                            background-color: #48bb78;
                                                            width: 32px;
                                                            height: 32px;
                                                            border-radius: 50%;
                                                            text-align: center;
                                                            vertical-align: middle;
                                                            font-size: 14px;
                                                            font-weight: 700;
                                                            color: #ffffff;
                                                            line-height: 32px;
                                                        "
                                                    >
                                                        1
                                                    </td>
                                                    </tr>
                                                </table>
                                            </td>
                                            <td
                                                valign="middle"
                                                style="
                                                    font-size: 14px;
                                                    color: #4a5568;
                                                    line-height: 1.6;
                                                "
                                            >
                                                Click the
                                                <strong>Access Your Guide</strong> button
                                                above, or visit
                                                <a
                                                    href="https://livinfulfilled.com/login"
                                                    style="
                                                    color: #48bb78;
                                                    text-decoration: none;
                                                    font-family: &quot;Courier New&quot;,
                                                        Courier, monospace;
                                                    font-size: 13px;
                                                    font-weight: 700;
                                                    "
                                                    >livinfulfilled.com/login</a
                                                >
                                            </td>
                                        </tr>
                                        </table>
                                    </td>
                                </tr>
                                <!-- Step 2 -->
                                <tr>
                                    <td
                                        style="
                                        background-color: #ffffff;
                                        padding: 0 40px 14px 40px;
                                        "
                                    >
                                        <table
                                        role="presentation"
                                        cellspacing="0"
                                        cellpadding="0"
                                        border="0"
                                        width="100%"
                                        >
                                        <tr>
                                            <td
                                                width="36"
                                                valign="top"
                                                style="padding-right: 14px"
                                            >
                                                <table
                                                    role="presentation"
                                                    cellspacing="0"
                                                    cellpadding="0"
                                                    border="0"
                                                >
                                                    <tr>
                                                    <td
                                                        style="
                                                            background-color: #48bb78;
                                                            width: 32px;
                                                            height: 32px;
                                                            border-radius: 50%;
                                                            text-align: center;
                                                            vertical-align: middle;
                                                            font-size: 14px;
                                                            font-weight: 700;
                                                            color: #ffffff;
                                                            line-height: 32px;
                                                        "
                                                    >
                                                        2
                                                    </td>
                                                    </tr>
                                                </table>
                                            </td>
                                            <td
                                                valign="middle"
                                                style="
                                                    font-size: 14px;
                                                    color: #4a5568;
                                                    line-height: 1.6;
                                                "
                                            >
                                                Enter your Email and Password provided above.
                                            </td>
                                        </tr>
                                        </table>
                                    </td>
                                </tr>
                                <!-- Step 3 -->
                                <tr>
                                    <td
                                        style="
                                        background-color: #ffffff;
                                        padding: 0 40px 25px 40px;
                                        "
                                    >
                                        <table
                                        role="presentation"
                                        cellspacing="0"
                                        cellpadding="0"
                                        border="0"
                                        width="100%"
                                        >
                                        <tr>
                                            <td
                                                width="36"
                                                valign="top"
                                                style="padding-right: 14px"
                                            >
                                                <table
                                                    role="presentation"
                                                    cellspacing="0"
                                                    cellpadding="0"
                                                    border="0"
                                                >
                                                    <tr>
                                                    <td
                                                        style="
                                                            background-color: #48bb78;
                                                            width: 32px;
                                                            height: 32px;
                                                            border-radius: 50%;
                                                            text-align: center;
                                                            vertical-align: middle;
                                                            font-size: 14px;
                                                            font-weight: 700;
                                                            color: #ffffff;
                                                            line-height: 32px;
                                                        "
                                                    >
                                                        3
                                                    </td>
                                                    </tr>
                                                </table>
                                            </td>
                                            <td
                                                valign="middle"
                                                style="
                                                    font-size: 14px;
                                                    color: #4a5568;
                                                    line-height: 1.6;
                                                "
                                            >
                                                Once logged in, you\'ll have full access to your
                                                program resources.
                                            </td>
                                        </tr>
                                        </table>
                                    </td>
                                </tr>
                                <!-- ========== LOST PASSWORD / NEED HELP - TWO COLUMNS ========== -->
                                <tr>
                                    <td
                                        style="
                                        background-color: #ffffff;
                                        padding: 0 40px 30px 40px;
                                        "
                                    >
                                        <table
                                        role="presentation"
                                        cellspacing="0"
                                        cellpadding="0"
                                        border="0"
                                        width="100%"
                                        >
                                        <tr>
                                            <td
                                                class="stack-column"
                                                width="48%"
                                                valign="top"
                                                style="padding-right: 2%"
                                            >
                                                <table
                                                    role="presentation"
                                                    cellspacing="0"
                                                    cellpadding="0"
                                                    border="0"
                                                    width="100%"
                                                >
                                                    <tr>
                                                    <td
                                                        style="
                                                            background-color: #f7fafc;
                                                            border: 1px solid #e2e8f0;
                                                            border-radius: 8px;
                                                            padding: 18px 20px;
                                                        "
                                                    >
                                                        <p
                                                            style="
                                                                margin: 0 0 6px 0;
                                                                font-size: 14px;
                                                                font-weight: 700;
                                                                color: #2d3748;
                                                            "
                                                        >
                                                            Lost Your Password?
                                                        </p>
                                                        <p
                                                            style="
                                                                margin: 0;
                                                                font-size: 13px;
                                                                color: #718096;
                                                                line-height: 1.5;
                                                            "
                                                        >
                                                            Contact our support team at<br /><a
                                                                href="mailto:support@livinfulfilled.com"
                                                                style="
                                                                color: #48bb78;
                                                                text-decoration: none;
                                                                font-weight: 700;
                                                                "
                                                                >support@livinfulfilled.com</a
                                                            >
                                                        </p>
                                                    </td>
                                                    </tr>
                                                </table>
                                            </td>
                                            <td
                                                class="stack-column"
                                                width="48%"
                                                valign="top"
                                                style="padding-left: 2%"
                                            >
                                                <table
                                                    role="presentation"
                                                    cellspacing="0"
                                                    cellpadding="0"
                                                    border="0"
                                                    width="100%"
                                                >
                                                    <tr>
                                                    <td
                                                        style="
                                                            background-color: #f7fafc;
                                                            border: 1px solid #e2e8f0;
                                                            border-radius: 8px;
                                                            padding: 18px 20px;
                                                        "
                                                    >
                                                        <p
                                                            style="
                                                                margin: 0 0 6px 0;
                                                                font-size: 14px;
                                                                font-weight: 700;
                                                                color: #2d3748;
                                                            "
                                                        >
                                                            Need Help?
                                                        </p>
                                                        <p
                                                            style="
                                                                margin: 0;
                                                                font-size: 13px;
                                                                color: #718096;
                                                                line-height: 1.5;
                                                            "
                                                        >
                                                            Email us at
                                                            <a
                                                                href="mailto:support@livinfulfilled.com"
                                                                style="
                                                                color: #48bb78;
                                                                text-decoration: none;
                                                                font-weight: 700;
                                                                "
                                                                >support@livinfulfilled.com</a
                                                            >
                                                        </p>
                                                    </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                        </table>
                                    </td>
                                </tr>
                                <!-- ========== DIVIDER ========== -->
                                <tr>
                                    <td style="background-color: #ffffff; padding: 0 40px">
                                        <hr
                                        style="
                                            border: none;
                                            border-top: 1px solid #e2e8f0;
                                            margin: 0;
                                        "
                                        />
                                    </td>
                                </tr>
                                <!-- ========== STEPS TO FOLLOW ========== -->
                                <tr>
                                    <td
                                        style="
                                        background-color: #ffffff;
                                        padding: 25px 40px 5px 40px;
                                        "
                                    >
                                        <h2
                                        style="
                                            margin: 0 0 18px 0;
                                            font-family: Georgia, &quot;Times New Roman&quot;,
                                                Times, serif;
                                            font-size: 20px;
                                            font-weight: 700;
                                            color: #2d3748;
                                        "
                                        >
                                        Steps to Follow
                                        </h2>
                                    </td>
                                </tr>
                                <!-- Step 1: Download -->
                                <tr>
                                    <td
                                        style="
                                        background-color: #ffffff;
                                        padding: 0 40px 12px 40px;
                                        "
                                    >
                                        <table
                                        role="presentation"
                                        cellspacing="0"
                                        cellpadding="0"
                                        border="0"
                                        width="100%"
                                        >
                                        <tr>
                                            <td
                                                style="
                                                    border-left: 4px solid #48bb78;
                                                    padding: 16px 20px;
                                                    background-color: #f7fafc;
                                                    border-radius: 0 8px 8px 0;
                                                "
                                            >
                                                <p
                                                    style="
                                                    margin: 0 0 4px 0;
                                                    font-size: 15px;
                                                    font-weight: 700;
                                                    color: #2d3748;
                                                    "
                                                >
                                                    1. Download all your program assets
                                                </p>
                                                <p
                                                    style="
                                                    margin: 0;
                                                    font-size: 14px;
                                                    color: #718096;
                                                    line-height: 1.5;
                                                    "
                                                >
                                                    Get instant access to all your resources and
                                                    materials.
                                                </p>
                                            </td>
                                        </tr>
                                        </table>
                                    </td>
                                </tr>
                                <!-- Step 2: Watch Video -->
                                <tr>
                                    <td
                                        style="
                                        background-color: #ffffff;
                                        padding: 0 40px 30px 40px;
                                        "
                                    >
                                        <table
                                        role="presentation"
                                        cellspacing="0"
                                        cellpadding="0"
                                        border="0"
                                        width="100%"
                                        >
                                        <tr>
                                            <td
                                                style="
                                                    border-left: 4px solid #48bb78;
                                                    padding: 16px 20px;
                                                    background-color: #f7fafc;
                                                    border-radius: 0 8px 8px 0;
                                                "
                                            >
                                                <p
                                                    style="
                                                    margin: 0 0 4px 0;
                                                    font-size: 15px;
                                                    font-weight: 700;
                                                    color: #2d3748;
                                                    "
                                                >
                                                    2. Watch the Explainer Video first
                                                </p>
                                                <p
                                                    style="
                                                    margin: 0;
                                                    font-size: 14px;
                                                    color: #718096;
                                                    line-height: 1.5;
                                                    "
                                                >
                                                    The explainer video is essential. It walks
                                                    you through the entire program and explains
                                                    exactly how to use each resource, what to
                                                    start with, and how everything fits
                                                    together.
                                                </p>
                                            </td>
                                        </tr>
                                        </table>
                                    </td>
                                </tr>
                                <!-- ========== DIVIDER ========== -->
                                <tr>
                                    <td style="background-color: #ffffff; padding: 0 40px">
                                        <hr
                                        style="
                                            border: none;
                                            border-top: 1px solid #e2e8f0;
                                            margin: 0;
                                        "
                                        />
                                    </td>
                                </tr>
                                <!-- ========== WHAT YOU RECEIVE ========== -->
                                <tr>
                                    <td
                                        style="
                                        background-color: #ffffff;
                                        padding: 25px 40px 10px 40px;
                                        "
                                    >
                                        <h2
                                        style="
                                            margin: 0 0 18px 0;
                                            font-family: Georgia, &quot;Times New Roman&quot;,
                                                Times, serif;
                                            font-size: 20px;
                                            font-weight: 700;
                                            color: #2d3748;
                                        "
                                        >
                                        What You Receive in This Program
                                        </h2>
                                    </td>
                                </tr>
                                <!-- Checklist Items -->
                                <tr>
                                    <td
                                        style="
                                        background-color: #ffffff;
                                        padding: 0 40px 6px 40px;
                                        "
                                    >
                                        <table
                                        role="presentation"
                                        cellspacing="0"
                                        cellpadding="0"
                                        border="0"
                                        width="100%"
                                        >
                                        <tr>
                                            <td
                                                width="28"
                                                valign="middle"
                                                style="padding-right: 12px"
                                            >
                                                <img
                                                    src="https://livinfulfilled.com/wp-content/uploads/2025/08/circle-tick-icon.svg"
                                                    alt="✓"
                                                    width="22"
                                                    height="22"
                                                    style="display: block"
                                                />
                                            </td>
                                            <td
                                                valign="middle"
                                                style="
                                                    font-size: 15px;
                                                    color: #4a5568;
                                                    line-height: 1.5;
                                                    padding: 8px 0;
                                                "
                                            >
                                                18 Comprehensive Modules (In 5 Booklets)
                                            </td>
                                        </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td
                                        style="
                                        background-color: #ffffff;
                                        padding: 0 40px 6px 40px;
                                        "
                                    >
                                        <table
                                        role="presentation"
                                        cellspacing="0"
                                        cellpadding="0"
                                        border="0"
                                        width="100%"
                                        >
                                        <tr>
                                            <td
                                                width="28"
                                                valign="middle"
                                                style="padding-right: 12px"
                                            >
                                                <img
                                                    src="https://livinfulfilled.com/wp-content/uploads/2025/08/circle-tick-icon.svg"
                                                    alt="✓"
                                                    width="22"
                                                    height="22"
                                                    style="display: block"
                                                />
                                            </td>
                                            <td
                                                valign="middle"
                                                style="
                                                    font-size: 15px;
                                                    color: #4a5568;
                                                    line-height: 1.5;
                                                    padding: 8px 0;
                                                "
                                            >
                                                Monthly Life Plan + Vision Board System
                                            </td>
                                        </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td
                                        style="
                                        background-color: #ffffff;
                                        padding: 0 40px 6px 40px;
                                        "
                                    >
                                        <table
                                        role="presentation"
                                        cellspacing="0"
                                        cellpadding="0"
                                        border="0"
                                        width="100%"
                                        >
                                        <tr>
                                            <td
                                                width="28"
                                                valign="middle"
                                                style="padding-right: 12px"
                                            >
                                                <img
                                                    src="https://livinfulfilled.com/wp-content/uploads/2025/08/circle-tick-icon.svg"
                                                    alt="✓"
                                                    width="22"
                                                    height="22"
                                                    style="display: block"
                                                />
                                            </td>
                                            <td
                                                valign="middle"
                                                style="
                                                    font-size: 15px;
                                                    color: #4a5568;
                                                    line-height: 1.5;
                                                    padding: 8px 0;
                                                "
                                            >
                                                Video Walkthrough (Explainer Video)
                                            </td>
                                        </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td
                                        style="
                                        background-color: #ffffff;
                                        padding: 0 40px 6px 40px;
                                        "
                                    >
                                        <table
                                        role="presentation"
                                        cellspacing="0"
                                        cellpadding="0"
                                        border="0"
                                        width="100%"
                                        >
                                        <tr>
                                            <td
                                                width="28"
                                                valign="middle"
                                                style="padding-right: 12px"
                                            >
                                                <img
                                                    src="https://livinfulfilled.com/wp-content/uploads/2025/08/circle-tick-icon.svg"
                                                    alt="✓"
                                                    width="22"
                                                    height="22"
                                                    style="display: block"
                                                />
                                            </td>
                                            <td
                                                valign="middle"
                                                style="
                                                    font-size: 15px;
                                                    color: #4a5568;
                                                    line-height: 1.5;
                                                    padding: 8px 0;
                                                "
                                            >
                                                Clifton Strengths Tools &amp; Updates
                                            </td>
                                        </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td
                                        style="
                                        background-color: #ffffff;
                                        padding: 0 40px 6px 40px;
                                        "
                                    >
                                        <table
                                        role="presentation"
                                        cellspacing="0"
                                        cellpadding="0"
                                        border="0"
                                        width="100%"
                                        >
                                        <tr>
                                            <td
                                                width="28"
                                                valign="middle"
                                                style="padding-right: 12px"
                                            >
                                                <img
                                                    src="https://livinfulfilled.com/wp-content/uploads/2025/08/circle-tick-icon.svg"
                                                    alt="✓"
                                                    width="22"
                                                    height="22"
                                                    style="display: block"
                                                />
                                            </td>
                                            <td
                                                valign="middle"
                                                style="
                                                    font-size: 15px;
                                                    color: #4a5568;
                                                    line-height: 1.5;
                                                    padding: 8px 0;
                                                "
                                            >
                                                Living Fulfilled Score Card
                                            </td>
                                        </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td
                                        style="
                                        background-color: #ffffff;
                                        padding: 0 40px 6px 40px;
                                        "
                                    >
                                        <table
                                        role="presentation"
                                        cellspacing="0"
                                        cellpadding="0"
                                        border="0"
                                        width="100%"
                                        >
                                        <tr>
                                            <td
                                                width="28"
                                                valign="middle"
                                                style="padding-right: 12px"
                                            >
                                                <img
                                                    src="https://livinfulfilled.com/wp-content/uploads/2025/08/circle-tick-icon.svg"
                                                    alt="✓"
                                                    width="22"
                                                    height="22"
                                                    style="display: block"
                                                />
                                            </td>
                                            <td
                                                valign="middle"
                                                style="
                                                    font-size: 15px;
                                                    color: #4a5568;
                                                    line-height: 1.5;
                                                    padding: 8px 0;
                                                "
                                            >
                                                One Page Life Blueprint
                                            </td>
                                        </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td
                                        style="
                                        background-color: #ffffff;
                                        padding: 0 40px 25px 40px;
                                        "
                                    >
                                        <table
                                        role="presentation"
                                        cellspacing="0"
                                        cellpadding="0"
                                        border="0"
                                        width="100%"
                                        >
                                        <tr>
                                            <td
                                                width="28"
                                                valign="middle"
                                                style="padding-right: 12px"
                                            >
                                                <img
                                                    src="https://livinfulfilled.com/wp-content/uploads/2025/08/circle-tick-icon.svg"
                                                    alt="✓"
                                                    width="22"
                                                    height="22"
                                                    style="display: block"
                                                />
                                            </td>
                                            <td
                                                valign="middle"
                                                style="
                                                    font-size: 15px;
                                                    color: #4a5568;
                                                    line-height: 1.5;
                                                    padding: 8px 0;
                                                "
                                            >
                                                Gallup Balcony and Basement PDF
                                            </td>
                                        </tr>
                                        </table>
                                    </td>
                                </tr>
                                <!-- ========== ENCOURAGEMENT BOX ========== -->
                                <tr>
                                    <td
                                        style="
                                        background-color: #ffffff;
                                        padding: 0 40px 30px 40px;
                                        "
                                    >
                                        <table
                                        role="presentation"
                                        cellspacing="0"
                                        cellpadding="0"
                                        border="0"
                                        width="100%"
                                        >
                                        <tr>
                                            <td
                                                style="
                                                    background-color: #f7fafc;
                                                    border: 1px solid #e2e8f0;
                                                    border-radius: 8px;
                                                    padding: 22px 24px;
                                                "
                                            >
                                                <p
                                                    style="
                                                    margin: 0 0 14px 0;
                                                    font-size: 14px;
                                                    color: #4a5568;
                                                    line-height: 1.7;
                                                    "
                                                >
                                                    You may complete this program at your own
                                                    pace &mdash; in five hours, five days, five
                                                    weeks, or five months. There is no rush.
                                                    <span
                                                    style="color: #48bb78; font-weight: 600"
                                                    >This is your journey.</span
                                                    >
                                                </p>
                                                <p
                                                    style="
                                                    margin: 0;
                                                    font-size: 14px;
                                                    color: #4a5568;
                                                    line-height: 1.7;
                                                    "
                                                >
                                                    You\'ll be guided on how to complete your
                                                    Living Fulfilled Scorecard and Life Plan
                                                    Spreadsheet inside the explainer video. You
                                                    may choose to share your results with a
                                                    coach, employer, therapist, or loved ones if
                                                    you wish.
                                                </p>
                                            </td>
                                        </tr>
                                        </table>
                                    </td>
                                </tr>
                                <!-- ========== DIVIDER ========== -->
                                <tr>
                                    <td style="background-color: #ffffff; padding: 0 40px">
                                        <hr
                                        style="
                                            border: none;
                                            border-top: 1px solid #e2e8f0;
                                            margin: 0;
                                        "
                                        />
                                    </td>
                                </tr>
                                <!-- ========== CLOSING MESSAGE ========== -->
                                <tr>
                                    <td
                                        style="
                                        background-color: #ffffff;
                                        padding: 30px 40px 10px 40px;
                                        text-align: center;
                                        "
                                    >
                                        <p
                                        style="
                                            margin: 0 0 20px 0;
                                            font-size: 16px;
                                            color: #2d3748;
                                            line-height: 1.6;
                                        "
                                        >
                                        You\'ve made a powerful choice. We\'re honoured to walk
                                        this journey with you.
                                        </p>
                                        <p
                                        style="
                                            margin: 0 0 6px 0;
                                            font-size: 15px;
                                            color: #718096;
                                            line-height: 1.5;
                                        "
                                        >
                                        With warmth,
                                        </p>
                                        <p
                                        style="
                                            margin: 0 0 20px 0;
                                            font-size: 17px;
                                            font-weight: 700;
                                            color: #2d3748;
                                        "
                                        >
                                        Living Fulfilled
                                        </p>
                                    </td>
                                </tr>
                                <!-- ========== FOOTER LOGO ========== -->
                                <tr>
                                    <td
                                        style="
                                        background-color: #ffffff;
                                        padding: 0 40px 25px 40px;
                                        text-align: center;
                                        "
                                    >
                                        <img
                                        src="https://livinfulfilled.com/wp-content/uploads/2025/09/logo-1.png"
                                        alt="Living Fulfilled"
                                        width="140"
                                        style="
                                            display: inline-block;
                                            max-width: 140px;
                                            height: auto;
                                            opacity: 0.7;
                                        "
                                        />
                                    </td>
                                </tr>
                                <!-- ========== COPYRIGHT FOOTER ========== -->
                                <tr>
                                    <td
                                        style="
                                        background-color: #f7fafc;
                                        border-radius: 0 0 8px 8px;
                                        padding: 20px 40px;
                                        text-align: center;
                                        border-top: 1px solid #e2e8f0;
                                        "
                                    >
                                        <p
                                        style="
                                            margin: 0;
                                            font-size: 12px;
                                            color: #a0aec0;
                                            line-height: 1.5;
                                        "
                                        >
                                        &copy; 2026 Living Fulfilled. All rights reserved.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            <!-- End Email Container -->
                            </td>
                        </tr>
                    </table>
                </body>
                </html>
                ';
            Debugbar::info('Attempting to send Gmail SMTP mail', ['email' => $user->email]);
            Debugbar::info('Attempting to send Gmail SMTP mail', ['body' => $body]);
            Mail::send([], [], function ($message) use ($user, $subject, $body) {
                $message->to($user->email)
                    ->subject($subject)
                    ->html($body);
            });
            if (count(Mail::failures()) > 0) {
                Debugbar::error('Mail sending failed', ['failures' => Mail::failures()]);
            } else {
                Debugbar::info('Gmail SMTP mail sent successfully', ['email' => $user->email]);
            }
        } catch (\Exception $ex) {
            Debugbar::error('Error sending welcome email via Gmail SMTP: ' . $ex->getMessage());
        }

        // Also create in WordPress
        try {
            $wpResponse = Http::withBasicAuth(
                    env('WP_ADMIN_USER'),
                    env('WP_APP_PASSWORD')
                )->post(env('WP_SITE_URL') . '/wp-json/wp/v2/users', [
                    'username' => Str::before($user->email, '@'),
                    'email'    => $user->email,
                    'password' => $pass,
                    'roles'    => ['member']
                ]);

            if ($wpResponse->failed()) {
                Debugbar::error('WP user creation failed'. json_encode($wpResponse->body()));
            }
        } catch (\Exception $ex) {
            Debugbar::error('Error creating WP user: ' . $ex->getMessage());
        }
    }

    
    public function userPayments($userId)
    {
        $user = User::with('payments')->findOrFail($userId);

        return response()->json([
            'success' => true,
            'user' => $user->only(['id', 'name', 'email']),
            'payments' => $user->payments
        ]);
    }
}

