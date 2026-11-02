<?php
namespace App\Services;

class ContactGuard
{
    public function containsContact(string $text): bool
    {
        $email = '/[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}/u';
        // Syrian mobile numbers and most international numbers with separators.
        $phone = '/(?<!\w)(?:(?:\+|00)\d{1,3}[\s.\-]?)?(?:0?9\d{8}|\d[\d\s().\-]{8,}\d)(?!\w)/u';
        return (bool) (preg_match($email,$text) || preg_match($phone,$text) || preg_match('/(?:wa\.me\/|https?:\/\/t\.me\/)/iu',$text));
    }
}
