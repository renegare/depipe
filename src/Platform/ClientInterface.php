<?php

namespace App\Platform;

use App\Platform\InstanceInterface;
use App\Platform\ImageInterface;


interface ClientInterface {

    public function snapshotInstance(InstanceInterface $instance, $imageName='');
}
