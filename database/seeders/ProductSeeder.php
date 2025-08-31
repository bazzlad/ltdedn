<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Artist;
use App\Models\Product;
use App\Models\ProductEdition;
use App\Models\User;
use App\Services\QRCodeService;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $qrService = app(QRCodeService::class);
        $testableEditions = [];

        $ownerId = User::where('role', UserRole::Artist)->value('id');

        Artist::factory()->create(['owner_id' => $ownerId]);

        $artists = Artist::all();

        $artists->each(function (Artist $artist) use (&$testableEditions, $qrService) {
            $products = Product::factory()
                ->count(fake()->numberBetween(2, 5))
                ->for($artist)
                ->create();

            $products->each(function (Product $product) use (&$testableEditions, $qrService, $artist) {
                $editionCount = fake()->numberBetween(1, 10);

                for ($i = 1; $i <= $editionCount; $i++) {
                    $edition = ProductEdition::factory()
                        ->for($product)
                        ->create([
                            'number' => $i,
                            'owner_id' => null,
                            'status' => 'available',
                        ]);

                    // Ensure QR code is generated
                    if (empty($edition->qr_code)) {
                        $edition->qr_code = $qrService->generateQRCode($product, $i);
                        $edition->save();
                    }

                    // Collect some editions for testing output
                    if (count($testableEditions) < 5) {
                        $testableEditions[] = [
                            'product_name' => $product->name,
                            'artist_name' => $artist->name,
                            'edition_number' => $i,
                            'qr_code' => $edition->qr_code,
                            'url' => config('app.url').'/qr/'.$edition->qr_code,
                        ];
                    }
                }
            });
        });

        $this->command->info('Products and editions seeded successfully!');
        $this->command->info('');
        $this->command->info('🧪 Test QR Codes (scan with your iPhone camera):');
        $this->command->info('');

        foreach ($testableEditions as $edition) {
            $this->command->info("📱 {$edition['product_name']} (by {$edition['artist_name']}) - Edition #{$edition['edition_number']}");
            $this->command->info("   URL: {$edition['url']}");
            $this->command->info('');
        }
    }
}
