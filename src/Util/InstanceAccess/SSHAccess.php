<?php

namespace App\Util\InstanceAccess;

use App\Platform\InstanceAccessInterface;

class SSHAccess implements InstanceAccessInterface {

    protected $credentials;

    /**
     * {@inheritdoc}
     */
    public function setCredentials(array $crendtials) {
        $this->credentials = $credentials;
    }

    /**
     * {@inheritdoc}
     */
    public function connect($host) {
        throw new \Exception('WTF');
    }

    /**
     * {@inheritdoc}
     */
    public function exec($code) {
        throw new \Exception('WTF');
    }
}
