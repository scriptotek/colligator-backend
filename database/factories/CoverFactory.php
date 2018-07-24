<?php

use Faker\Generator as Faker;

$factory->define(Colligator\Cover::class, function (Faker $faker) {
    return [
        'url' => $faker->url(),
    ];
});