<?php

declare(strict_types=1);

namespace Bhhaskin\LaravelWorkspaces\Traits;

use Bhhaskin\LaravelWorkspaces\Support\WorkspaceConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Trait WorkspaceBillable
 *
 * Provides billing integration for workspaces when bhhaskin/laravel-billing is installed.
 * The billing contact (or owner) becomes the billable entity for the workspace.
 *
 * @mixin Model
 */
trait WorkspaceBillable
{
    /**
     * Determine if billing support is available.
     */
    public static function billingAvailable(): bool
    {
        return class_exists(\Bhhaskin\Billing\Models\Subscription::class);
    }

    /**
     * Get the billing contact for this workspace.
     * Falls back to the owner if no billing contact is set.
     */
    public function billingContact(): ?Model
    {
        if ($this->billing_contact_id) {
            $contact = WorkspaceConfig::userModel()::find($this->billing_contact_id);

            if ($contact && $this->isMember($contact)) {
                return $contact;
            }
        }

        return $this->owner;
    }

    /**
     * Set the billing contact for this workspace.
     * The user must be a member of the workspace.
     */
    public function setBillingContact(?Model $user): void
    {
        if ($user !== null && ! $this->isMember($user)) {
            throw new RuntimeException('Billing contact must be a member of the workspace.');
        }

        $this->billing_contact_id = $user?->getKey();
        $this->save();
    }

    /**
     * Get the billable entity (user) for this workspace.
     * This is the user who will be charged for subscriptions.
     */
    public function getBillableEntity(): ?Model
    {
        return $this->billingContact();
    }

    /**
     * Get all subscriptions associated with this workspace.
     */
    public function subscriptions()
    {
        $this->guardBillingAvailable();

        $subscriptionClass = $this->getSubscriptionModel();

        return $subscriptionClass::query()
            ->where('workspace_id', $this->getKey());
    }

    /**
     * Get active subscriptions for this workspace.
     */
    public function activeSubscriptions()
    {
        $this->guardBillingAvailable();

        $subscriptionClass = $this->getSubscriptionModel();

        return $this->subscriptions()
            ->whereIn('status', [
                $subscriptionClass::STATUS_ACTIVE,
                $subscriptionClass::STATUS_TRIALING,
            ]);
    }

    /**
     * Determine if the workspace has any active subscription.
     */
    public function hasActiveSubscription(): bool
    {
        $this->guardBillingAvailable();

        return $this->activeSubscriptions()->exists();
    }

    /**
     * Subscribe the workspace to a plan.
     * The billing contact (or owner) will be charged.
     *
     * @param  \Bhhaskin\Billing\Models\Plan  $plan
     * @param  array  $options
     * @return \Bhhaskin\Billing\Models\Subscription
     */
    public function subscribe($plan, array $options = [])
    {
        $this->guardBillingAvailable();

        $billableUser = $this->getBillableEntity();

        if (! $billableUser) {
            throw new RuntimeException('No billable entity found for workspace. Ensure workspace has an owner or billing contact.');
        }

        if (! method_exists($billableUser, 'getOrCreateCustomer')) {
            throw new RuntimeException('Billing contact must use the Billable trait from bhhaskin/laravel-billing.');
        }

        // Create subscription for the billable user
        $customer = $billableUser->getOrCreateCustomer();

        $subscriptionClass = $this->getSubscriptionModel();

        $subscription = $customer->subscriptions()->create([
            'workspace_id' => $this->getKey(),
            'status' => $options['status'] ?? $subscriptionClass::STATUS_ACTIVE,
            'trial_ends_at' => $options['trial_ends_at'] ?? null,
            'current_period_start' => $options['current_period_start'] ?? now(),
            'current_period_end' => $options['current_period_end'] ?? now()->addMonth(),
        ]);

        $subscription->addItem($plan, $options['quantity'] ?? 1);

        return $subscription;
    }

    /**
     * Determine if the workspace is subscribed to a specific plan.
     *
     * @param  \Bhhaskin\Billing\Models\Plan|int|string  $plan
     */
    public function subscribedToPlan($plan): bool
    {
        $this->guardBillingAvailable();

        $planClass = $this->getPlanModel();
        $planId = $plan instanceof $planClass ? $plan->id : $plan;

        return $this->activeSubscriptions()
            ->whereHas('items', function ($query) use ($planId) {
                $query->where('plan_id', $planId);
            })
            ->exists();
    }

    /**
     * Get the combined limits from all active workspace subscriptions.
     */
    public function getCombinedLimits(): array
    {
        $this->guardBillingAvailable();

        $limits = [];

        $this->activeSubscriptions()
            ->with('items.plan')
            ->get()
            ->flatMap(fn($subscription) => $subscription->items)
            ->each(function ($item) use (&$limits) {
                if (! $item->plan || ! $item->plan->limits) {
                    return;
                }

                foreach ($item->plan->limits as $key => $value) {
                    if (! isset($limits[$key])) {
                        $limits[$key] = 0;
                    }
                    $limits[$key] += $value * $item->quantity;
                }
            });

        return $limits;
    }

