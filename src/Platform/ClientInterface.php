<?php

namespace App\Platform;

use App\Platform\InstanceInterface;
use App\Platform\ImageInterface;


interface ClientInterface {

    public function convertToImage($imageId);

    public function convertToInstances(array $instances);

    public function snapshotInstance(InstanceInterface $instance, $imageName='');

    public function launchInstances(ImageInterface $image, $instanceCount = 1, array $instanceConfig=[], array $userDataConfig=[]);

    public function provisionInstances(array $instances, array $shellScripts);
}
