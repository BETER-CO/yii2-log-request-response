<?php

namespace app\components;

use yii\base\Component;

/** Guest-only user status required by the request logger. */
class GuestUser extends Component
{
    public function getIsGuest(): bool
    {
        return true;
    }
}
