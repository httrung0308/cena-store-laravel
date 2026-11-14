<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('roles');

        $roleLabels = $user->roles
            ->map(fn ($role): string => $role->slug->label())
            ->values()
            ->all();

        return view('admin.profile.show', [
            'user' => $user,
            'roleLabels' => $roleLabels,
        ]);
    }
}
