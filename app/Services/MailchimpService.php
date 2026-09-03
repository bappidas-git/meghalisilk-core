<?php

namespace App\Services;

use MailchimpTransactional\ApiClient;

class MailchimpService
{
    protected $client;

    public function __construct()
    {
        $this->client = new ApiClient();
        $this->client->setApiKey(config('services.mailchimp.transactional_key'));
    }

    public function sendEmail($toEmail, $toName, $subject, $htmlContent)
    {
        return $this->client->messages->send([
            "message" => [
                "from_email" => "no-reply@livinfulfilled.com",
                "from_name"  => "Freedom | Livinfulfilled",
                "subject"    => $subject,
                "html"       => $htmlContent,
                "to" => [
                    [
                        "email" => $toEmail,
                        "name"  => $toName,
                        "type"  => "to"
                    ]
                ]
            ]
        ]);
    }
}
