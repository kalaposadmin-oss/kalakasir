<?php

namespace App\Models;

class ProductionBatch extends TenantModel
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_POSTED = 'posted';
}