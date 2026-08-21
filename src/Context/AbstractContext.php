<?php

/*
 * (c) Yannis Sgarra <hello@yannissgarra.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Webmunkeez\ContextBundle\Context;

use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\String\UnicodeString;

/**
 * @author Yannis Sgarra <hello@yannissgarra.com>
 */
abstract class AbstractContext implements ContextInterface
{
    #[Ignore]
    final public static function getReference(string $separator = '-'): string
    {
        $shortName = (new \ReflectionClass(static::class))->getShortName();

        if (str_ends_with($shortName, 'Context')) {
            $shortName = substr($shortName, 0, -strlen('Context'));
        }

        return (new UnicodeString($shortName))->snake()->replace('_', $separator)->toString();
    }
}
