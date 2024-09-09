<?php
namespace coucounco\LaravelOtc\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use coucounco\LaravelOtc\Models\OtcToken;

class OtcTokenFactory extends Factory
{
    protected $model = OtcToken::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'ip' => '127.0.0.1',
        ];
    }
}
