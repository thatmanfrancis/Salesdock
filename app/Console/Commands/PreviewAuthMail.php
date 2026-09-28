<?php

namespace App\Console\Commands;

use App\Support\AuthMail;
use Illuminate\Console\Command;

class PreviewAuthMail extends Command
{
    protected $signature = 'mail:preview {email? : Inbox that should receive the sample templates}';

    protected $description = 'Send every auth email template to the review inbox';

    public function handle(): int
    {
        $email = $this->argument('email') ?: config('services.zeptomail.review');
        $samples = [
            [
                'Verify your email — SalesDock',
                [
                    'preheader' => 'Confirm your email to finish the SalesDock application.',
                    'heading' => 'Verify your email address',
                    'kicker' => "You're almost there.",
                    'paragraphs' => [
                        'Hi <strong style="color:#111827;">Francis Uzoigwe</strong>, please verify your email address to complete the SalesDock application for <strong style="color:#111827;">Kemi\'s Fashion</strong>.',
                        'We will not review the business until this address is confirmed. The button below is the only step you need to take right now.',
                    ],
                    'url' => url('/verify-email?token=preview'),
                    'label' => 'Verify Email Address',
                    'stepsTitle' => 'What happens next',
                    'steps' => [
                        'Open the verification link. It expires in 24 hours.',
                        'Our team reviews the business, usually within 24–48 hours.',
                        'If it is approved, you get another email with a 72-hour link to choose a plan.',
                    ],
                    'note' => 'If you did not apply for SalesDock, you can ignore this email.',
                ],
            ],
            [
                "We've received your application — SalesDock",
                [
                    'preheader' => 'Your email is verified. The application is now in review.',
                    'heading' => 'Registration received',
                    'kicker' => 'Your email is verified. Here is what we have on file.',
                    'paragraphs' => [
                        'Hi <strong style="color:#111827;">Francis Uzoigwe</strong>, thank you for applying. We have received the application for <strong style="color:#111827;">Kemi\'s Fashion</strong> and the review has started.',
                    ],
                    'summaryTitle' => 'Application summary',
                    'summary' => [
                        'Owner' => 'Francis Uzoigwe',
                        'Business' => "Kemi's Fashion",
                        'Email' => $email,
                        'Status' => 'Waiting for review',
                    ],
                    'steps' => [
                        'Our team reviews the application, usually within 24–48 hours.',
                        'You will get an email when it is approved or if we need a correction.',
                        'After approval, you choose a plan and can sign in.',
                    ],
                    'note' => 'Reply to this email if any of the details above are wrong.',
                ],
            ],
            [
                'Your SalesDock application was approved',
                [
                    'preheader' => 'Choose a plan within 72 hours to open the dashboard.',
                    'heading' => "You're approved",
                    'kicker' => 'SalesDock is ready for the business.',
                    'paragraphs' => [
                        'Hi <strong style="color:#111827;">Francis Uzoigwe</strong>, the application for <strong style="color:#111827;">Kemi\'s Fashion</strong> has been approved.',
                        'The next step is choosing a plan. A free plan opens the dashboard immediately. A paid plan stays pending until Flutterwave confirms the payment.',
                    ],
                    'url' => url('/select-plan?token=preview'),
                    'label' => 'Choose your plan',
                    'note' => 'This link expires in 72 hours. You can return to it if a payment window is closed.',
                ],
            ],
            [
                'Update on your SalesDock application',
                [
                    'preheader' => 'The application was not approved.',
                    'heading' => 'Application not approved',
                    'kicker' => 'Here is the update from the review.',
                    'paragraphs' => [
                        'Hi <strong style="color:#111827;">Francis Uzoigwe</strong>, after reviewing <strong style="color:#111827;">Kemi\'s Fashion</strong>, we are not able to approve it at this time.',
                    ],
                    'summaryTitle' => 'Reason',
                    'summary' => [
                        'Review' => 'The business details need to be corrected before we can continue.',
                    ],
                    'note' => 'You can apply again with the same email after this rejection. Reply to this email if you think the review missed something.',
                ],
            ],
            [
                'Reset your SalesDock password',
                [
                    'preheader' => 'This password link expires in one hour.',
                    'heading' => 'Reset your password',
                    'kicker' => 'Let\'s get you back into the account.',
                    'paragraphs' => [
                        'Hi <strong style="color:#111827;">Francis Uzoigwe</strong>, we received a request to reset the password for this SalesDock account.',
                    ],
                    'url' => url('/reset-password?token=preview'),
                    'label' => 'Reset password',
                    'note' => 'This link expires in 1 hour. If you did not ask for a reset, ignore this email and the password will stay the same.',
                ],
            ],
        ];

        foreach ($samples as [$subject, $mail]) {
            AuthMail::send($email, 'Francis Uzoigwe', '[Review] '.$subject, $mail);
            $this->line('Sent '.$subject);
        }

        $this->info('Review copies sent to '.$email);

        return self::SUCCESS;
    }
}
