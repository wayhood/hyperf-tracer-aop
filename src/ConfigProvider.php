<?php

declare(strict_types=1);

namespace Wayhood\HyperfTracerAop;


use Wayhood\HyperfTracerAop\Aspect\SpanStraterAspect;
use Wayhood\HyperfTracerAop\Aspect\SwitchManagerAspect;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'aspects' => [
                SpanStraterAspect::class,
                SwitchManagerAspect::class,
            ],
        ];
    }
}
