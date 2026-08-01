<?php

namespace SummerCraft\Service\Renderer;

use SummerCraft\Core\ComponentManaging\LifeCycle\SharedComponent;

class RendererConfig implements SharedComponent
{
    public string $templateNameAppend = '.tpl.php';
}