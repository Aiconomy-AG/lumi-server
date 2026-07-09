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

    // Admins and Employees can manage products
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isEmployee();
    }

    public function update(User $user, Product $product): bool
    {
        return $user->isAdmin() || $user->isEmployee();
    }

    // Only Admins can delete
    public function delete(User $user, Product $product): bool
    {
        return $user->isAdmin();
    }

    // Both Admins and Employees can update stock
    public function updateStock(User $user, Product $product): bool
    {
        return $user->isAdmin() || $user->isEmployee();
    }
}
