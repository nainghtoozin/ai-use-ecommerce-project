<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Categories
    |--------------------------------------------------------------------------
    |
    | Predefined categories imported into a tenant on demand.
    | Each entry has a name and description.
    |
    */

    'categories' => [
        ['name' => 'Electronics', 'description' => 'Gadgets, devices, and electronic accessories'],
        ['name' => 'Fashion', 'description' => 'Clothing, shoes, and accessories'],
        ['name' => 'Beauty', 'description' => 'Skincare, makeup, and personal care products'],
        ['name' => 'Grocery', 'description' => 'Food, beverages, and household essentials'],
        ['name' => 'Home & Living', 'description' => 'Furniture, decor, and home improvement'],
        ['name' => 'Sports', 'description' => 'Sports equipment, fitness gear, and outdoor items'],
        ['name' => 'Books', 'description' => 'Books, stationery, and educational materials'],
        ['name' => 'Accessories', 'description' => 'Bags, watches, jewelry, and other accessories'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Brands
    |--------------------------------------------------------------------------
    |
    | Predefined brands imported into a tenant on demand.
    | Each entry has a name and description. Slug is auto-generated.
    |
    */

    'brands' => [
        ['name' => 'Generic', 'description' => 'Generic or unbranded products'],
        ['name' => 'No Brand', 'description' => 'Products without a specific brand'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Units
    |--------------------------------------------------------------------------
    |
    | Standard measurement units required by an ecommerce system.
    | Each entry has a name, short_name, and optional description.
    |
    */

    'units' => [
        ['name' => 'Piece', 'short_name' => 'pc', 'description' => 'Single item'],
        ['name' => 'Box', 'short_name' => 'box', 'description' => 'Items packed in a box'],
        ['name' => 'Pack', 'short_name' => 'pk', 'description' => 'Items sold in a pack'],
        ['name' => 'Dozen', 'short_name' => 'dz', 'description' => '12 pieces'],
        ['name' => 'Set', 'short_name' => 'set', 'description' => 'A set of items'],
        ['name' => 'Bottle', 'short_name' => 'btl', 'description' => 'Items in a bottle'],
        ['name' => 'Can', 'short_name' => 'can', 'description' => 'Items in a can'],
        ['name' => 'Kilogram', 'short_name' => 'kg', 'description' => 'Weight in kilograms'],
        ['name' => 'Gram', 'short_name' => 'g', 'description' => 'Weight in grams'],
        ['name' => 'Liter', 'short_name' => 'L', 'description' => 'Volume in liters'],
        ['name' => 'Milliliter', 'short_name' => 'mL', 'description' => 'Volume in milliliters'],
        ['name' => 'Meter', 'short_name' => 'm', 'description' => 'Length in meters'],
        ['name' => 'Centimeter', 'short_name' => 'cm', 'description' => 'Length in centimeters'],
    ],

];
