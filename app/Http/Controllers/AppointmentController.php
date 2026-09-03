<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use Barryvdh\Debugbar\Facades\Debugbar;

class AppointmentController extends Controller
{
    /**
     * Display a listing of the appointments.
     */
    public function index(): JsonResponse
    {
        $appointments = Appointment::all();
        return response()->json($appointments);
    }

    /**
     * Store a newly created appointment in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'payload' => 'required|array',
            'is_active' => 'boolean',
        ]);

        $appointment = Appointment::create($validated);

        //get the send_email value from the request, default to false if not provided
        $send_email = $request->input('send_email', false);
        if($send_email){
            $this->sendEmail($appointment->email, $appointment->payload);
        }

        return response()->json($appointment, 201);
    }

    private function sendEmail($email, $payload){
        // Send Welcome Email using Gmail SMTP
        try {
            $subject = 'Booking Confirmation - Livin Fulfilled';
            //$body = "<p>Hi {$user->name},</p><p>Your account has been created successfully!</p><p>Password: {$pass}</p>";
            
            //$payload = json_decode($payload, true);

            // Assuming $payload contains the decoded JSON (array)
            $USER_NAME = $payload['userDetails']['name'];
            $EMAIL = $payload['userDetails']['email'];

            // Group session time
            $GROUP_DATE = $payload['groupCoaching']['selectedSlot']['date'];
            $GROUP_TIME = $payload['groupCoaching']['selectedSlot']['time'];
            $GROUP_LOCAL_TIME = $payload['groupCoaching']['selectedSlot']['time_local'];
            $GROUP_AEST_TIME = $GROUP_DATE . ' ' . $GROUP_TIME; // You can adjust this if you need to convert timezone

            // One-on-one sessions
            $oneOnOneSlots = $payload['oneOnOneCoaching']['selectedSlots'];

            // SESSION 1
            $SESSION_1_DATE = $oneOnOneSlots[0]['date'] ?? null;
            $SESSION_1_TIME = $oneOnOneSlots[0]['time'] ?? null;
            $SESSION_1_LOCAL_TIME = $oneOnOneSlots[0]['time_local'] ?? null;
            $SESSION_1_AEST_TIME = $SESSION_1_DATE . ' ' . $SESSION_1_TIME;

            // SESSION 2
            $SESSION_2_DATE = $oneOnOneSlots[1]['date'] ?? null;
            $SESSION_2_TIME = $oneOnOneSlots[1]['time'] ?? null;
            $SESSION_2_LOCAL_TIME = $oneOnOneSlots[1]['time_local'] ?? null;
            $SESSION_2_AEST_TIME = $SESSION_2_DATE . ' ' . $SESSION_2_TIME;

            // SESSION 3
            $SESSION_3_DATE = $oneOnOneSlots[2]['date'] ?? null;
            $SESSION_3_TIME = $oneOnOneSlots[2]['time'] ?? null;
            $SESSION_3_LOCAL_TIME = $oneOnOneSlots[2]['time_local'] ?? null;
            $SESSION_3_AEST_TIME = $SESSION_3_DATE . ' ' . $SESSION_3_TIME;

            // SESSION 4
            $SESSION_4_DATE = $oneOnOneSlots[3]['date'] ?? null;
            $SESSION_4_TIME = $oneOnOneSlots[3]['time'] ?? null;
            $SESSION_4_LOCAL_TIME = $oneOnOneSlots[3]['time_local'] ?? null;
            $SESSION_4_AEST_TIME = $SESSION_4_DATE . ' ' . $SESSION_4_TIME;

            // SESSION 5
            $SESSION_5_DATE = $oneOnOneSlots[4]['date'] ?? null;
            $SESSION_5_TIME = $oneOnOneSlots[4]['time'] ?? null;
            $SESSION_5_LOCAL_TIME = $oneOnOneSlots[4]['time_local'] ?? null;
            $SESSION_5_AEST_TIME = $SESSION_5_DATE . ' ' . $SESSION_5_TIME;


            $body = '<!DOCTYPE html>
                <html lang="en">
                <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Booking Confirmation - Livin Fulfilled</title>
                </head>
                <body style="margin: 0; padding: 0; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif; background-color: #f5f5f5; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;">
                
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #f5f5f5; padding: 20px 0;">
                    <tr>
                    <td align="center">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width: 600px; background-color: #ffffff; margin: 0 auto;">
                        
                        <!-- Header -->
						  <tr>
							<td style="background: #f2f2f2; padding: 40px 30px; text-align: center;">
							  <img src="https://livinfulfilled.com/wp-content/uploads/2025/09/logo__1_-removebg-preview.png" alt="Livin Fulfilled Logo" style="max-width: 180px; height: auto; margin-bottom: 20px; display: block; margin-left: auto; margin-right: auto;">
							  
							  <div style="width: 60px; height: 60px; background-color: #4caf50; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin: 20px 0 10px;">
								<span style="color: #fff; font-size: 36px; width:60px; height:60px; font-weight: bold; line-height: 0px; display:grid; place-content:center; padding-top:30px;">✓</span>
							  </div>
							  
							  <h1 style="color: #4caf50; font-size: 28px; font-weight: bold; margin: 10px 0 0 0; text-shadow: 0 2px 4px rgba(0,0,0,0.1);">Booking Confirmed!</h1>
							</td>
						  </tr>
                        
                        <!-- Content -->
                        <tr>
                            <td style="padding: 40px 30px;">
                            
                            <p style="font-size: 18px; color: #333333; margin: 0 0 20px 0; line-height: 1.6;">
                                Hi <strong>'.$USER_NAME.'</strong>,
                            </p>
                            
                            <p style="font-size: 16px; color: #333; line-height: 1.6; margin: 0 0 20px 0;">
                                Thank you for booking your Group Briefing and 1:1 Coaching Sessions with <strong>Sat Lunasky</strong>! We are excited to begin this transformative journey with you.
                            </p>
                            
                            <p style="font-size: 16px; color: #333; line-height: 1.6; margin: 0 0 30px 0;">
                                Below are all the details for your upcoming sessions:
                            </p>
                            
                            <!-- Group Briefing Session -->
                            <h2 style="font-size: 20px; font-weight: bold; color: #1976d2; margin: 30px 0 15px; padding-bottom: 10px; border-bottom: 3px solid #1976d2;">
                                Group Briefing Session
                            </h2>
                            
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border-left: 4px solid #1976d2; border-radius: 8px; margin-bottom: 20px;">
                                <tr>
                                <td style="padding: 20px;">
                                    <div style="font-size: 16px; font-weight: bold; color: #1976d2; margin: 0 0 15px 0;">
                                    Group Briefing with Sat Lunasky
                                    </div>
                                    
                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: rgba(255,255,255,0.7); border-radius: 6px; margin-bottom: 8px;">
                                    <tr>
                                        <td style="padding: 12px 15px;">
                                        <div style="font-size: 14px; color: #555; margin-bottom: 8px;">
                                            <strong style="color: #1976d2;">📅 Your Local Time:</strong>
                                        </div>
                                        <div style="font-size: 16px; font-weight: bold; color: #333;">
                                            '.$GROUP_LOCAL_TIME.'
                                        </div>
                                        </td>
                                    </tr>
                                    </table>
                                    
                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: rgba(255,255,255,0.5); border-radius: 6px;">
                                    <tr>
                                        <td style="padding: 12px 15px;">
                                        <div style="font-size: 12px; color: #666; margin-bottom: 4px;">
                                            <strong>AEST Time:</strong>
                                        </div>
                                        <div style="font-size: 14px; color: #555;">
                                            '.$GROUP_AEST_TIME.'
                                        </div>
                                        </td>
                                    </tr>
                                    </table>
                                    
                                    <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(0,0,0,0.1);">
                                    <div style="font-size: 13px; color: #555; margin: 4px 0;">
                                        <strong>⏱️ Duration:</strong> 1 hour
                                    </div>
                                    <div style="font-size: 13px; color: #555; margin: 4px 0;">
                                        <strong>📍 Location:</strong> Online (Google Meet)
                                    </div>
                                    </div>
                                </td>
                                </tr>
                            </table>
                            
                            <!-- 1:1 Briefing Sessions -->
                            <h2 style="font-size: 20px; font-weight: bold; color: #63b59d; margin: 40px 0 15px; padding-bottom: 10px; border-bottom: 3px solid #63b59d;">
                                1:1 Coaching Sessions (5 Sessions)
                            </h2>
                            
                            <!-- Session 1 -->
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background: linear-gradient(135deg, #d4f1e8 0%, #b8e6d5 100%); border-left: 4px solid #63b59d; border-radius: 8px; margin-bottom: 15px;">
                                <tr>
                                <td style="padding: 20px;">
                                    <div style="font-size: 16px; font-weight: bold; color: #63b59d; margin: 0 0 15px 0;">
                                    1:1 Coaching Session with Sat Lunasky (Session 1)
                                    </div>
                                    
                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: rgba(255,255,255,0.7); border-radius: 6px; margin-bottom: 8px;">
                                    <tr>
                                        <td style="padding: 12px 15px;">
                                        <div style="font-size: 14px; color: #555; margin-bottom: 8px;">
                                            <strong style="color: #63b59d;">📅 Your Local Time:</strong>
                                        </div>
                                        <div style="font-size: 16px; font-weight: bold; color: #333;">
                                            '.$SESSION_1_LOCAL_TIME.'
                                        </div>
                                        </td>
                                    </tr>
                                    </table>
                                    
                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: rgba(255,255,255,0.5); border-radius: 6px;">
                                    <tr>
                                        <td style="padding: 12px 15px;">
                                        <div style="font-size: 12px; color: #666; margin-bottom: 4px;">
                                            <strong>AEST Time:</strong>
                                        </div>
                                        <div style="font-size: 14px; color: #555;">
                                            '.$SESSION_1_AEST_TIME.'
                                        </div>
                                        </td>
                                    </tr>
                                    </table>
                                    
                                    <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(0,0,0,0.1);">
                                    <div style="font-size: 13px; color: #555; margin: 4px 0;">
                                        <strong>⏱️ Duration:</strong> 1 hour
                                    </div>
                                    <div style="font-size: 13px; color: #555; margin: 4px 0;">
                                        <strong>📍 Location:</strong> Online (Google Meet)
                                    </div>
                                    </div>
                                </td>
                                </tr>
                            </table>
                            
                            <!-- Session 2 -->
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background: linear-gradient(135deg, #d4f1e8 0%, #b8e6d5 100%); border-left: 4px solid #63b59d; border-radius: 8px; margin-bottom: 15px;">
                                <tr>
                                <td style="padding: 20px;">
                                    <div style="font-size: 16px; font-weight: bold; color: #63b59d; margin: 0 0 15px 0;">
                                    1:1 Coaching Session with Sat Lunasky (Session 2)
                                    </div>
                                    
                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: rgba(255,255,255,0.7); border-radius: 6px; margin-bottom: 8px;">
                                    <tr>
                                        <td style="padding: 12px 15px;">
                                        <div style="font-size: 14px; color: #555; margin-bottom: 8px;">
                                            <strong style="color: #63b59d;">📅 Your Local Time:</strong>
                                        </div>
                                        <div style="font-size: 16px; font-weight: bold; color: #333;">
                                            '.$SESSION_2_LOCAL_TIME.'
                                        </div>
                                        </td>
                                    </tr>
                                    </table>
                                    
                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: rgba(255,255,255,0.5); border-radius: 6px;">
                                    <tr>
                                        <td style="padding: 12px 15px;">
                                        <div style="font-size: 12px; color: #666; margin-bottom: 4px;">
                                            <strong>AEST Time:</strong>
                                        </div>
                                        <div style="font-size: 14px; color: #555;">
                                            '.$SESSION_2_AEST_TIME.'
                                        </div>
                                        </td>
                                    </tr>
                                    </table>
                                    
                                    <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(0,0,0,0.1);">
                                    <div style="font-size: 13px; color: #555; margin: 4px 0;">
                                        <strong>⏱️ Duration:</strong> 1 hour
                                    </div>
                                    <div style="font-size: 13px; color: #555; margin: 4px 0;">
                                        <strong>📍 Location:</strong> Online (Google Meet)
                                    </div>
                                    </div>
                                </td>
                                </tr>
                            </table>
                            
                            <!-- Session 3 -->
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background: linear-gradient(135deg, #d4f1e8 0%, #b8e6d5 100%); border-left: 4px solid #63b59d; border-radius: 8px; margin-bottom: 15px;">
                                <tr>
                                <td style="padding: 20px;">
                                    <div style="font-size: 16px; font-weight: bold; color: #63b59d; margin: 0 0 15px 0;">
                                    1:1 Coaching Session with Sat Lunasky (Session 3)
                                    </div>
                                    
                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: rgba(255,255,255,0.7); border-radius: 6px; margin-bottom: 8px;">
                                    <tr>
                                        <td style="padding: 12px 15px;">
                                        <div style="font-size: 14px; color: #555; margin-bottom: 8px;">
                                            <strong style="color: #63b59d;">📅 Your Local Time:</strong>
                                        </div>
                                        <div style="font-size: 16px; font-weight: bold; color: #333;">
                                            '.$SESSION_3_LOCAL_TIME.'
                                        </div>
                                        </td>
                                    </tr>
                                    </table>
                                    
                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: rgba(255,255,255,0.5); border-radius: 6px;">
                                    <tr>
                                        <td style="padding: 12px 15px;">
                                        <div style="font-size: 12px; color: #666; margin-bottom: 4px;">
                                            <strong>AEST Time:</strong>
                                        </div>
                                        <div style="font-size: 14px; color: #555;">
                                            '.$SESSION_3_AEST_TIME.'
                                        </div>
                                        </td>
                                    </tr>
                                    </table>
                                    
                                    <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(0,0,0,0.1);">
                                    <div style="font-size: 13px; color: #555; margin: 4px 0;">
                                        <strong>⏱️ Duration:</strong> 1 hour
                                    </div>
                                    <div style="font-size: 13px; color: #555; margin: 4px 0;">
                                        <strong>📍 Location:</strong> Online (Google Meet)
                                    </div>
                                    </div>
                                </td>
                                </tr>
                            </table>
                            
                            <!-- Session 4 -->
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background: linear-gradient(135deg, #d4f1e8 0%, #b8e6d5 100%); border-left: 4px solid #63b59d; border-radius: 8px; margin-bottom: 15px;">
                                <tr>
                                <td style="padding: 20px;">
                                    <div style="font-size: 16px; font-weight: bold; color: #63b59d; margin: 0 0 15px 0;">
                                    1:1 Coaching Session with Sat Lunasky (Session 4)
                                    </div>
                                    
                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: rgba(255,255,255,0.7); border-radius: 6px; margin-bottom: 8px;">
                                    <tr>
                                        <td style="padding: 12px 15px;">
                                        <div style="font-size: 14px; color: #555; margin-bottom: 8px;">
                                            <strong style="color: #63b59d;">📅 Your Local Time:</strong>
                                        </div>
                                        <div style="font-size: 16px; font-weight: bold; color: #333;">
                                            '.$SESSION_4_LOCAL_TIME.'
                                        </div>
                                        </td>
                                    </tr>
                                    </table>
                                    
                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: rgba(255,255,255,0.5); border-radius: 6px;">
                                    <tr>
                                        <td style="padding: 12px 15px;">
                                        <div style="font-size: 12px; color: #666; margin-bottom: 4px;">
                                            <strong>AEST Time:</strong>
                                        </div>
                                        <div style="font-size: 14px; color: #555;">
                                            '.$SESSION_4_AEST_TIME.'
                                        </div>
                                        </td>
                                    </tr>
                                    </table>
                                    
                                    <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(0,0,0,0.1);">
                                    <div style="font-size: 13px; color: #555; margin: 4px 0;">
                                        <strong>⏱️ Duration:</strong> 1 hour
                                    </div>
                                    <div style="font-size: 13px; color: #555; margin: 4px 0;">
                                        <strong>📍 Location:</strong> Online (Google Meet)
                                    </div>
                                    </div>
                                </td>
                                </tr>
                            </table>
                            
                            <!-- Session 5 -->
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background: linear-gradient(135deg, #d4f1e8 0%, #b8e6d5 100%); border-left: 4px solid #63b59d; border-radius: 8px; margin-bottom: 15px;">
                                <tr>
                                <td style="padding: 20px;">
                                    <div style="font-size: 16px; font-weight: bold; color: #63b59d; margin: 0 0 15px 0;">
                                    1:1 Coaching Session with Sat Lunasky (Session 5)
                                    </div>
                                    
                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: rgba(255,255,255,0.7); border-radius: 6px; margin-bottom: 8px;">
                                    <tr>
                                        <td style="padding: 12px 15px;">
                                        <div style="font-size: 14px; color: #555; margin-bottom: 8px;">
                                            <strong style="color: #63b59d;">📅 Your Local Time:</strong>
                                        </div>
                                        <div style="font-size: 16px; font-weight: bold; color: #333;">
                                            '.$SESSION_5_LOCAL_TIME.'
                                        </div>
                                        </td>
                                    </tr>
                                    </table>
                                    
                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: rgba(255,255,255,0.5); border-radius: 6px;">
                                    <tr>
                                        <td style="padding: 12px 15px;">
                                        <div style="font-size: 12px; color: #666; margin-bottom: 4px;">
                                            <strong>AEST Time:</strong>
                                        </div>
                                        <div style="font-size: 14px; color: #555;">
                                            '.$SESSION_5_AEST_TIME.'
                                        </div>
                                        </td>
                                    </tr>
                                    </table>
                                    
                                    <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(0,0,0,0.1);">
                                    <div style="font-size: 13px; color: #555; margin: 4px 0;">
                                        <strong>⏱️ Duration:</strong> 1 hour
                                    </div>
                                    <div style="font-size: 13px; color: #555; margin: 4px 0;">
                                        <strong>📍 Location:</strong> Online (Google Meet)
                                    </div>
                                    </div>
                                </td>
                                </tr>
                            </table>
                            
                            <div style="text-align: center; margin-top: 40px;">
                                <p style="font-size: 16px; color: #333; margin: 0 0 10px 0;">
                                We look forward to supporting you on your growth journey!
                                </p>
                                <p style="font-size: 18px; color: #63b59d; font-weight: bold; margin: 10px 0 0 0;">
                                Living Fulfilled Team
                                </p>
                            </div>
                            
                            </td>
                        </tr>
                        
                        <!-- Footer -->
                        <tr>
                            <td style="background-color: #333333; color: #ffffff; padding: 30px; text-align: center;">
                            <p style="margin: 0 0 10px 0; font-weight: bold; font-size: 14px;">Need Support?</p>
                            <p style="margin: 5px 0;">
                                📧 <a href="mailto:freedom@livinfulfilled.com" style="color: #63b59d; text-decoration: none;">freedom@livinfulfilled.com</a>
                            </p>
                            <p style="margin: 5px 0; color: #999; font-size: 11px;">
                                This email was sent because you booked Group Briefing &amp; 1:1 Coaching Sessions at livinfulfilled.com
                            </p>
                            </td>
                        </tr>
                        
                        </table>
                    </td>
                    </tr>
                </table>
                
                </body>
                </html>';

            Debugbar::info('Attempting to send Gmail SMTP mail', ['email' => $email]);
            Mail::send([], [], function ($message) use ($email, $subject, $body) {
                $message->to($email)
                    ->subject($subject)
                    ->html($body);
            });
            if (count(Mail::failures()) > 0) {
                Debugbar::error('Mail sending failed', ['failures' => Mail::failures()]);
            } else {
                Debugbar::info('Gmail SMTP mail sent successfully', ['email' => $email]);
            }
        } catch (\Exception $ex) {
            Debugbar::error('Error sending welcome email via Gmail SMTP: ' . $ex->getMessage()." Line: ".$ex->getLine());
        }
    }

    /**
     * Display the specified appointment.
     */
    public function show(Appointment $appointment): JsonResponse
    {
        return response()->json($appointment);
    }

    /**
     * Update the specified appointment in storage.
     */
    public function update(Request $request, Appointment $appointment): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'sometimes|required|email',
            'payload' => 'sometimes|required|array',
            'is_active' => 'sometimes|boolean',
        ]);

        $appointment->update($validated);

        //get the send_email value from the request, default to false if not provided
        $send_email = $request->input('send_email', false);
        if($send_email){
            $this->sendEmail($appointment->email, $appointment->payload);
        }

        return response()->json($appointment);
    }

    /**
     * Remove the specified appointment from storage.
     */
    public function destroy(Appointment $appointment): JsonResponse
    {
        $appointment->delete();

        return response()->json(['message' => 'Appointment deleted successfully']);
    }

    /**
     * Get appointments by email.
     */
    public function getByEmail(string $email): JsonResponse
    {
        $appointments = Appointment::where('email', $email)->get();

        return response()->json($appointments);
    }

}
