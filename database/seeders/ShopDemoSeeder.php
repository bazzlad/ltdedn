<?php

namespace Database\Seeders;

use App\Models\Artist;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class ShopDemoSeeder extends Seeder
{
    /**
     * Turn the LTD/EDN demo catalogue (seeded by ProductSeeder) into a
     * browseable shop: add a bio + hero to the artist, flip the products
     * to public/sellable so they appear on /shop and /shop/ltd-edn.
     *
     * Idempotent — safe to re-run.
     */
    public function run(): void
    {
        $artist = Artist::query()->where('slug', 'ltdedn')->first();

        if (! $artist) {
            $this->command->warn('ShopDemoSeeder: LTD/EDN artist not found — run ProductSeeder first.');

            return;
        }

        $this->attachHeroAndBio($artist);

        $flipped = Product::query()
            ->where('artist_id', $artist->id)
            ->update([
                'is_public' => true,
                'is_sellable' => true,
                'sell_through_ltdedn' => true,
                'sale_status' => 'active',
            ]);

        $this->command->info("ShopDemoSeeder: LTD/EDN catalogue flipped to shop-visible ({$flipped} products).");
        $this->command->info('  /shop                  → grid of available editions');
        $this->command->info("  /shop/{$artist->slug}  → artist landing with hero + bio");
    }

    private function attachHeroAndBio(Artist $artist): void
    {
        $updates = [];

        if (empty($artist->bio)) {
            $updates['bio'] = "LTD/EDN is building premium infrastructure for the next generation of artist-led commerce, releasing authenticated, physical limited-edition capsule collections — from fine art prints to apparel and homeware.\n\nEach release is supported by QR-linked digital certification, providing verifiable authenticity, edition transparency and long-term provenance tracking.";
        }

        if (empty($artist->hero_image)) {
            $source = database_path('seeders/data/white-floated-frame.png');
            if (File::exists($source)) {
                $storagePath = 'artists/ltdedn-hero.png';
                Storage::disk('public')->put($storagePath, File::get($source));
                $updates['hero_image'] = $storagePath;
            }
        }

        if ($updates !== []) {
            $artist->update($updates);
            $this->command->info('ShopDemoSeeder: updated artist fields — '.implode(', ', array_keys($updates)));
        }
    }
}
