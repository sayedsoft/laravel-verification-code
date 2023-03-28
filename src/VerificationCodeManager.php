<?php

namespace NextApps\VerificationCode;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use NextApps\VerificationCode\Models\VerificationCode;
use NextApps\VerificationCode\Notifications\VerificationCodeCreated;
use NextApps\VerificationCode\Notifications\VerificationCodeCreatedInterface;
use RuntimeException;

class VerificationCodeManager
{
    /**
     * Create and send a verification code.
     *
     * @param string $verifiable
     * @param string $channel
     *
     * @return void
     */
    public function send(string $verifiable,string $type = 'register', string $related_id = null ,string $channel = 'mail')
    {
        if ($this->isTestVerifiable($verifiable)) {
            return;
        }
    
        $code = VerificationCode::createFor($verifiable);

        $notificationClass = $this->getNotificationClass();
        $notification = new $notificationClass($code);

        if ($notification instanceof ShouldQueue) {
            $notification->onQueue(config('verification-code.queue', null));
        }

        Notification::route($channel, $verifiable)->notify($notification);
    }

    /**
     * Verify the code.
     *
     * @param string $code
     * @param string $verifiable
     * @param bool $deleteAfterVerification
     *
     * @return bool
     */
    public function verify(string $code, string $verifiable, string $type = 'register', string $related_id = null, bool $deleteAfterVerification = true)
    {
        if ($this->isTestVerifiable($verifiable)) {
            return $this->isTestCode($code);
        }
    
        $codeIsValid = VerificationCode::query()
            ->for($verifiable)
            ->whithType($type,$related_id)
            ->notExpired()
             ->cursor()
         ->contains(function ($verificationCode) use ($code) {
                return Hash::check($code, $verificationCode->code);
        });
        
           

        if (! $codeIsValid) {
            return false;
        }

        if ($deleteAfterVerification) {
            VerificationCode::for($verifiable)->delete();
        }

        return true;
    }

    /**
     * Check if the verifiable is a test verifiable.
     *
     * @param string $verifiable
     *
     * @return bool
     */
    protected function isTestVerifiable(string $verifiable)
    {
        $testVerifiables = config('verification-code.test_verifiables', []);

        $testVerifiables = array_map(function ($email) {
            return strtolower($email);
        }, $testVerifiables);

        return in_array(strtolower($verifiable), $testVerifiables);
    }

    /**
     * Check if the code is the test code.
     *
     * @param string $code
     *
     * @return bool
     */
    protected function isTestCode(string $code)
    {
        if (empty(config('verification-code.test_code'))) {
            return false;
        }

        return $code === config('verification-code.test_code');
    }

    /**
     * Get the notification class.
     *
     * @throws \RuntimeException
     *
     * @return string
     */
    protected function getNotificationClass()
    {
        $notificationClass = config('verification-code.notification', VerificationCodeCreated::class);

        if (! is_subclass_of($notificationClass, VerificationCodeCreatedInterface::class)) {
            $interface = VerificationCodeCreatedInterface::class;

            throw new RuntimeException("The notification class must implement the `{$interface}` interface");
        }

        return $notificationClass;
    }
}
