<?php

declare(strict_types=1);

namespace Fuzzy\Fzpkg\Classes\SweetApi\Attributes\Router;

#[\Attribute(\Attribute::TARGET_METHOD)]
class Put extends Route
{
    public function __construct(string $path = '', string $name = '', array $consumes = [], string $summary = '', string $description = '', bool $deprecated = false)
    {
        parent::__construct($path, 'PUT', $name, $consumes, $summary, $description, $deprecated);
    }
}
