<?php

namespace Modules\Settings\app\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Consumption\app\Models\Consumption;
use Modules\Estimation\app\Models\Estimation;
use Modules\Finance\app\Models\Expense;
use Modules\Purchase\app\Models\Purchase;
use Modules\Returns\app\Models\Returns;
use Modules\Sale\app\Models\Sale;
use Modules\Service\app\Models\Service;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    protected $fillable = [
        'user_name',
        'email',
        'password',
        'user_logo',
        'user_role'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function purchases()
    {
        return $this->hasMany(Purchase::class, 'pu_user', 'id');
    }

    public function sales()
    {
        return $this->hasMany(Sale::class, 'sa_user', 'id');
    }

    public function consumptions()
    {
        return $this->hasMany(Consumption::class, 'con_user', 'id');
    }

    public function estimations()
    {
        return $this->hasMany(Estimation::class, 'es_user', 'id');
    }

    public function preturns()
    {
        return $this->hasMany(Returns::class, 'pr_user', 'id');
    }

    public function services()
    {
        return $this->hasMany(Service::class, 'sv_user', 'id');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'exp_user', 'id');
    }

    public function getAllUsers()
    {
         return $this->orderBy('id', 'asc')->get();
    }   

    public static function createUser(array $validatedData, $request)
    {
        $data = $request->only(['user_name', 'email', 'user_role']);
        // Hash the password
        $validatedData['password'] = Hash::make($validatedData['password']);

        // Handle file upload if it exists
        if ($request->hasFile('user_logo')) {
            $file = $request->file('user_logo');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->storeAs('user_logos', $filename, 'public');
            $validatedData['user_logo'] = $filename;
        }

        // Create and return the new user
        return self::create($validatedData);
    }

    public function updateUser(array $validatedData, $request)
    {
        $this->user_name    = $validatedData['user_name'];
        $this->email        = $validatedData['email'];
        $this->user_role    = $validatedData['user_role'];

        // Handle file upload if exists
        if ($request->hasFile('user_logo')) {
            // Delete the old logo if it exists
            if ($this->user_logo) {
                Storage::disk('public')->delete('user_logos/' . $this->user_logo);
            }

            // Upload new logo
            $file = $request->file('user_logo');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->storeAs('user_logos', $filename, 'public');
            $this->user_logo = $filename;
        }
        return $this->save();
    }

    public function deleteUserWithLogo()
    {
        // Check if the user has a logo and delete it
        if (!empty($this->user_logo) && Storage::disk('public')->exists('user_logos/' . $this->user_logo)) {
            Storage::disk('public')->delete('user_logos/' . $this->user_logo);
        }
        return $this->delete();
    }
}
