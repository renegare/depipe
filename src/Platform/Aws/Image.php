<?php

namespace App\Platform\Aws;

use App\Platform\ImageInterface;

class Image implements ImageInterface {

    protected $amiId;
    protected $description;

    public function __construct($amiId, array $description=[]) {
        $this->amiId = $amiId;
        $this->description = $description;
    }

    public function getId() {
        return $this->amiId;
    }

    public function getDescription() {
        return $this->description;
    }
}
