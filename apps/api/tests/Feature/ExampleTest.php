<?php

declare(strict_types=1);

test('the health endpoint returns a successful response', function () {
    $this->get('/up')->assertOk();
});
