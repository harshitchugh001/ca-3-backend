<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Config;
use App\Models\EmailRecord;
use App\Models\LinkRecord;

class AuthController extends Controller
{
    public function login()
    {
        $state = bin2hex(random_bytes(12));
        Session::put('state', $state);

        $redirect_uri = route('authorize');

        $oauthSettings = Config::get('services.microsoft');

        return Redirect::to(
            $oauthSettings['authorize_url'] .
            '?client_id=' . $oauthSettings['client_id'] .
            '&redirect_uri=' . $redirect_uri .
            '&state=' . $state
        );
    }

    public function authorize(Request $request)
    {
        try {
            // Get the authorization code
            $code = $request->query('code');
            $state = $request->query('state');

            if (!$code || !$state || $state !== Session::get('state')) {
                return "Invalid or missing parameters. Possible CSRF attack.";
            }

            $oauthSettings = Config::get('services.microsoft');

            $token_params = [
                'client_id' => $oauthSettings['client_id'],
                'client_secret' => $oauthSettings['client_secret'],
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => route('authorize'),
            ];

            $token_response = Http::asForm()->post($oauthSettings['token_url'], $token_params);

            if ($token_response->status() == 200) {
                $token_data = $token_response->json();
                $access_token = $token_data['access_token'];

                Session::put('access_token', $access_token);

                return Redirect::to("http://localhost:3000/work/{$access_token}");
            } else {
                return "Error retrieving access token: " . $token_response->body();
            }
        } catch (\Exception $e) {
            \Log::error("Error during token retrieval: " . $e->getMessage());
            return "Error: " . $e->getMessage();
        }
    }

    public function protected_route(Request $request)
    {
        try {
            $access_token = $request->json('access_token');

            if (!$access_token) {
                return response('Access token is missing', 400);
            }

            $graph_api_url = 'https://graph.microsoft.com/v1.0/me/mailFolders/Inbox/messages';

            $headers = [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ];

            $response = Http::withHeaders($headers)->get($graph_api_url);

            if ($response->status() == 200) {
                $emails = $response->json('value', []);
                return response()->json($emails);
            } else {
                return 'Error retrieving emails: ' . $response->body();
            }
        } catch (\Exception $e) {
            \Log::error("Error during email retrieval: " . $e->getMessage());
            return "Error: " . $e->getMessage();
        }
    }

    public function send_email(Request $request)
    {
        try {
            $access_token = $request->json('accessToken');
            $email_data = $request->json('emailData');

            // Extract the email body from the email data
            $email_body = $email_data['body'];
            $urls = preg_match_all('/http[s]?:\/\/(?:[a-zA-Z]|[0-9]|[$-_@.&+]|[!*\\(\\),]|(?:%[0-9a-fA-F][0-9a-fA-F]))+/', $email_body, $matches);

            $link = false;
            if ($urls) {
                $link = true;
                $token_generate = bin2hex(random_bytes(8));
                foreach ($matches[0] as $url) {
                    $email_body = str_replace($url, 'http://localhost:5000/custom-redirect/?original_url=' . $url . '&token=' . $token_generate, $email_body);
                }
            }

            $tracking_pixel_url = 'https://1ac1-210-89-61-6.ngrok-free.app/track-pixel/' . $token_generate;
            $email_body .= '<p><img src="' . $tracking_pixel_url . '" alt="ahds" width="1" height="1" /></p>';

            $headers = [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ];

            $email_request = [
                'message' => [
                    'subject' => $email_data['subject'],
                    'body' => [
                        'contentType' => 'HTML',
                        'content' => '<html><body>' . $email_body . '</body></html>',
                    ],
                    'toRecipients' => [
                        [
                            'emailAddress' => [
                                'address' => $email_data['recipientEmail'],
                            ],
                        ],
                    ],
                    'isReadReceiptRequested' => 'true',
                ],
                'saveToSentItems' => 'true',
            ];

            $graph_send_email_url = '<YOUR_GRAPH_SEND_EMAIL_URL>'; 
            $response = Http::withHeaders($headers)->post($graph_send_email_url, $email_request);

            $user_response = Http::withHeaders($headers)->get('https://graph.microsoft.com/v1.0/me');

            if ($user_response->status() == 200) {
                $user_info = $user_response->json();
                $current_user_email = $user_info['mail'];

                // Save email data and current user's email to the database
                $email_record = new EmailRecord();
                $email_record->sender_email = $current_user_email;
                $email_record->receiver_email = $email_data['recipientEmail'];
                $email_record->link = $link;
                $email_record->read = false;
                $email_record->link_present = $link;
                $email_record->token = $token_generate;
                $email_record->save();

                return response()->json(['message' => 'Email sent successfully']);
            } else {
                return 'Error sending email: ' . $user_response->body();
            }
        } catch (\Exception $e) {
            \Log::error("Error during email sending: " . $e->getMessage());
            return "Error: " . $e->getMessage();
        }
    }

    public function custom_redirect(Request $request)
    {
        $original_url = $request->input('original_url');
        $token = $request->input('token');

        $link_record = LinkRecord::where('token', $token)->first();

        if ($link_record) {
            $link_record->link_click = true;
            $link_record->number_of_times_link_click += 1;
            $link_record->save();
        } else {
            $email_record = EmailRecord::where('token', $token)->first();
            if ($email_record) {
                $link_record = new LinkRecord();
                $link_record->token = $token;
                $link_record->link_click = true;
                $link_record->number_of_times_link_click = 1;
                $link_record->email_record_id = $email_record->id;
                $link_record->save();
            }
        }

        return Redirect::to($original_url);
    }

    public function track_pixel(Request $request, $token)
    {
        $email_record = EmailRecord::where('token', $token)->first();

        if ($email_record) {
            $email_record->read = true;
            $email_record->save();

           
            \Log::info("Email with token $token has been opened.");

            $pixel = hex2bin('47494638396101000100800000FF00FF00FF000000000021F904010000002C00000000010001000002024401');

            return response($pixel)->header('Content-Type', 'image/gif');
        } else {
            return "Email not found for the given token";
        }
    }

    public function get_email_details()
    {
        $email_records = EmailRecord::all();
        $email_data = [];

        foreach ($email_records as $record) {
            $link_open_time = null;
            $link_open_count = null;

            if ($record->link_present && $record->link_records->count() > 0) {
                $link_record = $record->link_records->first();
                $link_open_time = $link_record->open_time;
                $link_open_count = $link_record->number_of_times_link_click;
            }

            $email_data[] = [
                'id' => $record->id,
                'senderEmail' => $record->sender_email,
                'receiverEmail' => $record->receiver_email,
                'link' => $record->link,
                'mailSentTime' => $record->Email_send_time->format('Y-m-d H:i:s'),
                'linkPresent' => $record->link_present,
                'mailRead' => $record->read,
                'linkOpenTime' => $link_open_time ? $link_open_time->format('Y-m-d H:i:s') : null,
                'linkOpenCount' => $link_open_count !== null ? $link_open_count : null,
            ];
        }

        return response()->json($email_data);
    }
}
