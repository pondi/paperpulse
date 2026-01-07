<?php

it('includes data uris in the connect-src policy', function () {
    $response = $this->get('/');

    $response->assertOk();

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain('connect-src');
    expect($csp)->toContain('data:');
});

it('allows camera access from self', function () {
    $response = $this->get('/');

    $response->assertOk();

    $permissionsPolicy = $response->headers->get('Permissions-Policy');

    expect($permissionsPolicy)->toContain('camera=(self)');
});
