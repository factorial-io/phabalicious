<?php

namespace Phabalicious\Method;

use ThibaudDauce\Mattermost\Attachment;

class MetaInformation
{

    private $key;
    private $value;
    private $short;

    public function __construct($key, $value, $short = false)
    {
        $this->key = $key;
        $this->value = $value;
        $this->short = $short;
    }

    public function applyToMatterMostAttachment(Attachment $attachment)
    {
        $attachment->field($this->key, $this->value, $this->short);
    }
}
