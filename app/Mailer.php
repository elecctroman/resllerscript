<?php

namespace App;

class Mailer
{
    public static function send(string $to, string $subject, string $message): void
    {
        $headers = "From: no-reply@bayinetwork.local\r\n" .
            'X-Mailer: PHP/' . phpversion();

        // mail() fonksiyonunun başarısız olması halinde hata fırlatılmasın diye @ kullanıyoruz.
        @mail($to, $subject, $message, $headers);
    }
}
