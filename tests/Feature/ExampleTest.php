<?php

it('redirects the root page to the dashboard', function () {
    $this->get('/')->assertRedirect(route('dashboard'));
});

it('redirects unknown pages to the dashboard', function () {
    $this->get('/unknown-page')->assertRedirect(route('dashboard'));
});
