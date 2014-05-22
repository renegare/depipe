<?php

namespace App\Platform\Pipe;

use App\Platform\InstanceInterface;

abstract class Instance implements InstanceInterface {

    /**
     * {@inheritdoc}
     */
    public function provisionWith(InstanceAccessInterface $access, array $scripts) {
        throw new \RuntimeException('Not Implemented!');
    }
}
