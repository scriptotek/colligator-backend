<?php

use Faker\Generator as Faker;

$factory->define(Colligator\Document::class, function (Faker $faker) {
    $bs_id = str_random(10);
    return [
        'bibsys_id'     => $bs_id,
        'bibliographic' => [
            'id'         => $bs_id,
            'electronic' => false,
            'title'      => $faker->sentence(),
            'isbns'      => [$faker->isbn13, $faker->isbn13, $faker->isbn13],
        ],
        'holdings' => [],
    ];
});
