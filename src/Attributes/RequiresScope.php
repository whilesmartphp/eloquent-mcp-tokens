<?php

namespace Whilesmart\McpTokens\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class RequiresScope
{
    public function __construct(public string $scope) {}
}
