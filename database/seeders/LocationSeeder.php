<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Township;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = [
            [
                'name' => 'Yangon',
                'delivery_fee' => 2000,
                'townships' => [
                    ['name' => 'Hlaing', 'postal_code' => '11051'],
                    ['name' => 'Kamayut', 'postal_code' => '11041'],
                    ['name' => 'Mayangone', 'postal_code' => '11061'],
                    ['name' => 'Insein', 'postal_code' => '11051'],
                    ['name' => 'North Dagon', 'postal_code' => '11421'],
                    ['name' => 'South Dagon', 'postal_code' => '11431'],
                    ['name' => 'East Dagon', 'postal_code' => '11441'],
                    ['name' => 'North Okkalapa', 'postal_code' => '11081'],
                    ['name' => 'South Okkalapa', 'postal_code' => '11091'],
                    ['name' => 'Thingangyun', 'postal_code' => '11071'],
                    ['name' => 'Yankin', 'postal_code' => '11081'],
                    ['name' => 'Sanchaung', 'postal_code' => '11111'],
                    ['name' => 'Pazundaung', 'postal_code' => '11121'],
                    ['name' => 'Dagon', 'postal_code' => '11131'],
                    ['name' => 'Bahan', 'postal_code' => '11201'],
                    ['name' => 'Tarmwe', 'postal_code' => '11211'],
                    ['name' => 'Botataung', 'postal_code' => '11141'],
                    ['name' => 'Kyimyindaing', 'postal_code' => '11101'],
                    ['name' => 'Lanmadaw', 'postal_code' => '11131'],
                    ['name' => 'Latha', 'postal_code' => '11131'],
                ],
            ],
            [
                'name' => 'Mandalay',
                'delivery_fee' => 3000,
                'townships' => [
                    ['name' => 'Chan Aye Thar Zan', 'postal_code' => '05011'],
                    ['name' => 'Maha Aung Myay', 'postal_code' => '05021'],
                    ['name' => 'Aung Myay Thar Zan', 'postal_code' => '05031'],
                    ['name' => 'Pyigyitagon', 'postal_code' => '05041'],
                    ['name' => 'Amarapura', 'postal_code' => '05061'],
                    ['name' => 'Sagaing', 'postal_code' => '05001'],
                    ['name' => 'Mingun', 'postal_code' => '05071'],
                    ['name' => 'Mandalay', 'postal_code' => '05001'],
                ],
            ],
            [
                'name' => 'Naypyidaw',
                'delivery_fee' => 2500,
                'townships' => [
                    ['name' => 'Pyinmana', 'postal_code' => '05001'],
                    ['name' => 'Lewe', 'postal_code' => '06691'],
                    ['name' => 'Zabuthiri', 'postal_code' => '15011'],
                    ['name' => 'Oke Ta Ra Thi Da', 'postal_code' => '15011'],
                    ['name' => 'Dekkhina Thiri', 'postal_code' => '15011'],
                    ['name' => 'Tatmadaw', 'postal_code' => '15011'],
                    ['name' => 'Pobbathiri', 'postal_code' => '15011'],
                ],
            ],
            [
                'name' => 'Bago',
                'delivery_fee' => 3000,
                'townships' => [
                    ['name' => 'Bago', 'postal_code' => '06001'],
                    ['name' => 'Taungoo', 'postal_code' => '06011'],
                    ['name' => 'Pyay', 'postal_code' => '06021'],
                    ['name' => 'Thayarwady', 'postal_code' => '06031'],
                ],
            ],
            [
                'name' => 'Ayeyarwady',
                'delivery_fee' => 3500,
                'townships' => [
                    ['name' => 'Pathein', 'postal_code' => '07001'],
                    ['name' => 'Hinthada', 'postal_code' => '07011'],
                    ['name' => 'Maubin', 'postal_code' => '07021'],
                    ['name' => 'Pyapon', 'postal_code' => '07031'],
                    ['name' => 'Myaungmya', 'postal_code' => '07041'],
                    ['name' => 'Wakema', 'postal_code' => '07051'],
                ],
            ],
            [
                'name' => 'Sagaing',
                'delivery_fee' => 3000,
                'townships' => [
                    ['name' => 'Sagaing', 'postal_code' => '04001'],
                    ['name' => 'Monywa', 'postal_code' => '04011'],
                    ['name' => 'Shwebo', 'postal_code' => '04021'],
                    ['name' => 'Monywa', 'postal_code' => '04011'],
                ],
            ],
            [
                'name' => 'Shan',
                'delivery_fee' => 4000,
                'townships' => [
                    ['name' => 'Taunggyi', 'postal_code' => '15011'],
                    ['name' => 'Lashio', 'postal_code' => '03101'],
                    ['name' => 'Kengtung', 'postal_code' => '05101'],
                    ['name' => 'Tachileik', 'postal_code' => '05201'],
                    ['name' => 'Muse', 'postal_code' => '03131'],
                ],
            ],
            [
                'name' => 'Magway',
                'delivery_fee' => 3500,
                'townships' => [
                    ['name' => 'Magway', 'postal_code' => '03001'],
                    ['name' => 'Minbu', 'postal_code' => '03011'],
                    ['name' => 'Thayet', 'postal_code' => '03021'],
                    ['name' => 'Pakokku', 'postal_code' => '03031'],
                ],
            ],
        ];

        foreach ($locations as $cityData) {
            $city = City::create([
                'name' => $cityData['name'],
                'delivery_fee' => $cityData['delivery_fee'],
                'is_active' => true,
            ]);

            foreach ($cityData['townships'] as $townshipData) {
                Township::create([
                    'city_id' => $city->id,
                    'name' => $townshipData['name'],
                    'postal_code' => $townshipData['postal_code'],
                    'is_active' => true,
                ]);
            }
        }
    }
}
