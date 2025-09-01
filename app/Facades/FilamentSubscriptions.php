<?php

namespace App\Facades;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use App\Services\Contracts\Subscriber;

/**
 * @method static void register(Subscriber|array $author)
 * @method static Collection getOptions()
 * @method void beforeSubscription(\Closure $closure)
 * @method void beforeRenew(\Closure $closure)
 * @method void beforeChange(\Closure $closure)
 * @method \Closure getBeforeSubscription()
 * @method \Closure getBeforeRenew()
 * @method \Closure getBeforeChange()
 * @method void afterSubscription(\Closure $closure)
 * @method void afterRenew(\Closure $closure)
 * @method void afterCanceling(\Closure $closure)
 * @method void afterChange(\Closure $closure)
 * @method \Closure getAfterSubscription()
 * @method \Closure getAfterRenew()
 * @method \Closure getAfterCanceling()
 * @method \Closure getAfterChange()
 */
class FilamentSubscriptions extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'filament-subscriptions';
    }
}
