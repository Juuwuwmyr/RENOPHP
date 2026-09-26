<?php

declare(strict_types=1);

namespace Reno\Database\Seeders;

use Reno\Database\Seeder;

/**
 * User Seeder
 * 
 * Seeds the users table with initial data.
 */
class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $connection = $this->getConnection();

        // Clear existing data
        $connection->table('users')->truncate();

        // Seed admin user
        $connection->table('users')->insert([
            'name' => 'Administrator',
            'email' => 'admin@horizon.dev',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'email_verified_at' => $this->now(),
            'is_admin' => true,
            'status' => 'active',
            'created_at' => $this->now(),
            'updated_at' => $this->now(),
        ]);

        // Seed regular users
        $users = $this->generateUsers(10);
        
        foreach ($users as $user) {
            $connection->table('users')->insert($user);
        }

        // Alternative: Using factory if available
        // $this->create(User::class, 10);
    }

    /**
     * Generate user data.
     */
    protected function generateUsers(int $count): array
    {
        $users = [];
        $firstNames = ['John', 'Jane', 'Mike', 'Sarah', 'David', 'Lisa', 'Tom', 'Emma', 'Chris', 'Anna'];
        $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez'];
        $domains = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'company.com'];

        for ($i = 0; $i < $count; $i++) {
            $firstName = $this->randomElement($firstNames);
            $lastName = $this->randomElement($lastNames);
            $email = strtolower($firstName . '.' . $lastName . '@' . $this->randomElement($domains));

            $users[] = [
                'name' => $firstName . ' ' . $lastName,
                'email' => $email,
                'password' => password_hash('password', PASSWORD_DEFAULT),
                'email_verified_at' => $this->randomBoolean(0.8) ? $this->randomDate('-1 year') : null,
                'is_admin' => false,
                'status' => $this->randomElement(['active', 'inactive', 'pending']),
                'age' => $this->randomNumber(18, 65),
                'created_at' => $this->randomDate('-2 years'),
                'updated_at' => $this->randomDate('-1 month'),
            ];
        }

        return $users;
    }

    /**
     * Get current timestamp.
     */
    protected function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}