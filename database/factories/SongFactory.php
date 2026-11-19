<?php

namespace Database\Factories;

use App\Models\Song;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Song>
 */
class SongFactory extends Factory
{
    protected $model = Song::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(3),
            'artist' => $this->faker->name(),
            'original_key' => 'C',
            'content' => "[C] Letra de prueba\n[G] Acordes",
            'is_public' => false,
        ];
    }
}
