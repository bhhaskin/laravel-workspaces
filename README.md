# Laravel Workspaces

Reusable workspace (team) management for your Laravel applications.

This package adds models, migrations, and traits that help you:

- Create workspaces that belong to any user (users can own many workspaces).
- Invite additional users into a workspace using secure tokens.
- Manage workspace member roles through [`bhhaskin/laravel-roles-permissions`](https://github.com/bhhaskin/laravel-roles-permissions).
- Attach workspaces to any other model in your application (pages, posts, etc.).
- Transfer ownership, remove members, and keep a soft-delete history of memberships and invitations.
- Reference every workspace, membership, invitation, and assignment via stable UUIDs for frontend APIs.
- **Optional**: Integrate workspace-level billing and subscription management with [`bhhaskin/laravel-billing`](https://github.com/bhhaskin/laravel-billing).

## Installation

```bash
composer require bhhaskin/laravel-workspaces
```

This package depends on `bhhaskin/laravel-roles-permissions`. Make sure to publish both sets of configuration and migrations, then run your migrations:

```bash
php artisan vendor:publish --tag=workspaces-config
php artisan vendor:publish --tag=workspaces-migrations
php artisan vendor:publish --tag=laravel-roles-permissions-config
php artisan vendor:publish --tag=laravel-roles-permissions-migrations
php artisan migrate
```

Enable object-level permissions so role assignments can be scoped to a workspace. In `config/roles-permissions.php`:

```php
'object_permissions' => [
    'enabled' => true,
],

'role_scopes' => [
    'workspace' => \Bhhaskin\LaravelWorkspaces\Models\Workspace::class,
],
```

## Setup

Update your `User` model to use the `HasWorkspaces` trait:

```php
use Bhhaskin\LaravelWorkspaces\Traits\HasWorkspaces;
use Bhhaskin\RolesPermissions\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
    use HasWorkspaces;
}

// Users can join or own multiple workspaces
$user->workspaces;       // memberships (pivot exposes UUID, role, timestamps)
$user->ownedWorkspaces;  // workspaces where the user is the owner
```

Any model that should be linked to a workspace can use the `Workspaceable` trait:

```php
use Bhhaskin\LaravelWorkspaces\Traits\Workspaceable;

class Page extends Model
{
    use Workspaceable;
}
```

## Usage

### Creating a workspace

```php
use Bhhaskin\LaravelWorkspaces\Models\Workspace;
use Bhhaskin\LaravelWorkspaces\Support\WorkspaceRoles;

/** @var Workspace $workspace */
$workspace = Workspace::create([
    'name' => 'Product Team',
    'owner_id' => $owner->id,
]);

// Owner role is assigned automatically when the owner joins.
$workspace->addMember($owner);

// Assign additional roles using slugs (aliases like "owner" and "member" are supported)
$editorRole = WorkspaceRoles::findOrCreateRole('workspace-editor');
$workspace->addMember($teammate, $editorRole);
```

### Inviting members

```php
$contributorRole = WorkspaceRoles::findOrCreateRole('workspace-contributor');
$invitation = $workspace->invite('teammate@example.com', role: $contributorRole);

// Later, after the user accepts the invite:
$invitation->accept($user);

// Use UUIDs when sending invitation links:
$invitation->uuid; // e.g. expose in APIs
```

Invitations automatically expire after seven days (configurable) and may only be accepted by the intended email address.

You can inspect roles for a workspace member:

```php
$role = $workspace->memberRole($user); // Returns a Role instance (or null)
$user->hasRole($role, $workspace);     // Provided by laravel-roles-permissions
```

### Removing members or leaving a workspace

```php
$workspace->removeMember($user); // Soft deletes the membership and detaches workspace-scoped roles

if (! $workspace->isMember($user)) {
    // The user successfully left the workspace
}
```

Historical membership entries remain in the pivot table with a `removed_at` timestamp so you can audit previous collaborators.

### Transferring ownership

```php
$workspace->transferOwnership($newOwner);

// Ownership role automatically moves across and the old owner is demoted to the configured fallback role.
```

### Mapping workspaces to other models

```php
$page->attachToWorkspace($workspace);

$workspace->assignTo($page); // Equivalent helper on the workspace model

// Both models now have UUID-backed relationships:
$workspace->uuid;
$page->workspaces->first()->pivot->uuid;
```

Models gain a `workspaces()` relation, and workspaces track attached models through the `assignments()` relation.

### Authorization

Workspace abilities are mapped to roles (or permissions) through the `workspaces.abilities` config array. Gates are registered using the `workspace.{ability}` convention:

```php
if (Gate::forUser($user)->allows('workspace.manage-members', $workspace)) {
    // $user can manage members in this workspace
}

Gate::authorize('workspace.edit-content', $workspace);
```

## API Routes

The package ships with controllers, form requests, resources, and policies so you can expose workspace management over HTTP. Routes are not loaded automatically—call the route registrar from your application's route files so you can decide middleware and prefixes:

```php
use Bhhaskin\LaravelWorkspaces\Routes\Workspaces;

Route::middleware(['auth:sanctum'])
    ->group(function () {
        Workspaces::register(); // Registers /workspaces, membership, and invitation endpoints
    });
```

Available endpoints include workspace CRUD operations, membership management, and invitation flows (accept/decline requires authentication so the invitation can be matched to the acting user). Use the `include` query string (`?include=members,invitations`) when you need expanded relationships in responses. Routes honour the package policies as well as the workspace ability map defined in your configuration.

Roles listed under `workspaces.roles.auto_create` are ensured during boot, and you can extend the ability map to fit your domain.

### Events

The package dispatches events as part of the workspace lifecycle so you can hook into notifications or analytics:

- `WorkspaceMemberAdded`
- `WorkspaceMemberRemoved`
- `WorkspaceMemberRoleUpdated`
- `WorkspaceInvitationCreated`
- `WorkspaceInvitationAccepted`
- `WorkspaceInvitationDeclined`
- `WorkspaceOwnershipTransferred`

Listen to these events to trigger custom logic (emails, audits, etc.).

## Configuration

The published `config/workspaces.php` file lets you adjust:

- Custom user/workspace/invitation model classes.
- Table names.
- Role scope, aliases, owner fallback behaviour, and which roles should be auto-created for workspaces.
- Ability-to-role mappings (registered automatically as `workspace.*` gates).
- Invitation expiration window.

## Billing Integration (Optional)

Add workspace-level billing and subscription management by installing the optional billing package:

```bash
composer require bhhaskin/laravel-billing
```

### Setup Billing

1. **Publish billing configuration and migrations:**

```bash
php artisan vendor:publish --tag=billing-config
php artisan vendor:publish --tag=billing-migrations
php artisan migrate
```

2. **Add the `WorkspaceBillable` trait to your Workspace model:**

```php
use Bhhaskin\LaravelWorkspaces\Models\Workspace as BaseWorkspace;
use Bhhaskin\LaravelWorkspaces\Traits\WorkspaceBillable;

class Workspace extends BaseWorkspace
{
    use WorkspaceBillable;
}
```

3. **Add the `Billable` trait to your User model:**

```php
use Bhhaskin\Billing\Concerns\Billable;
use Bhhaskin\LaravelWorkspaces\Traits\HasWorkspaces;
use Bhhaskin\RolesPermissions\Traits\HasRoles;

class User extends Authenticatable
{
    use Billable, HasRoles, HasWorkspaces;
}
```

### Billing Contact

Designate a workspace member to handle billing (defaults to owner):

```php
// Set a billing contact (must be a workspace member)
$workspace->addMember($financeUser);
$workspace->setBillingContact($financeUser);

// Get billing contact (falls back to owner if not set)
$contact = $workspace->billingContact();

// Clear billing contact (reverts to owner)
$workspace->setBillingContact(null);
```

### Workspace Subscriptions

Subscribe workspaces to plans and track usage:

```php
// Create a plan in your application
$plan = Plan::create([
    'name' => 'Professional Hosting',
    'slug' => 'pro-hosting',
    'price' => 49.99,
    'interval' => 'monthly',
    'limits' => [
        'sites' => 10,
        'storage_gb' => 100,
        'bandwidth_gb' => 1000,
    ],
    'features' => ['ssl_certificates', 'daily_backups', 'cdn'],
]);

// Subscribe workspace (billing contact gets charged)
$subscription = $workspace->subscribe($plan);

// Check subscription status
if ($workspace->hasActiveSubscription()) {
    // Workspace has active billing
}

if ($workspace->subscribedToPlan($plan)) {
    // Workspace has this specific plan
}
```

### Quota Management

Track usage against plan limits:

```php
// Get plan limits
$siteLimit = $workspace->getLimit('sites'); // 10
$storageLimit = $workspace->getLimit('storage_gb'); // 100

// Check features
if ($workspace->hasFeature('ssl_certificates')) {
    // Enable SSL for workspace sites
}

// Record usage when creating resources
$site = Site::create(['workspace_id' => $workspace->id, ...]);
$workspace->recordUsage('sites', 1);

// Check remaining quota before allowing actions
if ($workspace->getRemainingQuota('sites') > 0) {
    // User can create more sites
} else {
    // Show upgrade prompt
}

// Check if over quota
if ($workspace->isOverQuota('storage_gb')) {
    // Prevent new uploads
}

// Get usage percentage
$percentage = $workspace->getQuotaPercentage('sites'); // e.g., 40.0

// Decrement usage when deleting resources
$workspace->decrementUsage('sites', 1);
```

**Example: Web Hosting Platform**

```php
// Workspace for hosting multiple websites
$workspace = Workspace::create([
    'name' => 'ACME Corporation Sites',
    'owner_id' => $owner->id,
]);

// Delegate billing to finance team
$workspace->addMember($financeUser);
$workspace->setBillingContact($financeUser);

// Subscribe to hosting plan
$workspace->subscribe($proHostingPlan);

// Check limits before creating site
if ($workspace->getRemainingQuota('sites') > 0) {
    $site = Site::create([
        'workspace_id' => $workspace->id,
        'domain' => 'example.com',
    ]);
    $workspace->recordUsage('sites', 1);
} else {
    return redirect()->back()->with('error', 'Site limit reached. Please upgrade.');
}

// Track storage usage
$totalStorage = $workspace->sites()->sum('storage_mb');
$workspace->setUsage('storage_gb', $totalStorage / 1024);

// Prevent uploads if over quota
if ($workspace->isOverQuota('storage_gb')) {
    throw new StorageQuotaExceededException();
}
```

For more details on billing features, see the [`bhhaskin/laravel-billing`](https://github.com/bhhaskin/laravel-billing) documentation.

## Testing & Extending

The package ships with simple events and helpers designed to be extended. Replace the provided models by pointing to your own implementation in the config file and override any behaviour you need.

Run the automated test suite (powered by Pest and Orchestra Testbench):

```bash
composer test
```
