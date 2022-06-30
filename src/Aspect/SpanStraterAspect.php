<?php

declare(strict_types=1);

namespace Wayhood\HyperfTracerAop\Aspect;

use Hyperf\Context\Context;
use Hyperf\Di\Annotation\Aspect;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\Rpc;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Coroutine;
use OpenTracing\Span;
use Psr\Http\Message\ServerRequestInterface;
use const OpenTracing\Formats\TEXT_MAP;
use const OpenTracing\Tags\SPAN_KIND;

class SpanStraterAspect extends AbstractAspect
{
    public $classes = [
        'Hyperf\Tracer\SpanStarter::startSpan',
    ];

    private $tracer;

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        $root = Context::get('tracer.root');
        if (Coroutine::inCoroutine()) { // 协程内
            // 本协程内取tracer
            if (! $root instanceof Span) { // 如果没有，去父协程内取
                $root = Context::get('tracer.root', null, Coroutine::parentId());
                if ($root instanceof Span) { // 如果有， 放入本协程
                    // 这里主要是 协程 内 调 协程  是无序的
                    Context::set('tracer.root', $root);
                }
            }
        }

        [$name, $option, $kind] = $proceedingJoinPoint->getArguments();
        if ($this->tracer == null) {
            $reflectionClass = new \ReflectionClass($proceedingJoinPoint->getInstance());
            $reflectionProperty = $reflectionClass->getProperty('tracer');
            $reflectionProperty->setAccessible(true);
            $this->tracer = $reflectionProperty->getValue($proceedingJoinPoint->getInstance());
        }

        if (! $root instanceof Span) {
            $container = ApplicationContext::getContainer();
            /** @var ServerRequestInterface $request */
            $request = Context::get(ServerRequestInterface::class);
            if (! $request instanceof ServerRequestInterface) {
                // If the request object is absent, we are probably in a commandline context.
                // Throwing an exception is unnecessary.
                $root = $this->tracer->startSpan($name, $option);
                $root->setTag(SPAN_KIND, $kind);
                Context::set('tracer.root', $root);
                return $root;
            }
            $carrier = array_map(function ($header) {
                return $header[0];
            }, $request->getHeaders());
            if ($container->has(Rpc\Context::class) && $rpcContext = $container->get(Rpc\Context::class)) {
                $rpcCarrier = $rpcContext->get('tracer.carrier');
                if (! empty($rpcCarrier)) {
                    $carrier = $rpcCarrier;
                }
            }
            // Extracts the context from the HTTP headers.
            $spanContext = $this->tracer->extract(TEXT_MAP, $carrier);
            if ($spanContext) {
                $option['child_of'] = $spanContext;
            }
            $root = $this->tracer->startSpan($name, $option);
            $root->setTag(SPAN_KIND, $kind);
            Context::set('tracer.root', $root);
            return $root;
        }
        $option['child_of'] = $root->getContext();
        $child = $this->tracer->startSpan($name, $option);
        $child->setTag(SPAN_KIND, $kind);
        return $child;
    }
}
