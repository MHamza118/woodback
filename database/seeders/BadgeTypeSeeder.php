<?php

namespace Database\Seeders;

use App\Models\BadgeType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BadgeTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $badgeTypes = [
            [
                'name' => 'Customer Service Star',
                'description' => 'Exceptional customer service',
                'icon' => '⭐',
                'color' => 'gold',
                'criteria' => 'Outstanding customer feedback and service quality',
                'active' => true,
            ],
            [
                'name' => 'Team Player',
                'description' => 'Excellent teamwork and collaboration',
                'icon' => '🤝',
                'color' => 'blue',
                'criteria' => 'Consistently helps colleagues and works well in teams',
                'active' => true,
            ],
            [
                'name' => 'Efficiency Expert',
                'description' => 'Outstanding efficiency and productivity',
                'icon' => '⚡',
                'color' => 'yellow',
                'criteria' => 'Consistently meets and exceeds performance targets',
                'active' => true,
            ],
            [
                'name' => 'Innovation Champion',
                'description' => 'Creative problem solving and innovation',
                'icon' => '💡',
                'color' => 'purple',
                'criteria' => 'Brings innovative ideas and solutions to the workplace',
                'active' => true,
            ],
            [
                'name' => 'Mentor Master',
                'description' => 'Excellent at training and mentoring new staff',
                'icon' => '👨‍🏫',
                'color' => 'green',
                'criteria' => 'Successfully mentors and trains new employees',
                'active' => true,
            ],
            [
                'name' => 'Attendance Ace',
                'description' => 'Perfect attendance record',
                'icon' => '📅',
                'color' => 'orange',
                'criteria' => 'Maintains excellent attendance and punctuality',
                'active' => true,
            ],
        ];

        foreach ($badgeTypes as $badgeType) {
            BadgeType::firstOrCreate(
                ['name' => $badgeType['name']],
                $badgeType
            );
        }
    }
}
