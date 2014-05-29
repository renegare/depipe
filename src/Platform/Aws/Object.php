<?php

namespace App\Platform\Aws;


abstract class Object {

    protected $id;
    protected $description;

    public function __construct($id, array $description=[]) {
        $this->id = $id;
        $this->description = $description;
    }

    public function getId() {
        return $this->id;
    }

    public function getDescription() {
        return $this->description;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString() {
        return $this->id;
    }
}
