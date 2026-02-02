<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AdminSeeder extends Seeder
{
    public function run()
    {
        // Create roles
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $staffRole = Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
        $customerRole = Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

        // Create permissions
        $permissions = [
            'view dashboard',
            'manage products',
            'manage categories',
            'manage brands',
            'manage orders',
            'manage customers',
            'manage coupons',
            'manage shipping',
            'manage pages',
            'manage banners',
            'manage settings',
            'manage users',
            'manage roles',
            'view reports',
            'export data'
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Assign all permissions to admin role
        $adminRole->syncPermissions($permissions);

        // Assign limited permissions to staff role
        $staffPermissions = [
            'view dashboard',
            'manage products',
            'manage categories',
            'manage orders',
            'manage customers',
            'view reports'
        ];
        $staffRole->syncPermissions($staffPermissions);

        // Create admin user
        $admin = User::firstOrCreate([
            'email' => 'admin@example.com'
        ], [
            'name' => 'Admin User',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
            'role' => 'admin'
        ]);

        $admin->assignRole($adminRole);

        // Create staff user
        $staff = User::firstOrCreate([
            'email' => 'staff@example.com'
        ], [
            'name' => 'Staff User',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
            'role' => 'staff'
        ]);

        $staff->assignRole($staffRole);

        // Create customer user
        $customer = User::firstOrCreate([
            'email' => 'customer@example.com'
        ], [
            'name' => 'Customer User',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
            'role' => 'customer'
        ]);

        $customer->assignRole($customerRole);

        $this->command->info('Admin, Staff, and Customer users created successfully!');
        $this->command->info('Admin Email: admin@example.com');
        $this->command->info('Staff Email: staff@example.com');
        $this->command->info('Customer Email: customer@example.com');
        $this->command->info('Password for all: password123');
    }
}