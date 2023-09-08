<?php

declare(strict_types=1);

namespace Yiisoft\Injector\Tests\Common\Support;

final class Circle
{
    public function __construct(private ColorInterface $color, private ?string $name = null)
    {
    }

    public function getColor(): string
    {
        return $this->color->getColor();
    }

    public function getName(): ?string
    {
        return $this->name;
    }
}
