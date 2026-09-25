<?php

declare(strict_types=1);

namespace Horizon\Database\Seeders;

use Horizon\Database\Seeder;

/**
 * Post Seeder
 * 
 * Seeds the posts table with sample blog posts.
 */
class PostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $connection = $this->getConnection();

        // Clear existing data
        $connection->table('posts')->truncate();

        // Get existing user IDs
        $userIds = $connection->table('users')->pluck('id');
        
        if (empty($userIds)) {
            throw new \RuntimeException('No users found. Please run UserSeeder first.');
        }

        // Generate posts
        $posts = $this->generatePosts(50, $userIds);
        
        foreach ($posts as $post) {
            $connection->table('posts')->insert($post);
        }
    }

    /**
     * Generate post data.
     */
    protected function generatePosts(int $count, array $userIds): array
    {
        $posts = [];
        
        $titleTemplates = [
            'Getting Started with {technology}',
            'Advanced {technology} Techniques',
            'Best Practices for {technology}',
            'Common {technology} Mistakes to Avoid',
            'Building {projects} with {technology}',
            'The Future of {technology}',
            'Optimizing {technology} Performance',
            'Testing {technology} Applications',
            'Deploying {technology} to Production',
            'Debugging {technology} Issues'
        ];
        
        $technologies = [
            'PHP', 'Laravel', 'Symfony', 'JavaScript', 'React', 'Vue.js', 
            'Node.js', 'Python', 'Docker', 'MySQL', 'PostgreSQL', 'Redis'
        ];
        
        $projects = [
            'Web Applications', 'APIs', 'Microservices', 'E-commerce Sites',
            'Dashboards', 'Mobile Apps', 'Real-time Systems', 'Data Pipelines'
        ];

        $contentParagraphs = [
            "Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.",
            "Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.",
            "Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.",
            "Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
            "Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium.",
            "Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores.",
            "Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit.",
            "At vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis praesentium voluptatum deleniti atque corrupti."
        ];

        for ($i = 0; $i < $count; $i++) {
            $technology = $this->randomElement($technologies);
            $project = $this->randomElement($projects);
            $titleTemplate = $this->randomElement($titleTemplates);
            
            $title = str_replace(
                ['{technology}', '{projects}'],
                [$technology, $project],
                $titleTemplate
            );

            // Generate content with 3-5 paragraphs
            $paragraphCount = $this->randomNumber(3, 5);
            $contentParts = $this->randomElements($contentParagraphs, $paragraphCount);
            $content = implode("\n\n", $contentParts);

            $createdDate = $this->randomDate('-1 year');
            $status = $this->randomElement(['published', 'draft', 'scheduled']);
            
            $publishedAt = null;
            if ($status === 'published') {
                $publishedAt = $this->randomDate($createdDate, 'now');
            } elseif ($status === 'scheduled') {
                $publishedAt = $this->randomDate('now', '+1 month');
            }

            $posts[] = [
                'user_id' => $this->randomElement($userIds),
                'title' => $title,
                'slug' => $this->slugify($title),
                'content' => $content,
                'excerpt' => substr($content, 0, 150) . '...',
                'status' => $status,
                'published_at' => $publishedAt,
                'view_count' => $this->randomNumber(0, 5000),
                'like_count' => $this->randomNumber(0, 500),
                'comment_count' => $this->randomNumber(0, 100),
                'featured' => $this->randomBoolean(0.2),
                'meta_title' => $title,
                'meta_description' => substr($content, 0, 160),
                'created_at' => $createdDate,
                'updated_at' => $this->randomDate($createdDate, 'now'),
            ];
        }

        return $posts;
    }

    /**
     * Convert title to URL-friendly slug.
     */
    protected function slugify(string $title): string
    {
        $slug = strtolower($title);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/\s+/', '-', trim($slug));
        $slug = preg_replace('/-+/', '-', $slug);
        
        return trim($slug, '-');
    }
}