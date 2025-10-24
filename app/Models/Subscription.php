<?php

namespace App\Models;
use Laravelcm\Subscriptions\Models\Subscription as BaseSubscription;

class Subscription extends BaseSubscription
{
    /**
     * Check if the subscription has a feature.
     *
     * @param string $feature
     * @return bool
     */

    protected $fillable = [
        'subscriber_id',
        'subscriber_type',
        'plan_id',
        'gateway_subscription_id',
        'slug',
        'name',
        'description',
        'trial_ends_at',
        'starts_at',
        'ends_at',
        'cancels_at',
        'canceled_at',
    ];

    protected $casts = [
        'subscriber_type' => 'string',
        'auto_renew' => 'boolean',
        'slug' => 'string',
        'trial_ends_at' => 'datetime',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'cancels_at' => 'datetime',
        'canceled_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
    public function hasFeature(string $featureSlug): bool
    {
        $featureValue = $this->getFeatureValue($featureSlug);

        if ($featureValue === 'true') {
            return true;
        }

        // If the feature value is zero, let's return false since
        // there's no uses available. (useful to disable countable features)
        if ($featureValue === null || $featureValue === '0' || $featureValue === 'false') {
            return false;
        }

        // Check for available uses
        return true;
    }
}
