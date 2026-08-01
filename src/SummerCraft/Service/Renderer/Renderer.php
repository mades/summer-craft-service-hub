<?php

namespace SummerCraft\Service\Renderer;

use SummerCraft\Core\ComponentManaging\RequestScope;

interface Renderer
{
    public function render(RequestScope $requestScope, string $viewFile, array $data = [], bool $return = false): string;
}
