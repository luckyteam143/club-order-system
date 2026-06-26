<?php

namespace Database\Seeders;

use App\Models\Club;
use App\Models\Package;
use App\Models\Product;
use App\Models\SponsorLogo;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Admin
        User::create([
            'name'     => 'Admin',
            'email'    => 'admin@example.com',
            'password' => Hash::make('password'),
            'role'     => 'admin',
        ]);

        // Clubs
        $yorkHeat = Club::create([
            'name'    => 'York Heat',
            'email'   => 'yorkh@example.com',
            'phone'   => '07700000001',
            'status'  => 'active',
        ]);

        $cityFlyers = Club::create([
            'name'    => 'City Flyers',
            'email'   => 'cityf@example.com',
            'phone'   => '07700000002',
            'status'  => 'active',
        ]);

        // Club users
        User::create([
            'name'     => 'York Heat Manager',
            'email'    => 'york@example.com',
            'password' => Hash::make('password'),
            'role'     => 'club',
            'club_id'  => $yorkHeat->id,
        ]);

        // Products
        $products = [
            ['name' => 'Sceptrum Shirt', 'parent_sku' => 'SCEPTRUM', 'default_sku' => 'SCEP-ROY-M', 'size' => 'XS', 'qty' => 24, 'retail_price' => 38.00],
            ['name' => 'Sceptrum Shirt', 'parent_sku' => 'SCEPTRUM', 'default_sku' => 'SCEP-ROY-S', 'size' => 'S',  'qty' => 47, 'retail_price' => 38.00],
            ['name' => 'Sceptrum Shirt', 'parent_sku' => 'SCEPTRUM', 'default_sku' => 'SCEP-ROY-M', 'size' => 'M',  'qty' => 52, 'retail_price' => 38.00],
            ['name' => 'Sceptrum Shirt', 'parent_sku' => 'SCEPTRUM', 'default_sku' => 'SCEP-ROY-L', 'size' => 'L',  'qty' => 31, 'retail_price' => 38.00],
            ['name' => 'Sceptrum Shirt', 'parent_sku' => 'SCEPTRUM', 'default_sku' => 'SCEP-ROY-XL','size' => 'XL', 'qty' => 18, 'retail_price' => 38.00],
            ['name' => 'Halley Shirt',   'parent_sku' => 'HALLEY',   'default_sku' => 'HAL-YEL-S',  'size' => 'S',  'qty' => 35, 'retail_price' => 35.00],
            ['name' => 'Halley Shirt',   'parent_sku' => 'HALLEY',   'default_sku' => 'HAL-YEL-M',  'size' => 'M',  'qty' => 40, 'retail_price' => 35.00],
            ['name' => 'Halley Shirt',   'parent_sku' => 'HALLEY',   'default_sku' => 'HAL-YEL-L',  'size' => 'L',  'qty' => 22, 'retail_price' => 35.00],
            ['name' => 'Skara Eco Shorts','parent_sku' => 'SKARA',   'default_sku' => 'SKA-BLK-S',  'size' => 'S',  'qty' => 5,  'retail_price' => 22.00],
            ['name' => 'Skara Eco Shorts','parent_sku' => 'SKARA',   'default_sku' => 'SKA-BLK-M',  'size' => 'M',  'qty' => 30, 'retail_price' => 22.00],
            ['name' => 'Skara Eco Shorts','parent_sku' => 'SKARA',   'default_sku' => 'SKA-BLK-L',  'size' => 'L',  'qty' => 20, 'retail_price' => 22.00],
            ['name' => 'Hoops Socks',    'parent_sku' => 'HOOPS',    'default_sku' => 'HPS-S',       'size' => 'S',  'qty' => 30, 'retail_price' => 10.00],
            ['name' => 'Hoops Socks',    'parent_sku' => 'HOOPS',    'default_sku' => 'HPS-M',       'size' => 'M',  'qty' => 45, 'retail_price' => 10.00],
            ['name' => 'Hoops Socks',    'parent_sku' => 'HOOPS',    'default_sku' => 'HPS-L',       'size' => 'L',  'qty' => 28, 'retail_price' => 10.00],
            ['name' => 'Training Jacket','parent_sku' => 'JACKET',   'default_sku' => 'JAK-M',       'size' => 'M',  'qty' => 20, 'retail_price' => 55.00],
            ['name' => 'Training Jacket','parent_sku' => 'JACKET',   'default_sku' => 'JAK-L',       'size' => 'L',  'qty' => 12, 'retail_price' => 55.00],
        ];

        $productModels = [];
        foreach ($products as $p) {
            $productModels[] = Product::create($p);
        }

        // Sponsor logos
        SponsorLogo::create([
            'name'      => 'York Heat Badge',
            'positions' => ['Front Chest Left', 'Back Top'],
        ]);
        SponsorLogo::create([
            'name'      => 'Nike',
            'positions' => ['Front Chest Right', 'Left Sleeve'],
        ]);

        // Package for York Heat (Full Match Kit)
        $package = Package::create([
            'name'    => 'Full Match Kit',
            'club_id' => $yorkHeat->id,
            'price'   => 70.00,
            'status'  => 'active',
        ]);

        // Attach shirt M, shorts M, socks M to package
        $shirtM   = Product::where('name', 'Sceptrum Shirt')->where('size', 'M')->first();
        $shortsM  = Product::where('name', 'Skara Eco Shorts')->where('size', 'M')->first();
        $socksM   = Product::where('name', 'Hoops Socks')->where('size', 'M')->first();

        $package->products()->attach([
            $shirtM->id  => ['qty' => 1, 'per_item_price' => 38.00],
            $shortsM->id => ['qty' => 1, 'per_item_price' => 22.00],
            $socksM->id  => ['qty' => 1, 'per_item_price' => 10.00],
        ]);
    }
}
