<?php

namespace AlwaysOpen\AuditLog\Tests\Fakes\Models;

use AlwaysOpen\AuditLog\Models\BaseModel;

class NonSoftDeletePostAuditLog extends BaseModel
{
    public $timestamps = false;

    public $table = 'posts_auditlog';

    protected $guarded = [];
}
