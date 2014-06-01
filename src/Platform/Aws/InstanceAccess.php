<?php

namespace App\Platform\Aws;

use App\Util\InstanceAccess\SSHAccess;

class InstanceAccess extends SSHAccess {

    public function hasKey() {
        return $this->get('key', null) !== null;
    }

    public function getKeyName() {
        return $this->get('key.name');
    }
}
