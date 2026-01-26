<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Artist;
use App\Models\Product;
use App\Models\ProductEdition;
use App\Models\User;
use App\Services\QRCodeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $qrService = app(QRCodeService::class);
        $testableEditions = [];

        $arnoUser = User::firstOrCreate(
            ['email' => 'online@arnop.nl'],
            [
                'name' => 'Arno Poot',
                'password' => bcrypt('password'),
                'role' => UserRole::Admin,
                'email_verified_at' => now(),
            ]
        );

        $richardUser = User::firstOrCreate(
            ['email' => 'richard@barringtonmedia.co.uk'],
            [
                'name' => 'Richard Barrington-Hill',
                'password' => bcrypt('password'),
                'role' => UserRole::Admin,
                'email_verified_at' => now(),
            ]
        );

        $darrenUser = User::firstOrCreate(
            ['email' => 'darren@ltdedn.com'],
            [
                'name' => 'Darren',
                'password' => bcrypt('password'),
                'role' => UserRole::Admin,
                'email_verified_at' => now(),
            ]
        );

        $artists = [
            ['name' => 'Audrey Kawasaki'],
            ['name' => 'Fin Dac'],
            ['name' => 'The London Police'],
            ['name' => 'tokidoki'],
            ['name' => 'Tom Lewis'],
            ['name' => 'Audrey Kawasaki'],
            ['name' => 'LTD/EDN', 'owner_id' => $darrenUser->id],
        ];

        foreach ($artists as $artistData) {
            $artist = Artist::firstOrCreate(
                ['slug' => Str::slug($artistData['name'])],
                [
                    'name' => $artistData['name'],
                    'owner_id' => $artistData['owner_id'] ?? null,
                ]
            );

            if ($artist->name === 'Audrey Kawasaki') {
                $this->createProduct($artist, [
                    'name' => 'Time Will Tell Pillow',
                    'slug' => 'time-will-tell-pillow',
                    'description' => "The best, made better. \n\nYes, we've improved our much loved \npillows. Now with double-sided printing \nand upscaled fabric to a beautiful soft \nvelvet as standard.\n\nOur fabric manufacturer has produced \nthis new fabric especially for us so it \nprints and retains rich colours and high \ndefinition detailing.\n\nWe are even offering a real silk version for a really high end collectors option, shipped boxed with a handsigned print.\n\nThey ship with either a feather pad - made from waste materials - or a vegan \nalternative from recycled plastic if preferred.\n\nRather than our labels, each pillow has the artists own logo, title of the piece, number of the edition - with the unique QR code printed",
                    'base_price' => 99.00,
                    'edition_size' => 10,
                    'is_limited' => true,
                    'sell_through_ltdedn' => false,
                    'is_public' => false,
                ]);

                $this->createProduct($artist, [
                    'name' => 'The Offering Throw',
                    'slug' => 'the-offering-throw',
                    'description' => "Huge, cosy, beautiful representations of our artists work. \n\nThe upper layer is a premium printed polar fleece - irresistibly soft to the touch - bonded with a deep grey faux fur backing for a plush, weighted feel. A hand-sewn jumbo blanket-stitch hem completes the finish.\n\nEach design is brought to life through a precision thermo - sublimation process, delivering exceptional colour depth, definition and vibrancy that lasts.\n\nMeasuring 2000 × 1500mm, the blanket's generous scale makes it a true statement piece. Every blanket carries its bespoke woven label, detailing the sequential edition number, artist logo, title, and a unique QR code to register ownership and verify authenticity.",
                    'base_price' => 195.00,
                    'edition_size' => 300,
                    'is_limited' => true,
                    'sell_through_ltdedn' => false,
                    'is_public' => false,
                ]);
            }

            if ($artist->name === 'LTD/EDN') {
                $this->createProduct($artist, [
                    'name' => 'Black Down Puffer',
                    'slug' => 'black-down-puffer',
                    'description' => "Our custom down puffer jacket is crafted from premium \nsustainable fabrics, engineered for lasting performance. \nOur most technical jacket\n\nThe outer shell features a water repellent, soft-touch recycled \nNautica fabric built for the elements. \n\nInsulated with THERMORE® recycled fill down, made entirely from post-consumer plastic bottles, it delivers lightweight warmth \nwithout compromise.\n\nEvery detail is intentional: fleece-lined side pockets for comfort, \nan internal pocket for essentials, and a refined yet durable riri zip closure. \n\nThe result is a jacket that merges luxury aesthetics with technical \nprecision.\n\nSafe to say we're proud of this one.",
                    'base_price' => 295.00,
                    'edition_size' => 88,
                    'is_limited' => true,
                    'sell_through_ltdedn' => false,
                    'is_public' => false,
                ]);

                $this->createProduct($artist, [
                    'name' => 'Black Pillow',
                    'slug' => 'black-pillow',
                    'description' => "The best, made better. \n\nYes, we've improved our much loved \npillows. Now with double-sided printing \nand upscaled fabric to a beautiful soft \nvelvet as standard.\n\nOur fabric manufacturer has produced \nthis new fabric especially for us so it \nprints and retains rich colours and high \ndefinition detailing.\n\nWe are even offering a real silk version for a really high end collectors option, shipped boxed with a handsigned print.\n\nThey ship with either a feather pad - made from waste materials - or a vegan \nalternative from recycled plastic if preferred.\n\nRather than our labels, each pillow has the artists own logo, title of the piece, number of the edition - with the unique QR code printed",
                    'base_price' => 99.00,
                    'edition_size' => 88,
                    'is_limited' => true,
                    'sell_through_ltdedn' => false,
                    'is_public' => false,
                ]);

                $this->createProduct($artist, [
                    'name' => 'White Pillow',
                    'slug' => 'white-pillow',
                    'description' => "The best, made better. \n\nYes, we've improved our much loved \npillows. Now with double-sided printing \nand upscaled fabric to a beautiful soft \nvelvet as standard.\n\nOur fabric manufacturer has produced \nthis new fabric especially for us so it \nprints and retains rich colours and high \ndefinition detailing.\n\nWe are even offering a real silk version for a really high end collectors option, shipped boxed with a handsigned print.\n\nThey ship with either a feather pad - made from waste materials - or a vegan \nalternative from recycled plastic if preferred.\n\nRather than our labels, each pillow has the artists own logo, title of the piece, number of the edition - with the unique QR code printed",
                    'base_price' => 99.00,
                    'edition_size' => 88,
                    'is_limited' => true,
                    'sell_through_ltdedn' => false,
                    'is_public' => false,
                ]);

                $this->createProduct($artist, [
                    'name' => 'White Floated Frame',
                    'slug' => 'white-floated-frame',
                    'description' => "This new premium framed art edition \nrepresents our highest standard in \nfine art presentation.\n\nEach print is hand-signed and hand-numbered by the artist, available in two size formats - A2 and A1. Printed on 330gsm Somerset Velvet paper, \nrenowned for its rich texture and \narchival quality, every piece features a deckled torn edge, honouring traditional printmaking craftsmanship. \n\nEach work is then embossed with the artist's insignia.\n\nThe print is elevated and floated within a bespoke made frame, creating a sense of depth that accentuates the artwork.",
                    'base_price' => 395.00,
                    'edition_size' => null,
                    'is_limited' => true,
                    'sell_through_ltdedn' => false,
                    'is_public' => false,
                ]);

                $this->createProduct($artist, [
                    'name' => 'Black Parka',
                    'slug' => 'black-parka',
                    'description' => "We've worked long and hard to create this stunning new statement apparel edition.\n\nBorn from the idea that some artworks are so bold they might not suit every mood or occasion, this reversible coat offers two distinct sides: one calm and understated, the \nother vibrant and expressive -ready for when the wearer feels as bold as the art itself.\n\nThe coat comes in two material options: a 400gsm printed cotton bull denim, or a more technical model made from our 155gsm waterproof fabric with a 220gsm microfibre svelte towelling reverse - both exceptionally comfortable against the skin.\n\nFinished with a double-sided heavyweight zip and reversible poppers, every detail ensures the coat looks refined and functions beautifully whichever side you choose to show the world.",
                    'base_price' => 595.00,
                    'edition_size' => 88,
                    'is_limited' => true,
                    'sell_through_ltdedn' => false,
                    'is_public' => false,
                ]);

                $this->createProduct($artist, [
                    'name' => 'I am LTDEDN T-Shirt',
                    'slug' => 'i-am-ltdedn-t-shirt',
                    'description' => "When approaching our clothing editions - we wanted to create garments that really elevated an essential day-to-day garment to a genuine wearable piece of art, one that reflected it's creative source - real art - paint, pencils, texture, depth. \n\nThat begins with the humble tee,  alongside the development of a uniquely complex approach, combining hybrid printing methods that push the boundaries of what a T-shirt can be.\n\nThe cut itself is a boxy, oversized fit with dropped shoulders and a twin-stitched wide neck. With a slightly shorter length, extra width and dropped shoulders for a boxy silhouette. The perfect blank for printing with its smooth finish and heavy weight 220gsm, 22-singles 100% combed cotton. Built to last with neck ribbing, side seams, shoulder-to-shoulder tape, and double needle hems, plus preshrunk fabric for minimal shrinkage.\n\nSoon we will also be offering a 140gsm lightweight cotton, which has a finished with a soft garment-dyed wash for a lived-in, more vintage feel.\n\nArtist designs can be applied to the fabrics using a variety of print techniques - Layered screen printed transfers, DTG, DTF, standard screen print and sublimation all-over print - or a \ncreative combination of these for even extra depth and texture - plus for the first time - we're also introducing embroidery and patch detailing alongside our printing techniques.\n\nEach garment will, of course, be individually numbered, marking it as part of a genuine limited edition, along-side the artist name, title and the QR code linked to our authentication process, then shipped in our printed T-Shirt boxes.",
                    'base_price' => 75.00,
                    'edition_size' => 88,
                    'is_limited' => true,
                    'sell_through_ltdedn' => false,
                    'is_public' => false,
                ]);

                $this->createProduct($artist, [
                    'name' => 'Je suis edition limité T-Shirt',
                    'slug' => 'je-suis-edition-limite-t-shirt',
                    'description' => "When approaching our clothing editions - we wanted to create garments that really elevated an essential day-to-day garment to a genuine wearable piece of art, one that reflected it's creative source - real art - paint, pencils, texture, depth. \n\nThat begins with the humble tee,  alongside the development of a uniquely complex approach, combining hybrid printing methods that push the boundaries of what a T-shirt can be.\n\nThe cut itself is a boxy, oversized fit with dropped shoulders and a twin-stitched wide neck. With a slightly shorter length, extra width and dropped shoulders for a boxy silhouette. The perfect blank for printing with its smooth finish and heavy weight 220gsm, 22-singles 100% combed cotton. Built to last with neck ribbing, side seams, shoulder-to-shoulder tape, and double needle hems, plus preshrunk fabric for minimal shrinkage.\n\nSoon we will also be offering a 140gsm lightweight cotton, which has a finished with a soft garment-dyed wash for a lived-in, more vintage feel.\n\nArtist designs can be applied to the fabrics using a variety of print techniques - Layered screen printed transfers, DTG, DTF, standard screen print and sublimation all-over print - or a \ncreative combination of these for even extra depth and texture - plus for the first time - we're also introducing embroidery and patch detailing alongside our printing techniques.\n\nEach garment will, of course, be individually numbered, marking it as part of a genuine limited edition, along-side the artist name, title and the QR code linked to our authentication process, then shipped in our printed T-Shirt boxes.",
                    'base_price' => 75.00,
                    'edition_size' => 88,
                    'is_limited' => true,
                    'sell_through_ltdedn' => false,
                    'is_public' => false,
                ]);

                $this->createProduct($artist, [
                    'name' => 'I am turning Japanese T-Shirt',
                    'slug' => 'i-am-turning-japanese-t-shirt',
                    'description' => "When approaching our clothing editions - we wanted to create garments that really elevated an essential day-to-day garment to a genuine wearable piece of art, one that reflected it's creative source - real art - paint, pencils, texture, depth. \n\nThat begins with the humble tee,  alongside the development of a uniquely complex approach, combining hybrid printing methods that push the boundaries of what a T-shirt can be.\n\nThe cut itself is a boxy, oversized fit with dropped shoulders and a twin-stitched wide neck. With a slightly shorter length, extra width and dropped shoulders for a boxy silhouette. The perfect blank for printing with its smooth finish and heavy weight 220gsm, 22-singles 100% combed cotton. Built to last with neck ribbing, side seams, shoulder-to-shoulder tape, and double needle hems, plus preshrunk fabric for minimal shrinkage.\n\nSoon we will also be offering a 140gsm lightweight cotton, which has a finished with a soft garment-dyed wash for a lived-in, more vintage feel.\n\nArtist designs can be applied to the fabrics using a variety of print techniques - Layered screen printed transfers, DTG, DTF, standard screen print and sublimation all-over print - or a \ncreative combination of these for even extra depth and texture - plus for the first time - we're also introducing embroidery and patch detailing alongside our printing techniques.\n\nEach garment will, of course, be individually numbered, marking it as part of a genuine limited edition, along-side the artist name, title and the QR code linked to our authentication process, then shipped in our printed T-Shirt boxes.",
                    'base_price' => 75.00,
                    'edition_size' => 88,
                    'is_limited' => true,
                    'sell_through_ltdedn' => false,
                    'is_public' => false,
                ]);
            }
        }

        $products = Product::with('artist')->whereNotNull('edition_size')->get();

        $products->each(function (Product $product) use (&$testableEditions, $qrService) {
            $existingEditionsCount = $product->editions()->count();

            if ($existingEditionsCount >= $product->edition_size) {
                return;
            }

            $this->command->info("Generating editions for {$product->name} ({$existingEditionsCount}/{$product->edition_size})");

            for ($i = 1; $i <= $product->edition_size; $i++) {
                $existingEdition = ProductEdition::where('product_id', $product->id)
                    ->where('number', $i)
                    ->first();

                if ($existingEdition) {
                    continue;
                }

                $edition = ProductEdition::factory()
                    ->for($product)
                    ->create([
                        'number' => $i,
                        'owner_id' => null,
                        'status' => 'available',
                    ]);

                if (empty($edition->qr_code)) {
                    $edition->qr_code = $qrService->generateQRCode($product, $i);
                    $edition->save();
                }

                if (count($testableEditions) < 5) {
                    $testableEditions[] = [
                        'product_name' => $product->name,
                        'artist_name' => $product->artist->name,
                        'edition_number' => $i,
                        'qr_code' => $edition->qr_code,
                        'url' => config('app.url').'/qr/'.$edition->qr_code,
                    ];
                }
            }
        });

        $this->command->info('');
        $this->command->info('✅ Products and editions seeded successfully!');

        if (count($testableEditions) > 0) {
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

    private function createProduct(Artist $artist, array $data): void
    {
        $coverImagePath = null;

        $imageSourcePath = database_path("seeders/data/{$data['slug']}.png");

        if (File::exists($imageSourcePath)) {
            $storagePath = "products/{$data['slug']}.png";

            Storage::disk('public')->put(
                $storagePath,
                File::get($imageSourcePath)
            );

            $coverImagePath = $storagePath;
            $this->command->info("  ✓ Uploaded image for {$data['name']}");
        } else {
            $this->command->warn("  ⚠ No image found for {$data['slug']}.png");
        }

        Product::firstOrCreate(
            ['slug' => $data['slug']],
            [
                'artist_id' => $artist->id,
                'name' => $data['name'],
                'description' => $data['description'],
                'base_price' => $data['base_price'],
                'edition_size' => $data['edition_size'],
                'is_limited' => $data['is_limited'],
                'sell_through_ltdedn' => $data['sell_through_ltdedn'],
                'is_public' => $data['is_public'],
                'cover_image' => $coverImagePath,
            ]
        );
    }
}
