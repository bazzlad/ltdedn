<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Artist;
use App\Models\Product;
use App\Models\ProductEdition;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ownerId = User::where('role', UserRole::Artist)->value('id');

        Artist::factory()->create(['owner_id' => $ownerId]);

        $artists = Artist::all();

        $artists->each(function (Artist $artist) {
            // Create 2-5 products per artist
            $products = Product::factory()
                ->count(fake()->numberBetween(2, 5))
                ->for($artist)
                ->create();

            // Create editions for each product
            $products->each(function (Product $product) {
                $editionCount = fake()->numberBetween(1, 10);

                for ($i = 1; $i <= $editionCount; $i++) {
                    ProductEdition::factory()
                        ->for($product)
                        ->create([
                            'number' => $i,
                            'owner_id' => null,
                        ]);
                }
            });
        });

        $this->command->info('Products and editions seeded successfully!');
    }
}
