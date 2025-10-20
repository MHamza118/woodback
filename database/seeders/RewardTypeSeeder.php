<?php

namespace Database\Seeders;

use App\Models\RewardType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RewardTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rewardTypes = [
            [
                'name' => '50 Points',
                'type' => 'points',
                'value' => 50,
                'description' => 'Recognition points for good performance',
                'icon' => '⭐',
                'active' => true,
            ],
            [
                'name' => '100 Points',
                'type' => 'points',
                'value' => 100,
                'description' => 'Recognition points for excellent performance',
                'icon' => '🌟',
                'active' => true,
            ],
            [
                'name' => '$10 Gift Card',
                'type' => 'gift_card',
                'value' => 10,
                'description' => 'Restaurant gift card reward',
                'icon' => '🎁',
                'active' => true,
            ],
            [
                'name' => 'Extra 15min Break',
                'type' => 'benefit',
                'value' => 15,
                'description' => 'Additional break time',
                'icon' => '☕',
                'active' => true,
            ],
            [
                'name' => 'Premium Parking Spot',
                'type' => 'benefit',
                'value' => 1,
                'description' => 'Reserved parking spot for the week',
                'icon' => '🚗',
                'active' => true,
            ],
        ];

        foreach ($rewardTypes as $rewardType) {
            RewardType::firstOrCreate(
                ['name' => $rewardType['name']],
                $rewardType
            );
        }
    }
}
