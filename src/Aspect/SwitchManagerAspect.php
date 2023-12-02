<?php

declare(strict_types=1);

namespace Wayhood\HyperfTracerAop\Aspect;

use Hyperf\Context\Context;
use Hyperf\Di\Annotation\Aspect;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\Coroutine\Coroutine;
use OpenTracing\Span;

class SwitchManagerAspect extends AbstractAspect
{
    public array $classes = [
        'Hyperf\Tracer\SwitchManager::isEnable',
    ];

    protected $config = [];

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        if (count($this->config) == 0) {
            $reflectionClass = new \ReflectionClass($proceedingJoinPoint->getInstance());
            $reflectionProperty = $reflectionClass->getProperty('config');
            $reflectionProperty->setAccessible(true);
            $this->config = $reflectionProperty->getValue($proceedingJoinPoint->getInstance());
        }
        $identifier = $proceedingJoinPoint->getArguments()[0];
        if (! isset($this->config[$identifier])) {
            return false;
        }
        if (Coroutine::inCoroutine()) {
            $tracerRoot = Context::get('tracer.root');
            if (! $tracerRoot instanceof Span) {
                $tracerRoot = Context::get('tracer.root', null, Coroutine::parentId());
            }
        } else {
            $tracerRoot = Context::get('tracer.root');
        }
        return $this->config[$identifier] && $tracerRoot instanceof Span;
    }
}
