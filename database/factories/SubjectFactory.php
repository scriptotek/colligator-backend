<?php

use Faker\Generator as Faker;

$factory->define(Colligator\Subject::class, function (Faker $faker) {
    return [
        'vocabulary' => 'noubomn',
        'term'       => $faker->sentence(3),
    ];
});