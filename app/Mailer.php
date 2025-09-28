<?php

namespace App;

class Mailer
{
    public static function send(string $to, string $subject, string $message): void
    {

        @mail($to, $subject, $message, $headers);
    }
}
