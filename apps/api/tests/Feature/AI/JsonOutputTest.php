<?php

declare(strict_types=1);

use App\Services\AI\JsonOutput;

it('decodes a bare object', function () {
    expect(JsonOutput::object('{"a":1}'))->toBe(['a' => 1]);
});

it('digs the object out of prose and markdown fences', function () {
    // What models actually return, however firmly the prompt says otherwise.
    $text = "Sure! Here's the triage:\n\n```json\n{\"category\":\"payments\",\"urgency\":\"high\"}\n```\n\nHope that helps!";

    expect(JsonOutput::object($text))->toBe(['category' => 'payments', 'urgency' => 'high']);
});

it('returns null for text with no object', function () {
    expect(JsonOutput::object('I cannot help with that.'))->toBeNull();
});

it('returns null for malformed json', function () {
    expect(JsonOutput::object('{"category": "payments",}'))->toBeNull();
});

it('returns null for a json list, which is not the asked-for shape', function () {
    expect(JsonOutput::object('[1,2,3]'))->toBeNull();
});

it('returns null when braces are inverted', function () {
    expect(JsonOutput::object('} not json {'))->toBeNull();
});

it('reads string fields and defaults when absent or non-scalar', function () {
    $data = ['a' => '  padded  ', 'n' => 7, 'obj' => ['x' => 1]];

    expect(JsonOutput::string($data, 'a'))->toBe('padded')
        ->and(JsonOutput::string($data, 'n'))->toBe('7')
        ->and(JsonOutput::string($data, 'obj', 'fallback'))->toBe('fallback')
        ->and(JsonOutput::string($data, 'missing', 'fallback'))->toBe('fallback')
        ->and(JsonOutput::string($data, 'missing'))->toBe('');
});

it('reads positive ints and collapses everything else to null', function () {
    // "no value" arrives in many disguises; a row id must not be conjured from any.
    expect(JsonOutput::positiveInt(['id' => 5], 'id'))->toBe(5)
        ->and(JsonOutput::positiveInt(['id' => '5'], 'id'))->toBe(5)
        ->and(JsonOutput::positiveInt(['id' => 0], 'id'))->toBeNull()
        ->and(JsonOutput::positiveInt(['id' => -3], 'id'))->toBeNull()
        ->and(JsonOutput::positiveInt(['id' => null], 'id'))->toBeNull()
        ->and(JsonOutput::positiveInt(['id' => false], 'id'))->toBeNull()
        ->and(JsonOutput::positiveInt(['id' => 'none'], 'id'))->toBeNull()
        ->and(JsonOutput::positiveInt(['id' => '3.7'], 'id'))->toBeNull()
        ->and(JsonOutput::positiveInt([], 'id'))->toBeNull();
});
