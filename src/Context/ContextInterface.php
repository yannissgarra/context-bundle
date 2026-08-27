<?php

/*
 * (c) Yannis Sgarra <hello@yannissgarra.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Webmunkeez\ContextBundle\Context;

/**
 * @author Yannis Sgarra <hello@yannissgarra.com>
 */
interface ContextInterface extends ContextItemInterface
{
    public static function getReference(): string;

    /**
     * How long the cookie/JWT stays valid, as a relative date/time string (e.g. '30 days', anything accepted by strtotime('+'.$ttl)).
     */
    public static function getTtl(): string;

    /**
     * Past this age, the cookie/JWT is silently reissued on the next request, sliding it back within getTtl() of expiry. Must resolve to a shorter duration than getTtl().
     */
    public static function getRefreshAfter(): string;
}
