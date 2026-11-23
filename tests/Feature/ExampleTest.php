<?php

test('home displays the public library', function () {
    $this->get(route('home'))->assertOk()->assertSee('Published documents');
});
