<?php

/*
 * Larastan checks `view-string` arguments with view()->exists() inside the Testbench
 * app it boots. That app does not load this package's provider (testbench.yaml is
 * intentionally absent), so register the "switchbot" view namespace here.
 */
app('view')->addNamespace('switchbot', __DIR__ . '/resources/views');
