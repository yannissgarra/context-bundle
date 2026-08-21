<?php

/*
 * (c) Yannis Sgarra <hello@yannissgarra.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Webmunkeez\ContextBundle\Context;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Yannis Sgarra <hello@yannissgarra.com>
 */
final class ContextProvider implements ContextProviderInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly SerializerInterface $serializer,
    ) {
    }

    public function get(string $contextClass): ContextInterface
    {
        $request = $this->requestStack->getMainRequest();

        if (
            null !== $request
            && true === $request->attributes->has('context.'.$contextClass::getReference())
        ) {
            return $this->serializer->deserialize($request->attributes->get('context.'.$contextClass::getReference()), $contextClass, JsonEncoder::FORMAT);
        }

        return new $contextClass();
    }

    public function update(ContextInterface $context): void
    {
        $request = $this->requestStack->getMainRequest();

        if (
            null !== $request
            && $this->get($context::class)->getHash() !== $context->getHash()
        ) {
            $request->attributes->set('context.'.$context::getReference(), $this->serializer->serialize($context, JsonEncoder::FORMAT));
            $request->attributes->set('context.'.$context::getReference().'.refresh', true);
        }
    }
}
