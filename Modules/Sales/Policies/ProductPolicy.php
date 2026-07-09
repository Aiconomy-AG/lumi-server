<?php

namespace Modules\Sales\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\Sales\Models\Product;

class ProductPolicy
{
    use HandlesAuthorization;

    // Both Admins and Employees can view lists and details
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Product $product): bool
    {
        return true;
    }

    // Only Admins can create, update, or delete the main product details
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isEmployee();
    }

    public function update(User $user, Product $product): bool
    {
        return $user->isAdmin() || $user->isEmployee();
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->isAdmin() || $user->isEmployee();
    }

    // Both Admins and Employees can update stock
    public function updateStock(User $user, Product $product): bool
    {
        return $user->isAdmin() || $user->isEmployee();
    }
}