    /**
     * Get a specific limit value across all active workspace subscriptions.
     */
    public function getLimit(string $key, $default = null)
    {
        $this->guardBillingAvailable();

        return $this->getCombinedLimits()[$key] ?? $default;
    }

    /**
     * Check if the workspace has a specific feature from any active subscription.
     */
    public function hasFeature(string $feature): bool
    {
        $this->guardBillingAvailable();

        return $this->activeSubscriptions()
            ->with('items.plan')
            ->get()
            ->flatMap(fn($subscription) => $subscription->items)
            ->contains(function ($item) use ($feature) {
                return $item->plan && $item->plan->hasFeature($feature);
            });
    }

    /**
     * Get all features from active workspace subscriptions.
     */
    public function getFeatures(): array
    {
        $this->guardBillingAvailable();

        return $this->activeSubscriptions()
            ->with('items.plan')
            ->get()
            ->flatMap(fn($subscription) => $subscription->items)
            ->filter(fn($item) => $item->plan && $item->plan->features)
            ->flatMap(fn($item) => $item->plan->features)
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Get the current usage for a specific quota at the workspace level.
     * This proxies to the billing contact's usage.
     */
    public function getUsage(string $quotaKey): float
    {
        $this->guardBillingAvailable();

        $billableUser = $this->getBillableEntity();

        if (! $billableUser || ! method_exists($billableUser, 'getUsage')) {
            return 0;
        }

        return $billableUser->getUsage($quotaKey);
    }

    /**
     * Record (increment) usage for a specific quota.
     */
    public function recordUsage(string $quotaKey, float $amount): void
    {
        $this->guardBillingAvailable();

        $billableUser = $this->getBillableEntity();

        if (! $billableUser || ! method_exists($billableUser, 'recordUsage')) {
            throw new RuntimeException('Billing contact must use the Billable trait to track usage.');
        }

        $billableUser->recordUsage($quotaKey, $amount);
    }

    /**
     * Set the absolute usage for a specific quota.
     */
    public function setUsage(string $quotaKey, float $amount): void
    {
        $this->guardBillingAvailable();

        $billableUser = $this->getBillableEntity();

        if (! $billableUser || ! method_exists($billableUser, 'setUsage')) {
            throw new RuntimeException('Billing contact must use the Billable trait to track usage.');
        }

        $billableUser->setUsage($quotaKey, $amount);
    }

    /**
     * Decrement usage for a specific quota.
     */
    public function decrementUsage(string $quotaKey, float $amount): void
    {
        $this->guardBillingAvailable();

        $billableUser = $this->getBillableEntity();

        if (! $billableUser || ! method_exists($billableUser, 'decrementUsage')) {
            throw new RuntimeException('Billing contact must use the Billable trait to track usage.');
        }

        $billableUser->decrementUsage($quotaKey, $amount);
    }

    /**
     * Reset usage for a specific quota to zero.
     */
    public function resetUsage(string $quotaKey): void
    {
        $this->guardBillingAvailable();

        $billableUser = $this->getBillableEntity();

        if (! $billableUser || ! method_exists($billableUser, 'resetUsage')) {
            throw new RuntimeException('Billing contact must use the Billable trait to track usage.');
        }

        $billableUser->resetUsage($quotaKey);
    }

    /**
     * Get the remaining quota for a specific key.
     */
    public function getRemainingQuota(string $quotaKey): float
    {
        $this->guardBillingAvailable();

        $limit = $this->getLimit($quotaKey);
        $usage = $this->getUsage($quotaKey);

        if ($limit === null) {
            return PHP_FLOAT_MAX; // unlimited
        }

        return max(0, $limit - $usage);
    }

    /**
     * Check if the workspace is over quota for a specific key.
     */
    public function isOverQuota(string $quotaKey): bool
    {
        $this->guardBillingAvailable();

        $limit = $this->getLimit($quotaKey);

        if ($limit === null) {
            return false; // unlimited
        }

        return $this->getUsage($quotaKey) > $limit;
    }

    /**
     * Get the percentage of quota used for a specific key.
     */
    public function getQuotaPercentage(string $quotaKey): float
    {
        $this->guardBillingAvailable();

        $limit = $this->getLimit($quotaKey);

        if ($limit === null || $limit == 0) {
            return 0;
        }

        $usage = $this->getUsage($quotaKey);

        return min(100, ($usage / $limit) * 100);
    }

    /**
     * Guard that billing support is available.
     */
    protected function guardBillingAvailable(): void
    {
        if (! static::billingAvailable()) {
            throw new RuntimeException('Billing package (bhhaskin/laravel-billing) is not installed.');
        }
    }

    /**
     * Get the subscription model class.
     */
    protected function getSubscriptionModel(): string
    {
        return \Bhhaskin\Billing\Models\Subscription::class;
    }

    /**
     * Get the plan model class.
     */
    protected function getPlanModel(): string
    {
        return \Bhhaskin\Billing\Models\Plan::class;
    }
}
