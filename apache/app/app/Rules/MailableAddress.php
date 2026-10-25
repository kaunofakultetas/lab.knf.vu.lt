<?php

// -----------------------------------------------------------
//  [*] MailableAddress — an e-mail address the mailer takes
//
//  Laravel's email rule checks an address against the RFC,
//  which allows forms the mail cannot reach: a comment in
//  the local part, which Symfony's mailer refuses to write;
//  a space before the @, which SMTP's own grammar does not
//  allow; a local part beyond ASCII, which Symfony sends
//  only to a server that announces SMTPUTF8 — Mailjet does,
//  but every server the mail passes after it must take one
//  too, and another relay set in the .env may not. An
//  account made with such an address may never get its
//  mail, or the mail ends the request on a 500, so the
//  forms that mail the address they take check it here
//  too: they build Symfony's Address from it, as
//  the mailer does, refuse a local part beyond ASCII, and
//  take the RFC check without its warnings, the email
//  rule's strict mode, which turns away the other forms the
//  RFC only tolerates as well: a quoted local part, an
//  address literal, a domain without a dot, a local part
//  over 64 characters. A value that is not text is left to
//  the string and email rules beside this one.
//
//  Used by:
//    - Users\RegisterController — store and forgotpassword
//    - Admins\UsersController::store
//    - Admins\EmailsController::sending_test
// -----------------------------------------------------------


namespace App\Rules;

use Closure;
use Egulias\EmailValidator\EmailValidator;
use Egulias\EmailValidator\Validation\NoRFCWarningsValidation;
use Illuminate\Contracts\Validation\ValidationRule;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Exception\ExceptionInterface;








// -----------------------------------------------------------
// MailableAddress
// -----------------------------------------------------------
//
// One method, validate, as a validation rule object.
//
// Used by:
//   - the rule lists of the controllers above
// -----------------------------------------------------------

class MailableAddress implements ValidationRule
{






    // -----------------------------------------------------------
    // validate
    // -----------------------------------------------------------
    //
    // Fails with the email rule's own message when Symfony's
    // Address refuses the value — an RFC compliance error, or
    // control characters — when its local part goes beyond
    // ASCII, or when the RFC check passes it only with a
    // warning.
    //
    // Used by:
    //   - Laravel's validator, for the fields this rule is on
    // -----------------------------------------------------------

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        try {
            $address = new Address($value);
        } catch (ExceptionInterface) {
            $fail('validation.email')->translate();

            return;
        }

        if ($address->hasUnicodeLocalpart() || ! (new EmailValidator())->isValid($value, new NoRFCWarningsValidation())) {
            $fail('validation.email')->translate();
        }
    }
}
