<?php

use App\Handlers\MessageHandlerRegistry;
use Discord\Discord;

it('dispatches to the registered handler when prefix matches exactly', function () {
    $registry = new MessageHandlerRegistry;
    $called = false;

    $registry->register('!kciao', function () use (&$called) {
        $called = true;
    });

    $message = fakeMessage(['content' => '!kciao']);
    $discord = Mockery::mock(Discord::class);

    $registry->dispatch($message, $discord);

    expect($called)->toBeTrue();
});

it('dispatches when message starts with prefix followed by a space', function () {
    $registry = new MessageHandlerRegistry;
    $called = false;

    $registry->register('!kciao', function () use (&$called) {
        $called = true;
    });

    $message = fakeMessage(['content' => '!kciao world']);
    $discord = Mockery::mock(Discord::class);

    $registry->dispatch($message, $discord);

    expect($called)->toBeTrue();
});

it('does not dispatch when no prefix matches', function () {
    $registry = new MessageHandlerRegistry;
    $called = false;

    $registry->register('!kciao', function () use (&$called) {
        $called = true;
    });

    $message = fakeMessage(['content' => '!unknown']);
    $discord = Mockery::mock(Discord::class);

    $registry->dispatch($message, $discord);

    expect($called)->toBeFalse();
});

it('does not dispatch on partial prefix match without space boundary', function () {
    $registry = new MessageHandlerRegistry;
    $called = false;

    $registry->register('!kciao', function () use (&$called) {
        $called = true;
    });

    $message = fakeMessage(['content' => '!kciaoExtra']);
    $discord = Mockery::mock(Discord::class);

    $registry->dispatch($message, $discord);

    expect($called)->toBeFalse();
});

it('dispatches only the first matching handler and stops', function () {
    $registry = new MessageHandlerRegistry;
    $firstCalled = false;
    $secondCalled = false;

    $registry->register('!k', function () use (&$firstCalled) {
        $firstCalled = true;
    });
    $registry->register('!kciao', function () use (&$secondCalled) {
        $secondCalled = true;
    });

    $message = fakeMessage(['content' => '!kciao']);
    $discord = Mockery::mock(Discord::class);

    $registry->dispatch($message, $discord);

    expect($firstCalled || $secondCalled)->toBeTrue();
    expect($firstCalled && $secondCalled)->toBeFalse();
});
