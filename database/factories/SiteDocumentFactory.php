<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\SiteDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteDocument>
 */
class SiteDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->sentence(12),
            'content' => '# '.fake()->sentence(3)."\n\n".fake()->paragraphs(3, true),
            'original_filename' => fake()->slug(2).'.md',
        ];
    }
}
