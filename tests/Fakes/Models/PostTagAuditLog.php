<?php

namespace AlwaysOpen\AuditLog\Tests\Fakes\Models;

use Awobaz\Compoships\Compoships;
use AlwaysOpen\AuditLog\Models\BaseModel;

class PostTagAuditLog extends BaseModel
{
    use Compoships;

    public $timestamps = false;

    public $table = 'post_tag_auditlog';

    protected $guarded = [];
}
