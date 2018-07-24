<?php

use Faker\Generator as Faker;

$factory->define(Colligator\User::class, function (Faker $faker) {
    return [
        'name'           => $faker->name,
        'email'          => $faker->email,
        'password'       => str_random(10),
        'remember_token' => str_random(10),
    ];
});