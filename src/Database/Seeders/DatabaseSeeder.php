<?php

declare(strict_types=1);

namespace Horizon\Database\Seeders;

use Horizon\Database\Seeder;

/**
 * Database Seeder
 * 
 * Main seeder class that orchestrates all other seeders.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Call other seeders here
        // $this->call([
        //     UserSeeder::class,
        //     PostSeeder::class,
        //     CategorySeeder::class,
        // ]);

        // Or seed data directly
        // $this->seedUsers();
        // $this->seedPosts();
    }

    /**
     * Seed users table.
     */
    protected function seedUsers(): void
    {
        // Example of seeding users
        $users = [
            [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'password' => password_hash('password', PASSWORD_DEFAULT),
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Demo User',
                'email' => 'demo@example.com',
                'password' => password_hash('password', PASSWORD_DEFAULT),
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        $connection = $this->getConnection();
        
        foreach ($users as $user) {
            $connection->table('users')->insert($user);
        }
    }

    /**
     * Seed posts table.
     */
    protected function seedPosts(): void
    {
        // Example of seeding posts with factory-style approach
        $posts = [];
        
        $titles = [
            'Getting Started with Horizon Framework',
            'Building RESTful APIs',
            'Database Migrations Guide',
            'Advanced ORM Relationships',
            'Testing Your Application',
        ];
        
        $contents = [
            'This is a comprehensive guide to getting started...',
            'RESTful APIs are the backbone of modern web applications...',
            'Database migrations allow you to version your database schema...',
            'Relationships are a powerful feature of the ORM...',
            'Testing is an essential part of application development...',
        ];

        for ($i = 0; $i < 5; $i++) {
            $posts[] = [
                'title' => $titles[$i],
                'content' => $contents[$i],
                'user_id' => $this->randomNumber(1, 2), // Reference seeded users
                'status' => $this->randomElement(['published', 'draft']),
                'published_at' => $this->randomDate('-6 months'),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $connection = $this->getConnection();
        
        foreach ($posts as $post) {
            $connection->table('posts')->insert($post);
        }
    }

    /**
     * Helper function to get current timestamp.
     */
    protected function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}