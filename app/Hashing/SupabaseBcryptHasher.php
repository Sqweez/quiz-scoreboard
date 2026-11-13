<?php

namespace App\Hashing;

use Illuminate\Hashing\BcryptHasher;

class SupabaseBcryptHasher extends BcryptHasher
{
    public function info($hashedValue)
    {
        if (preg_match('/\A\$2[ab]\$(0[4-9]|[12][0-9]|3[01])\$[.\/A-Za-z0-9]{53}\z/', $hashedValue) === 1) {
            return parent::info(substr_replace($hashedValue, '2y', 1, 2));
        }

        return parent::info($hashedValue);
    }
}
