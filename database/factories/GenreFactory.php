<?php

use Faker\Generator as Faker;

$factory->define(Colligator\Genre::class, function (Faker $faker) {
    return [
        'vocabulary' => 'noubomn',
        'term'       => $faker->sentence(3),
    ];
});