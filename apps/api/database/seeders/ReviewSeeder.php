<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Review;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Seeds the four REAL testimonials from the 2026 course brochures. The bulk
 * Google/WhatsApp reviews are imported with `php artisan reviews:import` from
 * real exports — never fabricated here.
 */
class ReviewSeeder extends Seeder
{
    /**
     * @var list<array{author_name: string, body: string}>
     */
    private const BROCHURE_TESTIMONIALS = [
        [
            'author_name' => 'KR Sampritha',
            'body' => "Big thanks to Trainer Krish for being such a motivating and knowledgeable guide. Your way of teaching with real-life examples made everything so easy to understand. You're not just a trainer, but a true mentor!",
        ],
        [
            'author_name' => 'Anish Lakumarapu',
            'body' => 'The course is super well-structured, covering everything from ETL processes to data storage tools. Instead of just theory, I got to work on actual data pipelines and industry-relevant case studies.',
        ],
        [
            'author_name' => 'Saket Vaibhav',
            'body' => 'I would like to sincerely thank BrowseJobs for their incredible support in helping me find a job. Krish Sir, you are doing a wonderful job in helping students who dream of working in the IT industry.',
        ],
        [
            'author_name' => 'Arbaz Khan',
            'body' => 'I recommend BrowseJobs to anyone looking to advance their career in IT. The course was comprehensive, well-structured, and tailored. My tutor was always available to clear any doubts.',
        ],
    ];

    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'browsejobs')->firstOrFail();

        foreach (self::BROCHURE_TESTIMONIALS as $order => $testimonial) {
            Review::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'author_name' => $testimonial['author_name']],
                [
                    'body' => $testimonial['body'],
                    'rating' => 5,
                    'source' => 'platform',
                    'is_active' => true,
                    'sort_order' => $order,
                ],
            );
        }
    }
}
