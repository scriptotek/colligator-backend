<?php

use Faker\Generator as Faker;

$factory->define(Colligator\Collection::class, function (Faker $faker) {
    return [
        'name'  => str_random(10),
        'label' => $faker->sentence(3),
    ];
});
