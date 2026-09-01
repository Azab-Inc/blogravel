<?php

use App\Services\Ai\PromptBuilder;

it('builds prompt with paragraphs length', function () {
    $result = PromptBuilder::build(' Laravel best practices', ['title', 'content'], [
        'length_type' => 'paragraphs',
        'length_value' => 4,
    ]);

    expect($result)->toContain('title, content')
        ->toContain('4 paragraphs')
        ->toContain(' Laravel best practices');
});

it('builds prompt with characters length', function () {
    $result = PromptBuilder::build('PHP tips', ['content', 'excerpt'], [
        'length_type' => 'characters',
        'length_value' => 2000,
    ]);

    expect($result)->toContain('2000 characters');
});

it('defaults to 4 paragraphs when no options given', function () {
    $result = PromptBuilder::build('test topic', ['title']);

    expect($result)->toContain('4 paragraphs');
});
