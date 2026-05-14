<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $categories = Category::pluck('id')->toArray();
        
        $productNames = [
            'Wireless Bluetooth Headphones',
            'Smart Watch Series 5',
            'Laptop Stand Adjustable',
            'USB-C Charging Cable',
            'Portable Power Bank 20000mAh',
            'Mechanical Gaming Keyboard',
            'Wireless Mouse Ergonomic',
            'HD Webcam 1080p',
            'Smartphone Case Premium',
            'Tablet Screen Protector',
            'Smart Home Speaker',
            'Wireless Charging Pad',
            'LED Desk Lamp',
            'Bluetooth Speaker Portable',
            'Fitness Tracker Band',
            'Phone Tripod Mount',
            'Laptop Backpack',
            'Screen Cleaning Kit',
            'Cable Management Kit',
            'Men\'s Cotton T-Shirt',
            'Women\'s Denim Jeans',
            'Running Sneakers',
            'Leather Belt',
            'Sports Cap',
            'Winter Jacket',
            'Socks Pack (5 Pairs)',
            'Formal Shirt',
            'Casual Shorts',
            'Handbag Leather',
            'Rice Cooker 1.8L',
            'Non-Stick Pan Set',
            'Stainless Steel Pot',
            'Electric Kettle',
            'Blender 500W',
            'Microwave Oven 20L',
            'Air Fryer Digital',
            'Coffee Maker Machine',
            'Vacuum Cleaner',
            'Moisturizer Cream 100ml',
            'Shampoo 500ml',
            'Face Serum Vitamin C',
            'Lip Balm Set',
            'Hair Dryer Professional',
            'Makeup Brush Set',
            'Perfume 50ml',
            'Sunscreen SPF50',
            'Football Jersey',
            'Basketball Official',
            'Yoga Mat Premium',
            'Dumbbells Set',
            'Resistance Bands',
            'Tennis Racket',
            'Cricket Bat',
            'Swimming Goggles',
            'Running Shoes',
            'Fiction Novel Bestseller',
            'Cookbook Collection',
            'Children\'s Storybook',
            'Educational Workbook',
            'Board Game Classic',
            'Chess Set Wooden',
            'Puzzle 1000 Pieces',
            'Action Figure Toy',
            'Building Blocks Set',
            'Remote Control Car',
            'Plush Teddy Bear',
            'Educational Toy Set',
            'Rice 5kg Pack',
            'Coffee Beans 1kg',
            'Tea Variety Pack',
            'Snack Pack Mix',
            'Bottled Water 24 Pack',
            'Instant Noodles Box',
            'Cereal Box',
            'Vitamin C Tablets',
            'Fish Oil Supplements',
            'Protein Powder',
            'First Aid Kit',
            'Hand Sanitizer',
            'Face Mask Pack',
            'Car Phone Mount',
            'Car Vacuum Cleaner',
            'Dash Camera HD',
            'Tire Pressure Gauge',
            'Car Air Freshener',
            'Car Seat Cover',
            'Motorcycle Helmet',
        ];

        $categoryId = $this->faker->randomElement($categories);
        
        // Generate non-unique base name and add variant
        $baseNames = [
            'Wireless Bluetooth Headphones',
            'Smart Watch Series 5',
            'Laptop Stand Adjustable',
            'USB-C Charging Cable',
            'Portable Power Bank 20000mAh',
            'Mechanical Gaming Keyboard',
            'Wireless Mouse Ergonomic',
            'HD Webcam 1080p',
            'Smartphone Case Premium',
            'Tablet Screen Protector',
            'Smart Home Speaker',
            'Wireless Charging Pad',
            'LED Desk Lamp',
            'Bluetooth Speaker Portable',
            'Fitness Tracker Band',
            'Phone Tripod Mount',
            'Laptop Backpack',
            'Screen Cleaning Kit',
            'Cable Management Kit',
            'Men\'s Cotton T-Shirt',
            'Women\'s Denim Jeans',
            'Running Sneakers',
            'Leather Belt',
            'Sports Cap',
            'Winter Jacket',
            'Socks Pack (5 Pairs)',
            'Formal Shirt',
            'Casual Shorts',
            'Handbag Leather',
            'Rice Cooker 1.8L',
            'Non-Stick Pan Set',
            'Stainless Steel Pot',
            'Electric Kettle',
            'Blender 500W',
            'Microwave Oven 20L',
            'Air Fryer Digital',
            'Coffee Maker Machine',
            'Vacuum Cleaner',
            'Moisturizer Cream 100ml',
            'Shampoo 500ml',
            'Face Serum Vitamin C',
            'Lip Balm Set',
            'Hair Dryer Professional',
            'Makeup Brush Set',
            'Perfume 50ml',
            'Sunscreen SPF50',
            'Football Jersey',
            'Basketball Official',
            'Yoga Mat Premium',
            'Dumbbells Set',
            'Resistance Bands',
            'Tennis Racket',
            'Cricket Bat',
            'Swimming Goggles',
            'Running Shoes',
            'Fiction Novel Bestseller',
            'Cookbook Collection',
            'Children\'s Storybook',
            'Educational Workbook',
            'Board Game Classic',
            'Chess Set Wooden',
            'Puzzle 1000 Pieces',
            'Action Figure Toy',
            'Building Blocks Set',
            'Remote Control Car',
            'Plush Teddy Bear',
            'Educational Toy Set',
            'Rice 5kg Pack',
            'Coffee Beans 1kg',
            'Tea Variety Pack',
            'Snack Pack Mix',
            'Bottled Water 24 Pack',
            'Instant Noodles Box',
            'Cereal Box',
            'Vitamin C Tablets',
            'Fish Oil Supplements',
            'Protein Powder',
            'First Aid Kit',
            'Hand Sanitizer',
            'Face Mask Pack',
            'Car Phone Mount',
            'Car Vacuum Cleaner',
            'Dash Camera HD',
            'Tire Pressure Gauge',
            'Car Air Freshener',
            'Car Seat Cover',
            'Motorcycle Helmet',
        ];
        
        $baseName = $this->faker->randomElement($baseNames);
        $variants = ['', ' Pro', ' Plus', ' Lite', ' Max', ' Mini', ' Premium', ' Standard', ' 2024', ' Gen2'];
        $name = $baseName . $this->faker->randomElement($variants);
        $basePrice = $this->faker->numberBetween(5000, 500000);
        $hasDiscount = $this->faker->boolean(30);
        $price = $hasDiscount ? $this->faker->numberBetween((int)($basePrice * 0.7), (int)($basePrice * 0.95)) : $basePrice;

        return [
            'name' => $name,
            'description' => $this->faker->sentence(20),
            'price' => $price,
            'base_price' => $basePrice,
            'category_id' => $categoryId,
            'stock' => $this->faker->numberBetween(0, 200),
            'photo1' => null, // Will use placeholder in frontend
            'photo2' => null,
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => 0,
        ]);
    }

    public function lowStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => $this->faker->numberBetween(1, 10),
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => $this->faker->numberBetween(20, 100),
        ]);
    }
}