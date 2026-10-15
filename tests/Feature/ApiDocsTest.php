<?php

it('serves the API docs to guests outside the local environment', function () {
    // The suite runs as "testing", so this goes through the viewApiDocs gate
    // rather than Scramble's local-only shortcut.
    expect(app()->environment('local'))->toBeFalse();

    $this->get('/docs/api')->assertOk();
});

it('serves the OpenAPI document with bearer auth on protected routes', function () {
    $document = $this->getJson('/docs/api.json')->assertOk()->json();

    expect($document['openapi'])->toStartWith('3.1')
        ->and($document['components']['securitySchemes'])->toHaveKey('http')
        ->and($document['paths']['/auth/login']['post']['security'])->toBe([])
        ->and($document['paths']['/invoices/{invoice}/pdf']['get']['responses'])->toHaveKeys(['200', '202', '409']);
});
