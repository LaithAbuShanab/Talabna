<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Password Reset Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are the default lines which match reasons
    | that are given by the password broker for a password update attempt
    | outcome such as failure due to an invalid password / reset token.
    |
    */

    'reset' => 'Your password has been reset.',
    'sent' => 'If an account exists for that email address, we have sent password reset instructions to it.',
    'throttled' => 'Please wait before retrying.',
    'token' => 'This password reset token is invalid.',
    'user' => "We can't find a user with that email address.",

    'reset_subject' => 'Reset your password',
    'reset_intro' => 'You are receiving this email because we received a password reset request for your account.',
    'reset_token' => 'Your password reset token is: :token',
    'reset_expire' => 'This token will expire in :count minutes.',
    'reset_ignore' => 'If you did not request a password reset, no further action is required.',

];
