<?php

namespace App\Traits;

use Illuminate\Support\Facades\Crypt;

trait EncryptsPii
{
    /**
     * Fields that should be AES encrypted/decrypted automatically.
     * Override $encryptedFields in your model.
     */
    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);

        if (in_array($key, $this->getEncryptedFields()) && $value !== null) {
            try {
                return Crypt::decryptString($value);
            } catch (\Exception $e) {
                return $value;
            }
        }

        return $value;
    }

    public function setAttribute($key, $value)
    {
        if (in_array($key, $this->getEncryptedFields()) && $value !== null) {
            $value = Crypt::encryptString($value);
        }

        return parent::setAttribute($key, $value);
    }

    protected function getEncryptedFields(): array
    {
        return $this->encryptedFields ?? [];
    }
}
