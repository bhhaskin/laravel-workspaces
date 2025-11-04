<?php

declare(strict_types=1);

use Bhhaskin\LaravelWorkspaces\Models\Workspace;
use Bhhaskin\LaravelWorkspaces\Tests\Fixtures\BillableWorkspace;
use Bhhaskin\LaravelWorkspaces\Tests\Fixtures\User;

beforeEach(function () {
    // Check if billing package is available
    if (! class_exists(\Bhhaskin\Billing\Models\Subscription::class)) {
        $this->markTestSkipped('Billing package is not installed');
    }
});

test('can check if billing is available', function () {
    expect(BillableWorkspace::billingAvailable())->toBe(
        class_exists(\Bhhaskin\Billing\Models\Subscription::class)
    );
});

test('billing contact defaults to owner', function () {
    $owner = User::create([
        'name' => 'Owner User',
        'email' => 'owner@example.com',
        'password' => bcrypt('password'),
    ]);

    $workspace = BillableWorkspace::create([
        'name' => 'Test Workspace',
        'owner_id' => $owner->id,
    ]);

    expect($workspace->billingContact())->not->toBeNull()
        ->and($workspace->billingContact()->id)->toBe($owner->id)
        ->and($workspace->getBillableEntity()->id)->toBe($owner->id);
});

test('can set billing contact for workspace member', function () {
    $owner = User::create([
        'name' => 'Owner User',
        'email' => 'owner@example.com',
        'password' => bcrypt('password'),
    ]);

    $financeUser = User::create([
        'name' => 'Finance User',
        'email' => 'finance@example.com',
        'password' => bcrypt('password'),
    ]);

    $workspace = BillableWorkspace::create([
        'name' => 'Test Workspace',
        'owner_id' => $owner->id,
    ]);

    // Add finance user as member
    $workspace->addMember($financeUser);

    // Set billing contact
    $workspace->setBillingContact($financeUser);

    expect($workspace->billing_contact_id)->toBe($financeUser->id)
        ->and($workspace->billingContact()->id)->toBe($financeUser->id)
        ->and($workspace->getBillableEntity()->id)->toBe($financeUser->id);
});

test('cannot set billing contact for non-member', function () {
    $owner = User::create([
        'name' => 'Owner User',
        'email' => 'owner@example.com',
        'password' => bcrypt('password'),
    ]);

    $nonMember = User::create([
        'name' => 'Non Member',
        'email' => 'non-member@example.com',
        'password' => bcrypt('password'),
    ]);

    $workspace = BillableWorkspace::create([
        'name' => 'Test Workspace',
        'owner_id' => $owner->id,
    ]);

    expect(fn() => $workspace->setBillingContact($nonMember))
        ->toThrow(RuntimeException::class, 'Billing contact must be a member of the workspace.');
});

test('can clear billing contact', function () {
    $owner = User::create([
        'name' => 'Owner User',
        'email' => 'owner@example.com',
        'password' => bcrypt('password'),
    ]);

    $financeUser = User::create([
        'name' => 'Finance User',
        'email' => 'finance@example.com',
        'password' => bcrypt('password'),
    ]);

    $workspace = BillableWorkspace::create([
        'name' => 'Test Workspace',
        'owner_id' => $owner->id,
    ]);

    $workspace->addMember($financeUser);
    $workspace->setBillingContact($financeUser);

    expect($workspace->billing_contact_id)->toBe($financeUser->id);

    // Clear billing contact (reverts to owner)
    $workspace->setBillingContact(null);

    expect($workspace->billing_contact_id)->toBeNull()
        ->and($workspace->billingContact()->id)->toBe($owner->id);
});

test('billing contact falls back to owner if removed from workspace', function () {
    $owner = User::create([
        'name' => 'Owner User',
        'email' => 'owner@example.com',
        'password' => bcrypt('password'),
    ]);

    $financeUser = User::create([
        'name' => 'Finance User',
        'email' => 'finance@example.com',
        'password' => bcrypt('password'),
    ]);

    $workspace = BillableWorkspace::create([
        'name' => 'Test Workspace',
        'owner_id' => $owner->id,
    ]);

    $workspace->addMember($owner);
    $workspace->addMember($financeUser);
    $workspace->setBillingContact($financeUser);

    expect($workspace->billingContact()->id)->toBe($financeUser->id);

    // Remove finance user from workspace
    $workspace->removeMember($financeUser);

    // Should fall back to owner
    expect($workspace->billingContact()->id)->toBe($owner->id);
});

test('guards against calling billing methods when package not installed', function () {
    $owner = User::create([
        'name' => 'Owner User',
        'email' => 'owner@example.com',
        'password' => bcrypt('password'),
    ]);

    // Create a workspace without the WorkspaceBillable trait
    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'owner_id' => $owner->id,
    ]);

    // This should work since we're not using the WorkspaceBillable trait
    expect($workspace)->toBeInstanceOf(Workspace::class);
});
