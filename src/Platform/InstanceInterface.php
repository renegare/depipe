<?php

namespace App\Platform;

use App\Platform\InstanceAccessInterface;

interface InstanceInterface {

    /**
     * provide an access object that will be used to access the actual instance
     * and execute the scripts on the instance itself.
     * @param InstanceAccessInterface $access
     * @param array $scripts
     * @return void
     * @return App\Platform\RemoteInstanceException
     */
    public function provisionWith(InstanceAccessInterface $access, array $scripts);
}
