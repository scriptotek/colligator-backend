<?php

/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| Here you may define all of your model factories. Model factories give
| you a convenient way to create models for testing and seeding your
| database. Just tell the factory how a default model should look.
|
*/

$factory->define(Colligator\User::class, function (\Faker\Generator $faker) {
    return [
        'name' => $faker->name,
        'email' => $faker->email,
        'password' => str_random(10),
        'remember_token' => str_random(10),
    ];
});

$factory->define(Colligator\Document::class, function (\Faker\Generator $faker) {
    $bs_id = str_random(10);
    return [
        'bibsys_id' => $bs_id,
        'bibliographic' => [
            'id' => $bs_id,
            'title' => $faker->sentence(),
            'isbns' => [$faker->isbn13, $faker->isbn13, $faker->isbn13]
        ],
        'holdings' => [],
    ];
});

$factory->define(Colligator\Subject::class, function (\Faker\Generator $faker) {
    return [
        'vocabulary' => 'noubomn',
        'term' => $faker->sentence(3),
    ];
});
